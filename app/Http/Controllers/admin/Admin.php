<?php

namespace App\Http\Controllers\admin;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\Config;
use App\Models\Menu;
use App\Models\MenuOption;
use App\Models\Orders;
use App\Models\OrdersDetails;
use App\Models\OrdersOption;
use App\Models\Pay;
use App\Models\PayGroup;
use App\Models\RiderSend;
use App\Models\Table;
use App\Models\User;
use BaconQrCode\Encoder\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PromptPayQR\Builder;
use Illuminate\Support\Facades\Schema;
class Admin extends Controller
{
  public function dashboard()
{
    $data['function_key'] = __FUNCTION__;
    if (session('user')->is_rider != 1) {
        $data['orderday'] = $this->getCompletedOrdersTotal('day');
        $data['ordermouth'] = $this->getCompletedOrdersTotal('month');
        $data['orderyear'] = $this->getCompletedOrdersTotal('year');
        
        // คำนวณเงินสดและเงินโอน
        $data['moneyDay'] = $this->getPaymentTotalsByType(0, 'day'); // เงินสด
        $data['transferDay'] = $this->getPaymentTotalsByType(1, 'day'); // เงินโอน + สลิป

        $data['delivery'] = Orders::whereIn('status', [3, 5])
            ->whereNotNull('table_id')
            ->whereDate('created_at', date('Y-m-d'))
            ->count();
    } else {
        $data['delivery_day'] = Orders::join('rider_sends', 'rider_sends.order_id', '=', 'orders.id')
            ->whereIn('orders.status', [3, 5])
            ->where('rider_id', session('user')->id)
            ->whereDate('orders.created_at', date('Y-m-d'))
            ->count();
            
        $data['delivery_mouth'] = Orders::join('rider_sends', 'rider_sends.order_id', '=', 'orders.id')
            ->whereIn('orders.status', [3, 5])
            ->where('rider_id', session('user')->id)
            ->whereMonth('orders.created_at', date('m'))
            ->whereYear('orders.created_at', date('Y'))
            ->count();
    }
    
    $data['ordertotal'] = Orders::count();
    $data['rider'] = User::where('is_rider', 1)->get();

    $menu = Menu::select('id', 'name')->get();
    $item_menu = array();
    $item_order = array();
    if (count($menu) > 0) {
        foreach ($menu as $rs) {
            $item_menu[] = $rs->name;
            $menu_order = OrdersDetails::Join('orders', 'orders.id', '=', 'orders_details.order_id')
                ->whereIn('orders.status', [3, 5])
                ->where('menu_id', $rs->id)
                ->groupBy('menu_id')
                ->count();
            $item_order[] = $menu_order;
        }
    }

    $item_mouth = array();
    for ($i = 1; $i < 13; $i++) {
        $query = $this->getCompletedOrdersTotal('month', $i);
        $item_mouth[] = $query->total ?? 0; 
    }
    
    $data['item_menu'] = $item_menu;
    $data['item_order'] = $item_order;
    $data['item_mouth'] = $item_mouth;
    $data['config'] = Config::first();
    return view('dashboard', $data);
}
    public function ListOrder()
    {
        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];
        $order = DB::table('orders as o')
            ->select(
                'o.table_id',
                DB::raw('SUM(o.total) as total'),
                DB::raw('MAX(o.created_at) as created_at'),
                DB::raw('MAX(o.status) as status'),
                DB::raw('MAX(o.remark) as remark'),
                DB::raw('SUM(CASE WHEN o.status = 1 THEN 1 ELSE 0 END) as has_status_1')
            )
            ->whereNotNull('o.table_id')
            ->whereIn('o.status', [1, 2])
            ->groupBy('o.table_id')
            ->orderByDesc('has_status_1')
            ->orderByDesc(DB::raw('MAX(o.created_at)'))
            ->get();

        if (count($order) > 0) {
            $info = [];
            foreach ($order as $rs) {
                $status = '';
                $pay = '';
                if ($rs->has_status_1 > 0) {
                    $status = '<button type="button" class="btn btn-sm btn-primary update-status" data-id="' . $rs->table_id . '">กำลังทำอาหาร</button>';
                } else {
                    $status = '<button class="btn btn-sm btn-success">ออเดอร์สำเร็จแล้ว</button>';
                }

                if ($rs->status != 3) {
                    $pay = '<a href="' . route('printOrderAdmin', $rs->table_id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ปริ้นออเดอร์</a>
                    <a href="' . route('printOrderAdminCook', $rs->table_id) . '" target="_blank" type="button" class="btn btn-sm btn-outline-primary m-1">ปริ้นออเดอร์ในครัว</a>
                    <button data-id="' . $rs->table_id . '" data-total="' . $rs->total . '" type="button" class="btn btn-sm btn-outline-success modalPay">ชำระเงิน</button>';
                }
                $flag_order = '<button class="btn btn-sm btn-success">สั่งหน้าร้าน</button>';
                $action = '<button data-id="' . $rs->table_id . '" type="button" class="btn btn-sm btn-outline-primary modalShow m-1">รายละเอียด</button>' . $pay;
                $table = Table::find($rs->table_id);
                $info[] = [
                    'flag_order' => $flag_order,
                    'table_id' => $table->table_number,
                    'total' => $rs->total,
                    'remark' => $rs->remark,
                    'status' => $status,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    public function listOrderDetail(Request $request)
    {
        $orders = Orders::where('table_id', $request->input('id'))
            ->whereIn('status', [1, 2])
            ->get();
        $info = '';
        foreach ($orders as $order) {
            $info .= '<div class="mb-3">';
            $info .= '<div class="row"><div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $order->id . '</h5></div>
            <div class="col-auto d-flex align-items-start">';
            if ($order->status != 2) {
                $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-primary updatestatusOrder m-1" data-id="' . $order->id . '">อัพเดทออเดอร์สำเร็จแล้ว</button>';
                $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelOrderSwal m-1" data-id="' . $order->id . '">ยกเลิกออเดอร์</button>';
            }
            $info .= '</div></div>';
            $orderDetails = OrdersDetails::where('order_id', $order->id)->get()->groupBy('menu_id');
            foreach ($orderDetails as $details) {
                $menuName = optional($details->first()->menu)->name ?? 'ไม่พบชื่อเมนู';
                $orderOption = OrdersOption::where('order_detail_id', $details->first()->id)->get();
                foreach ($details as $detail) {
                    $detailsText = [];
                    if ($orderOption->isNotEmpty()) {
                        foreach ($orderOption as $key => $option) {
                            $optionName = MenuOption::find($option->option_id);
                            $detailsText[] = $optionName->type;
                        }
                        $detailsText = implode(',', $detailsText);
                    }
                    $optionType = $menuName;
                    $priceTotal = number_format($detail->price, 2);
                    $info .= '<ul class="list-group mb-1 shadow-sm rounded">';
                    $info .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
                    $info .= '<div class="flex-grow-1">';
                    $info .= '<div><span class="fw-bold">' . htmlspecialchars($optionType) . '</span></div>';
                    if (!empty($detailsText)) {
                        $info .= '<div class="small text-secondary mb-1 ps-2">+ ' . $detailsText . '</div>';
                    }
                    if (!empty($detail->remark)) {
                        $info .= '<div class="small text-secondary mb-1 ps-2">+ หมายเหตุ : ' . $detail->remark . '</div>';
                    }
                    $info .= '</div>';
                    $info .= '<div class="text-end d-flex flex-column align-items-end">';
                    $info .= '<div class="mb-1">จำนวน: ' . $detail->quantity . '</div>';
                    $info .= '<div>';
                    $info .= '<button class="btn btn-sm btn-primary me-1">' . $priceTotal . ' บาท</button>';
                    $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelMenuSwal" data-id="' . $detail->id . '">ยกเลิก</button>';
                    $info .= '</div>';
                    $info .= '</div>';
                    $info .= '</li>';
                    $info .= '</ul>';
                }
            }
            $info .= '</div>';
        }
        echo $info;
    }

    public function printOrderAdmin($table_id)
    {
        $config = Config::first();
        $orders = Orders::where('table_id', $table_id)
            ->whereIn('status', [1, 2])
            ->get();

        $order_details = [];
        foreach ($orders as $order) {
            $details = OrdersDetails::where('order_id', $order->id)
                ->with('menu', 'option.option')
                ->get();
            $order_details = array_merge($order_details, $details->toArray());
        }

        $table = Table::find($table_id);

        $data = [
            'config' => $config,
            'orders' => $orders,
            'order_details' => $order_details,
            
            'table' => $table,
            'table_id' => $orders->first()->table_id ?? null,
            'type' => 'order_admin'
        ];
        return view('print_web', ['jsonData' => json_encode($data)]);
    }

   public function printOrderAdminCook($id)
    {
        $config = Config::first();
        $orders = Orders::with(['orderdetail.menu', 'orderdetail.option.option', 'table'])
            ->where(function ($q) use ($id) {
                $q->where('table_id', $id)
                    ->orWhere('id', $id);
            })
            ->whereIn('status', [1, 2])
            ->get();
        // If no orders are found, treat the id as order id (for delivery orders)
        if ($orders->isEmpty()) {
            $orders = Orders::where('id', $id)
                ->whereIn('status', [1, 2])
                ->get();
        }

        
        Orders::whereIn('id', $orders->pluck('id'))
            ->update(['is_print_cook' => 1]);
        $order_details = $orders->flatMap(function ($order) {
            return $order->orderdetail;
        })->map(function ($detail) {
            return $detail->toArray();
        })->values()->all();

 
        $table = Table::find($id);
        if (!$table && $orders->isNotEmpty()) {
            $table = $orders->first()->table;
        }

        $data = [
            'config' => $config,
            'orders' => $orders,
            'order_details' => $order_details,
            'table' => $table,
            'table_id' => $orders->first()->table_id ?? null,
            'type' => 'order_cook'
        ];
        return view('print_web', ['jsonData' => json_encode($data)]);
    }
    public function checkNewOrders()
    {
        $order = Orders::where('is_print_cook', 0)
            ->whereIn('status', [1, 2])
            ->orderBy('created_at')
            ->first();

        if ($order) {
            if ($order->table_id !== null) {
                Orders::where('table_id', $order->table_id)
                    ->where('is_print_cook', 0)
                    ->update(['is_print_cook' => 1]);
            } else {
                $order->update(['is_print_cook' => 1]);
            }

            return response()->json([
                'status' => true,
                'table_id' => $order->table_id,
                'id' => $order->id,
            ]);
        }

        return response()->json(['status' => false]);
    }

    public function config()
    {
        $data['function_key'] = __FUNCTION__;
        $data['config'] = Config::first();
        return view('config', $data);
    }

    public function ConfigSave(Request $request)
    {
        $input = $request->input();
        $config = Config::find($input['id']);
        $config->name = $input['name'];
        $config->color1 = $input['color1'];
        $config->color2 = $input['color2'];
        $config->color_font = $input['color_font'];
        $config->color_category = $input['color_category'];
        $config->promptpay = $input['promptpay'];

        if ($request->hasFile('image_bg')) {
            $file = $request->file('image_bg');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('image', $filename, 'public');
            $config->image_bg = $path;
        }
        if ($request->hasFile('image_qr')) {
            $file = $request->file('image_qr');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('image', $filename, 'public');
            $config->image_qr = $path;
        }
        if ($config->save()) {
            return redirect()->route('config')->with('success', 'บันทึกรายการเรียบร้อยแล้ว');
        }
        return redirect()->route('config')->with('error', 'ไม่สามารถบันทึกข้อมูลได้');
    }

  public function confirm_pay(Request $request)
{
    $data = [
        'status' => false,
        'message' => 'ชำระเงินไม่สำเร็จ',
    ];
    
    $id = $request->input('id');
    $paymentType = $request->input('value'); 
    $receivedAmount = $request->input('received_amount', null);
    $changeAmount = $request->input('change_amount', null);
    
    if ($id) {
        $total = DB::table('orders as o')
            ->select(
                'o.table_id',
                DB::raw('SUM(o.total) as total'),
            )
            ->whereNotNull('table_id')
            ->groupBy('o.table_id')
            ->where('table_id', $id)
            ->whereIn('status', [1, 2])
            ->first();
            
        if (!$total) {
            $data['message'] = 'ไม่พบข้อมูลออเดอร์';
            return response()->json($data);
        }
        
        // ตรวจสอบการชำระเงินสด
        if ($paymentType == 0 && $receivedAmount < $total->total) {
            $data['message'] = 'จำนวนเงินที่รับมาไม่เพียงพอ';
            return response()->json($data);
        }
        
        try {
            DB::beginTransaction();
            
            $pay = new Pay();
            $pay->payment_number = $this->generateRunningNumber();
            $pay->table_id = $id;
            $pay->total = $total->total;
            $pay->is_type = $paymentType;
            
            // เพิ่มข้อมูลเงินสดและเงินทอน
            if ($paymentType == 0) {
                $pay->received_amount = $receivedAmount;
                $pay->change_amount = $changeAmount;
            }
            
            if ($pay->save()) {
                $orders = Orders::where('table_id', $id)->whereIn('status', [1, 2])->get();
                
                foreach ($orders as $order) {
                    $order->status = 3;
                    if ($order->save()) {
                        $paygroup = new PayGroup();
                        $paygroup->pay_id = $pay->id;
                        $paygroup->order_id = $order->id;
                        $paygroup->save();
                    }
                }
                
                DB::commit();
                
                $message = 'ชำระเงินเรียบร้อยแล้ว';
                
                if ($paymentType == 0) {
                    $message .= '<br>เลขที่ใบเสร็จ: ' . $pay->payment_number;
                    $message .= '<br>ยอดที่ต้องชำระ: ' . number_format($total->total, 2) . ' ฿';
                    $message .= '<br>เงินที่รับมา: ' . number_format($receivedAmount, 2) . ' ฿';
                    
                    if ($changeAmount > 0) {
                        $message .= '<br>เงินทอน: ' . number_format($changeAmount, 2) . ' ฿';
                    } else {
                        $message .= '<br>จ่ายพอดี';
                    }
                }
                
                $data = [
                    'status' => true,
                    'message' => $message,
                    'payment_info' => [
                        'payment_number' => $pay->payment_number,
                        'total' => $total->total,
                        'received_amount' => $receivedAmount,
                        'change_amount' => $changeAmount,
                        'payment_type' => $paymentType
                    ]
                ];
                
                $this->sendPaymentNotification($id, $pay, $paymentType);
            }
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Payment Error: ' . $e->getMessage());
            $data['message'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
    
    return response()->json($data);
}

private function sendPaymentNotification($tableId, $pay, $paymentType)
{
    try {
        $table = Table::find($tableId);
        $tableNumber = $table ? $table->table_number : 'ไม่ระบุ';
        
        $paymentTypeText = $paymentType == 0 ? 'เงินสด' : 'โอนเงิน';
        
        $message = "💰 ชำระเงินจาก โต้ะ {$tableNumber}";
        $subMessage = "ประเภท: {$paymentTypeText} | ยอดเงิน: " . number_format($pay->total, 2) . " บาท";
        
        if ($paymentType == 0 && isset($pay->change_amount) && $pay->change_amount > 0) {
            $subMessage .= " | เงินทอน: " . number_format($pay->change_amount, 2) . " บาท";
        }
        
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->insert([
                'type' => 'payment',
                'table_id' => $tableId,
                'table_number' => $tableNumber,
                'message' => $message,
                'sub_message' => $subMessage,
                'amount' => $pay->total,
                'payment_type' => $paymentType,
                'received_amount' => $pay->received_amount ?? null,
                'change_amount' => $pay->change_amount ?? null,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        event(new OrderCreated([$message . " - " . $subMessage]));
        
    } catch (\Exception $e) {
        \Log::error('Payment notification error: ' . $e->getMessage());
    }
}
    public function confirm_pay_rider(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ชำระเงินไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $order = Orders::find($id);
            $order->is_pay = 1;
            $order->is_type = $request->input('value');
            if ($order->save()) {
                $data = [
                    'status' => true,
                    'message' => 'ชำระเงินเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    function DateThai($strDate)
    {
        $strYear = date("Y", strtotime($strDate)) + 543;
        $strMonth = date("n", strtotime($strDate));
        $strDay = date("j", strtotime($strDate));
        $time = date("H:i", strtotime($strDate));
        $strMonthCut = array("", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม");
        $strMonthThai = $strMonthCut[$strMonth];
        return "$strDay $strMonthThai $strYear" . " " . $time;
    }

    public function generateQr(Request $request)
    {
        $config = Config::first();
        if ($config->promptpay != '') {
            $total = $request->total;
            $qr = Builder::staticMerchantPresentedQR($config->promptpay)->setAmount($total)->toSvgString();
            echo '<div class="row g-3 mb-3">
                <div class="col-md-12">
                    ' . $qr . '
                </div>
            </div>';
        } elseif ($config->image_qr != '') {
            echo '
        <div class="row g-3 mb-3">
            <div class="col-md-12">
            <img width="100%" src="' . url('storage/' . $config->image_qr) . '">
            </div>
        </div>';
        }
    }

    public function confirm_rider(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ส่งข้อมูลไปยังไรเดอร์ไม่สำเร็จ',
        ];
        $input = $request->input();
        if ($input['id']) {
            $order = Orders::find($input['id']);
            $order->status = 2;
            if ($order->save()) {
                $rider_save = new RiderSend();
                $rider_save->order_id = $input['id'];
                $rider_save->rider_id = $input['rider_id'];
                if ($rider_save->save()) {
                    $data = [
                        'status' => true,
                        'message' => 'ส่งข้อมูลไปยังไรเดอร์เรียบร้อยแล้ว',
                    ];
                }
            }
        }
        return response()->json($data);
    }

    function generateRunningNumber($prefix = '', $padLength = 7)
    {
        $latest = Pay::orderBy('id', 'desc')->first();

        if ($latest && isset($latest->payment_number)) {
            $number = (int) ltrim($latest->payment_number, '0');
            $next = $number + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad($next, $padLength, '0', STR_PAD_LEFT);
    }

    public function order()
    {
        $data['function_key'] = 'order';
        $data['rider'] = User::where('is_rider', 1)->get();
        $data['config'] = Config::first();
        return view('order', $data);
    }

  
    public function ListOrderPay()
    {
        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];

        $payList = Pay::orderBy('id', 'desc')->get();

        $orderList = Orders::whereIn('status', [4, 5])
            ->orderBy('id', 'desc')
            ->get();

        $info = [];

foreach ($payList as $pay) {
    $paymentType = $pay->is_type == 0 ? 'เงินสด' : 'โอนเงิน';
    $paymentClass = 'badge bg-success';

    $action = '';
    $action .= '<button type="button" data-id="' . $pay->id . '" data-type="pay" class="btn btn-sm btn-outline-info modalShowPay me-1">
                   รายละเอียดบิล
               </button>';

    $action .= '<button type="button" data-id="' . $pay->id . '" class="btn btn-sm btn-outline-secondary preview-short me-1">
                   พรีวิวใบเสร็จ
               </button>';

    $action .= '<button type="button" data-id="' . $pay->id . '" class="btn btn-sm btn-outline-warning modalTax">
                   ออกใบกำกับภาษี
               </button>';

    $info[] = [
        'payment_number' => $pay->payment_number,
        'type' => '<span class="' . $paymentClass . '">' . $paymentType . '</span>',
        'table_id' => 'โต้ะ ' . ($pay->table_id ?? 'Online'),
        'total' => number_format($pay->total, 2) . ' ฿',
        'created' => $this->DateThai($pay->created_at),
        'action' => $action,
        'data_type' => 'pay',
        'sort_date' => $pay->created_at
    ];
}

// รายการ
foreach ($orderList as $order) {
    $paymentType = '';
    $paymentClass = '';

    if ($order->status == 4) {
        $paymentType = 'รอตรวจสอบสลิป';
        $paymentClass = 'badge bg-warning text-dark';
    } elseif ($order->status == 5) {
        $paymentType = 'ยืนยันการชำระแล้ว';
        $paymentClass = 'badge bg-success';
    }

    $action = '';
    $action .= '<button type="button" data-id="' . $order->id . '" data-type="order" class="btn btn-sm btn-outline-info modalShowPay me-1">
                   รายละเอียดบิล
               </button>';

    // ปุ่มดูสลิป 
    if ($order->image) {
        $action .= '<button type="button" data-image="' . url('storage/' . $order->image) . '" class="btn btn-sm btn-outline-primary viewSlip me-1">
                       ดูสลิปโอนเงิน
                   </button>';
    }

    // ปุ่มยืนยัน/ปฏิเสธ 
    if ($order->status == 4) {
        $action .= '<button type="button" data-id="' . $order->id . '" class="btn btn-sm btn-outline-success confirmPayment me-1" title="ยืนยันการชำระ">
                       ยืนยันการชำระ
                   </button>';

        $action .= '<button type="button" data-id="' . $order->id . '" class="btn btn-sm btn-outline-danger rejectPayment me-1" title="ปฏิเสธการชำระ">
                       ปฏิเสธการชำระ
                   </button>';
    } else {
        // ปุ่มสำหรับยืนยันแล้ว
        $action .= '<button type="button" data-id="' . $order->id . '" class="btn btn-sm btn-outline-warning preview-short-order me-1">
                       พรีวิวใบเสร็จ
                   </button>';
        
        $action .= '<button type="button" data-id="' . $order->id . '" class="btn btn-sm btn-outline-warning modalTax">
                       ออกใบกำกับภาษี
                   </button>';
    }

    $info[] = [
        'payment_number' => str_pad($order->id, 8, '0', STR_PAD_LEFT),
        'type' => '<span class="' . $paymentClass . '">' . $paymentType . '</span>',
        'table_id' => 'โต้ะ ' . ($order->table_id ?? 'N/A'),
        'total' => number_format($order->total, 2) . ' ฿',
        'created' => $this->DateThai($order->created_at),
        'action' => $action,
        'data_type' => 'order',
        'sort_date' => $order->created_at
    ];
}


        // เรียงลำดับตามวันที่
        usort($info, function ($a, $b) {
            return strtotime($b['sort_date']) - strtotime($a['sort_date']);
        });

        $data = [
            'data' => $info,
            'status' => true,
            'message' => 'success'
        ];

        return response()->json($data);
    }

    public function ListOrderPayRider()
    {
        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];
        $pay = Pay::whereNull('table_id')->get();

        if (count($pay) > 0) {
            $info = [];
            foreach ($pay as $rs) {
                if ($rs->is_type != 0) {
                    $type = 'ชำระโอนเงิน';
                } else {
                    $type = 'ชำระเงินสด';
                }
                $action = '<button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-success preview-short m-1">พรีวิวใบเสร็จ</button>
                <button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalTax m-1">ออกใบกำกับภาษี</button>
                <button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalShowPay m-1">รายละเอียด</button>';
                $info[] = [
                    'payment_number' => $rs->payment_number,
                    'table_id' => $rs->table_id,
                    'total' => $rs->total,
                    'type' => $type,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    public function confirmSlipPayment(Request $request)
{
    $data = [
        'status' => false,
        'message' => 'ไม่สามารถยืนยันการชำระเงินได้',
    ];

    $orderId = $request->input('order_id');

    if ($orderId) {
        $order = Orders::find($orderId);

        if ($order && $order->status == 4) {
            $order->status = 5;

            if ($order->save()) {
           
                
                $data = [
                    'status' => true,
                    'message' => 'ยืนยันการชำระเงินเรียบร้อยแล้ว',
                ];
            }
        } else {
            $data['message'] = 'ไม่พบออเดอร์หรือสถานะไม่ถูกต้อง';
        }
    }

    return response()->json($data);
}

   
    public function rejectSlipPayment(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ไม่สามารถปฏิเสธการชำระเงินได้',
        ];

        $orderId = $request->input('order_id');
        $reason = $request->input('reason', '');

        if ($orderId) {
            $order = Orders::find($orderId);

            if ($order && $order->status == 4) {
                $order->status = 1;

                // ลบรูปสลิป
                if ($order->image) {
                    $imagePath = storage_path('app/public/' . $order->image);
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                    $order->image = null;
                }

                // เพิ่มเหตุผลในหมายเหตุ
                if ($reason) {
                    $currentRemark = $order->remark;
                    $rejectNote = 'ปฏิเสธการชำระเงิน: ' . $reason;
                    $order->remark = $currentRemark ? $currentRemark . ' | ' . $rejectNote : $rejectNote;
                }

                if ($order->save()) {
                    $data = [
                        'status' => true,
                        'message' => 'ปฏิเสธการชำระเงินเรียบร้อยแล้ว',
                    ];
                }
            } else {
                $data['message'] = 'ไม่พบออเดอร์หรือสถานะไม่ถูกต้อง';
            }
        }

        return response()->json($data);
    }
    public function printReceiptFromOrder($orderId)
{
    $config = Config::first();
    $order = Orders::with('user')->find($orderId);
    
    if (!$order) {
        abort(404, 'ไม่พบออเดอร์');
    }

    $order_details = OrdersDetails::where('order_id', $orderId)
        ->with('menu', 'option.option')
        ->get();

    $pay = (object)[
        'id' => $orderId,
        'payment_number' => str_pad($orderId, 8, '0', STR_PAD_LEFT),
        'total' => $order->total,
        'is_type' => 1, 
        'created_at' => $order->created_at,
        'table_id' => $order->table_id
    ];

    $data = [
        'config' => $config,
        'pay' => $pay,
        'order' => $order_details,
        'users' => $order->user ?? null,
        'type' => 'slip_payment'
    ];

    return view('print_web', ['jsonData' => json_encode($data)]);
}

    /**
     * แก้ไข ListOrderPay ให้ถูกต้อง - ดึงรายการจาก Pay table และ Orders table
     */

    public function listOrderDetailPay(Request $request)
    {
        $id = $request->input('id');
        $type = $request->input('type', 'pay'); // ระบุว่าเป็นข้อมูลจาก pay หรือ order

        $info = '';

        if ($type === 'pay') {
            $paygroup = PayGroup::where('pay_id', $id)->get();
            foreach ($paygroup as $pg) {
                $orderDetailsGrouped = OrdersDetails::where('order_id', $pg->order_id)
                    ->with('menu', 'option')
                    ->get()
                    ->groupBy('menu_id');

                if ($orderDetailsGrouped->isNotEmpty()) {
                    $info .= '<div class="mb-3">';
                    $info .= '<div class="row"><div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $pg->order_id . '</h5></div></div>';

                    foreach ($orderDetailsGrouped as $details) {
                        $this->generateOrderDetailHTML($details, $info);
                    }
                    $info .= '</div>';
                }
            }
        } else {
            $order = Orders::find($id);
            if ($order) {
                $orderDetailsGrouped = OrdersDetails::where('order_id', $id)
                    ->with('menu', 'option')
                    ->get()
                    ->groupBy('menu_id');

                if ($orderDetailsGrouped->isNotEmpty()) {
                    $info .= '<div class="mb-3">';
                    $info .= '<div class="row"><div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $id . '</h5></div></div>';

                    // แสดงข้อมูลสลิป
                    if ($order->image) {
                        $info .= '<div class="alert alert-info">
                        <strong>สลิปการโอนเงิน:</strong> 
                        <button type="button" data-image="' . url('storage/' . $order->image) . '" class="btn btn-sm btn-primary viewSlip ms-2">
                            <i class="bx bx-image"></i> ดูสลิป
                        </button>
                    </div>';
                    }

                    foreach ($orderDetailsGrouped as $details) {
                        $this->generateOrderDetailHTML($details, $info);
                    }
                    $info .= '</div>';
                }
            }
        }

        echo $info;
    }
    private function generateOrderDetailHTML($details, &$info)
    {
        $menuName = optional($details->first()->menu)->name ?? 'ไม่พบชื่อเมนู';
        $orderOption = OrdersOption::where('order_detail_id', $details->first()->id)->get();

        foreach ($details as $detail) {
            $detailsText = [];
            if ($orderOption->isNotEmpty()) {
                foreach ($orderOption as $option) {
                    $optionName = MenuOption::find($option->option_id);
                    if ($optionName) {
                        $detailsText[] = $optionName->type;
                    }
                }
                $detailsText = implode(',', $detailsText);
            }

            $priceTotal = number_format($detail->price, 2);
            $info .= '<ul class="list-group mb-1 shadow-sm rounded">';
            $info .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
            $info .= '<div class="flex-grow-1">';
            $info .= '<div><span class="fw-bold">' . htmlspecialchars($menuName) . '</span></div>';

            if (!empty($detailsText)) {
                $info .= '<div class="small text-secondary mb-1 ps-2">+ ' . $detailsText . '</div>';
            }
            if (!empty($detail->remark)) {
                $info .= '<div class="small text-secondary mb-1 ps-2">+ หมายเหตุ : ' . $detail->remark . '</div>';
            }

            $info .= '</div>';
            $info .= '<div class="text-end d-flex flex-column align-items-end">';
            $info .= '<div class="mb-1">จำนวน: ' . $detail->quantity . '</div>';
            $info .= '<div>';
            $info .= '<button class="btn btn-sm btn-primary me-1">' . $priceTotal . ' บาท</button>';
            $info .= '</div>';
            $info .= '</div>';
            $info .= '</li>';
            $info .= '</ul>';
        }
    }
    public function printReceipt($id)
    {
        $config = Config::first();
        $pay = Pay::with('user')->find($id);
        $paygroup = PayGroup::where('pay_id', $id)->get();
        $order_id = array();

        foreach ($paygroup as $rs) {
            $order_id[] = $rs->order_id;
        }

        $item_id = '';
        if (empty($pay->table_id)) {
            $item_id = $order_id[0];
        }

        $order = OrdersDetails::whereIn('order_id', $order_id)
            ->with('menu', 'option.option')
            ->get();

        $users = null;
        if ($item_id) {
            $users = Orders::select('users.*', 'users_addresses.name as address_name', 'users_addresses.tel as address_tel')
                ->join('users', 'orders.users_id', '=', 'users.id')
                ->leftJoin('users_addresses', function ($join) {
                    $join->on('users.id', '=', 'users_addresses.users_id')
                        ->where('users_addresses.is_use', 1);
                })
                ->find($item_id);
        }

        $data = [
            'config' => $config,
            'pay' => $pay,
            'order' => $order,
            'users' => $users,
            'type' => 'normal'
        ];

        return view('print_web', ['jsonData' => json_encode($data)]);
    }
    public function printReceiptfull($id)
    {
        $get = $_GET;

        $config = Config::first();
        $pay = Pay::find($id);
        $paygroup = PayGroup::where('pay_id', $id)->get();
        $order_id = array();
        foreach ($paygroup as $rs) {
            $order_id[] = $rs->order_id;
        }
        $order = OrdersDetails::whereIn('order_id', $order_id)
            ->with('menu', 'option.option')
            ->get();

        $tax_full = [
            'name' => $get['name'] ?? '',
            'tel' => $get['tel'] ?? '',
            'tax_id' => $get['tax_id'] ?? '',
            'address' => $get['address'] ?? ''
        ];
        $data = [
            'config' => $config,
            'pay' => $pay,
            'order' => $order,
            'tax_full' => $tax_full,
            'type' => 'taxfull'
        ];
        return view('print_web', ['jsonData' => json_encode($data)]);
    }

    public function order_rider()
    {
        $data['function_key'] = 'order_rider';
        $data['rider'] = User::where('is_rider', 1)->get();
        $data['config'] = Config::first();
        return view('order_rider', $data);
    }

    public function ListOrderRider()
    {
        $data = [
            'status' => false,
            'message' => '',
            'data' => []
        ];
        $order = Orders::select('orders.*', 'users.name')
            ->join('users', 'orders.users_id', '=', 'users.id')
            ->whereNull('table_id')
            ->whereNotNull('users_id')
            ->whereNotNull('address_id')
            ->orderBy('created_at', 'desc')
            ->whereIn('status', [1, 2])
            ->get();

        if (count($order) > 0) {
            $info = [];
            foreach ($order as $rs) {
                $status = '';
                $pay = '';
                if ($rs->status == 1) {
                    $status = '<button class="btn btn-sm btn-primary">กำลังทำอาหาร</button>';
                }
                if ($rs->status == 2) {
                    $status = '<button class="btn btn-sm btn-success">กำลังจัดส่ง</button>';
                }
                if ($rs->status == 3) {
                    $status = '<button class="btn btn-sm btn-success">ชำระเงินเรียบร้อยแล้ว</button>';
                }

                if ($rs->is_pay == 0) {
                    $pay .= '<button data-id="' . $rs->id . '" data-total="' . $rs->total . '" type="button" class="btn btn-sm btn-outline-success modalPay m-1">ชำระเงิน</button>';
                }
                if ($rs->status == 1) {
                    $pay .= '<button data-id="' . $rs->id . '" data-total="' . $rs->total . '" type="button" class="btn btn-sm btn-outline-warning modalRider">จัดส่ง</button>';
                }
                if ($rs->is_pay == 1) {
                    $status .= '<button class="btn btn-sm btn-success m-1">ชำระเงินแล้ว</button>';
                }
                $flag_order = '<button class="btn btn-sm btn-warning">สั่งออนไลน์</button>';
                $action = '<button data-id="' . $rs->id . '" type="button" class="btn btn-sm btn-outline-primary modalShow m-1">รายละเอียด</button>' . $pay;
                $info[] = [
                    'flag_order' => $flag_order,
                    'name' => $rs->name,
                    'total' => $rs->total,
                    'remark' => $rs->remark,
                    'status' => $status,
                    'created' => $this->DateThai($rs->created_at),
                    'action' => $action
                ];
            }
            $data = [
                'data' => $info,
                'status' => true,
                'message' => 'success'
            ];
        }
        return response()->json($data);
    }

    public function listOrderDetailRider(Request $request)
    {
        $orderId = $request->input('id');
        $order = Orders::find($orderId);
        $info = '';

        if ($order) {
            $orderDetails = OrdersDetails::where('order_id', $orderId)->get()->groupBy('menu_id');
            $info .= '<div class="mb-3">';
            $info .= '<div class="row">';
            $info .= '<div class="col d-flex align-items-end"><h5 class="text-primary mb-2">เลขออเดอร์ #: ' . $orderId . '</h5></div>';
            $info .= '<div class="col-auto d-flex align-items-start">';

            if ($order->status != 2) {
                $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelOrderSwal m-1" data-id="' . $orderId . '">ยกเลิกออเดอร์</button>';
            }

            $info .= '</div></div>';

            foreach ($orderDetails as $details) {
                $menuName = optional($details->first()->menu)->name ?? 'ไม่พบชื่อเมนู';
                $orderOption = OrdersOption::where('order_detail_id', $details->first()->id)->get();

                $detailsText = [];
                if ($orderOption->isNotEmpty()) {
                    foreach ($orderOption as $option) {
                        $optionName = MenuOption::find($option->option_id);
                        $detailsText[] = $optionName->type;
                    }
                }

                foreach ($details as $detail) {
                    $priceTotal = number_format($detail->price, 2);
                    $info .= '<ul class="list-group mb-1 shadow-sm rounded">';
                    $info .= '<li class="list-group-item d-flex justify-content-between align-items-start">';
                    $info .= '<div class="flex-grow-1">';
                    $info .= '<div><span class="fw-bold">' . htmlspecialchars($menuName) . '</span></div>';

                    if (!empty($detailsText)) {
                        $info .= '<div class="small text-secondary mb-1 ps-2">+ ' . implode(',', $detailsText) . '</div>';
                    }
                    if (!empty($detail->remark)) {
                        $info .= '<div class="small text-secondary mb-1 ps-2">+ หมายเหตุ : ' . $detail->remark . '</div>';
                    }
                    $info .= '</div>';
                    $info .= '<div class="text-end d-flex flex-column align-items-end">';
                    $info .= '<div class="mb-1">จำนวน: ' . $detail->quantity . '</div>';
                    $info .= '<div>';
                    $info .= '<button class="btn btn-sm btn-primary me-1">' . $priceTotal . ' บาท</button>';
                    $info .= '<button href="javascript:void(0)" class="btn btn-sm btn-danger cancelMenuSwal" data-id="' . $detail->id . '">ยกเลิก</button>';
                    $info .= '</div>';
                    $info .= '</div>';
                    $info .= '</li>';
                    $info .= '</ul>';
                }
            }

            $info .= '</div>';
        }

        echo $info;
    }

    public function cancelOrder(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ลบข้อมูลไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $menu = Orders::where('id', $id)->first();
            if ($menu->delete()) {
                $order = OrdersDetails::where('order_id', $id)->delete();
                $data = [
                    'status' => true,
                    'message' => 'ลบข้อมูลเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    public function cancelMenu(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ลบข้อมูลไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $menu = OrdersDetails::where('id', $id)->first();
            $count = OrdersDetails::where('order_id', $menu->order_id)->count();
            $total = $menu->price * $menu->quantity;
            if ($menu->delete()) {
                if ($count == 1) {
                    $order = Orders::where('id', $menu->order_id)->delete();
                } else {
                    $order = Orders::where('id', $menu->order_id)->first();
                    $order->total = $order->total - $total;
                    $order->save();
                }
                $data = [
                    'status' => true,
                    'message' => 'ลบข้อมูลเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    public function updatestatus(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'อัพเดทสถานะไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $order = Orders::where('table_id', $id)->get();
            foreach ($order as $rs) {
                $rs->status = 2;
                $rs->save();
            }
            $data = [
                'status' => true,
                'message' => 'อัพเดทสถานะเรียบร้อยแล้ว',
            ];
        }
        return response()->json($data);
    }

    public function updatestatusOrder(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'อัพเดทสถานะไม่สำเร็จ',
        ];
        $id = $request->input('id');
        if ($id) {
            $order = Orders::find($id);
            $order->status = 2;
            if ($order->save()) {
                $data = [
                    'status' => true,
                    'message' => 'อัพเดทสถานะเรียบร้อยแล้ว',
                ];
            }
        }
        return response()->json($data);
    }

    public function ListOrderPeople()
{
    $data = [
        'status' => false,
        'message' => '',
        'data' => []
    ];
    
  
    $order = DB::table('orders as o')
        ->select(
            'o.users_id',
            'users.name'
        )
        ->join('users', 'o.users_id', '=', 'users.id')
        ->whereNull('o.table_id')
        ->whereIn('o.status', [3, 5])
        ->groupBy('o.users_id', 'users.name')
        ->get();

    if (count($order) > 0) {
        $info = [];
        foreach ($order as $rs) {
            // ยอดรวมทั้งหมด
            $total = Orders::select(DB::raw("SUM(total)as total"))
                ->whereIn('status', [3, 5]) 
                ->where('users_id', $rs->users_id)
                ->first();
                
            // เงินสดจาก 
            $moneyDay = Orders::select(DB::raw("SUM(orders.total)as total"))
                ->join('pay_groups', 'pay_groups.order_id', '=', 'orders.id')
                ->join('pays', 'pays.id', '=', 'pay_groups.pay_id')
                ->whereIn('orders.status', [3, 5]) 
                ->where('orders.users_id', $rs->users_id)
                ->where('pays.is_type', 0)
                ->first();
                
            // เงินโอน
            $transferFromPay = Orders::select(DB::raw("SUM(orders.total)as total"))
                ->join('pay_groups', 'pay_groups.order_id', '=', 'orders.id')
                ->join('pays', 'pays.id', '=', 'pay_groups.pay_id')
                ->whereIn('orders.status', [3, 5]) 
                ->where('orders.users_id', $rs->users_id)
                ->where('pays.is_type', 1)
                ->first();
                
            // เงินโอนจากสลิป 
            $transferFromSlip = Orders::select(DB::raw("SUM(total)as total"))
                ->where('status', 5)
                ->where('users_id', $rs->users_id)
                ->first();
                
            $transferDay = ($transferFromPay->total ?? 0) + ($transferFromSlip->total ?? 0);
            
            // จำนวนการจัดส่ง
            $delivery = Orders::whereIn('status', [3, 5]) 
                ->where('users_id', $rs->users_id)
                ->whereNull('table_id')
                ->count();
                
            $info[] = [
                'name' => $rs->name,
                'total' => $total->total,
                'moneyDay' => $moneyDay->total ?? 0,
                'transferDay' => $transferDay ?? '0',
                'delivery' => $delivery ?? '0',
            ];
        }
        $data = [
            'data' => $info,
            'status' => true,
            'message' => 'success'
        ];
    }
    return response()->json($data);
}
    public function paymentConfirm(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'ไม่สามารถยืนยันการชำระเงินได้',
        ];

        $orderId = $request->input('order_id');

        if ($orderId) {
            $order = Orders::find($orderId);

            if ($order) {
                $payment = new \App\Models\Payment();
                $payment->order_id = $order->id;
                $payment->amount = $order->total;
                $payment->payment_method = 'transfer';
                $payment->status = 'confirmed';
                $payment->confirmed_at = now();
                $payment->save();


                $order->status = 5;
                $order->save();

                $data = [
                    'status' => true,
                    'message' => 'ยืนยันการชำระเงินเรียบร้อยแล้ว',
                ];
            }
        }

        return response()->json($data);
    }
   
   
private function getCompletedOrdersTotal($period = 'day', $date = null)
{
    $query = Orders::select(DB::raw("SUM(total) as total"))
        ->whereIn('status', [3, 5]); 
        
    switch ($period) {
        case 'day':
            $query->whereDay('created_at', $date ?? date('d'));
            break;
        case 'month':
            $query->whereMonth('created_at', $date ?? date('m'));
            break;
        case 'year':
            $query->whereYear('created_at', $date ?? date('Y'));
            break;
    }
    
    return $query->first();
}
private function getPaymentTotalsByType($type, $period = 'day', $date = null)
{
    // เงินจาก Pay table
    $payQuery = Pay::select(DB::raw("SUM(total) as total"))
        ->where('is_type', $type);
        
    switch ($period) {
        case 'day':
            $payQuery->whereDay('created_at', $date ?? date('d'));
            break;
        case 'month':
            $payQuery->whereMonth('created_at', $date ?? date('m'));
            break;
        case 'year':
            $payQuery->whereYear('created_at', $date ?? date('Y'));
            break;
    }
    
    $payTotal = $payQuery->first()->total ?? 0;
    
    if ($type == 1) {
        $slipQuery = Orders::select(DB::raw("SUM(total) as total"))
            ->where('status', 5); 
            
        switch ($period) {
            case 'day':
                $slipQuery->whereDay('created_at', $date ?? date('d'));
                break;
            case 'month':
                $slipQuery->whereMonth('created_at', $date ?? date('m'));
                break;
            case 'year':
                $slipQuery->whereYear('created_at', $date ?? date('Y'));
                break;
        }
        
        $slipTotal = $slipQuery->first()->total ?? 0;
        $payTotal += $slipTotal;
    }
    
    return (object)['total' => $payTotal];

}
    public function getNotifications()
{
    $notifications = \DB::table('notifications')
        ->where('is_read', false)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
    
    return response()->json([
        'status' => true,
        'data' => $notifications,
        'count' => $notifications->count()
    ]);
}
public function markNotificationAsRead(Request $request)
{
    $id = $request->input('id');
    
    \DB::table('notifications')
        ->where('id', $id)
        ->update(['is_read' => true]);
    
    return response()->json(['status' => true]);
}

public function markAllNotificationsAsRead()
{
    \DB::table('notifications')
        ->where('is_read', false)
        ->update(['is_read' => true]);
    
    return response()->json(['status' => true]);
}
}