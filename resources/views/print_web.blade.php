<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qz-tray/2.2.4/qz-tray.min.js" integrity="sha512-W1YQ2YsmEpRhtXZW8DqRLVQjaxAg/P6MqxsVXni4eWh05rq6ArlTc95xJMu38xpv8uKXu95syEHCqB6f+GO6wg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }
            body {
                margin: 0;
                padding: 0;
                font-family: 'Courier New', monospace;
                font-size: 14px;
                line-height: 1.4;
            }
            .print-wrapper {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }
        
        .print-wrapper {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .shop-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .order-info {
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        
        .order-info div {
            text-align: center;
            margin: 2px 0;
        }
        
        .order-item {
            margin-bottom: 10px;
            padding: 3px 0;
        }
        
        .item-name {
            font-weight: bold;
            text-align: center;
            font-size: 16px;
        }
        
        .item-option {
            font-size: 14px;
            margin-left: 10px;
            color: #666;
            text-align: center;
        }
        
        .item-remark {
            font-size: 14px;
            margin-left: 10px;
            color: #666;
            font-style: italic;
            text-align: center;
        }
        
        .quantity-price {
            text-align: center;
            margin-top: 3px;
            white-space: nowrap;
            font-size: 15px;
        }
        
        .total-section {
            border-top: 1px dashed #000;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .total-line {
            text-align: center;
            margin: 5px 0;
            font-size: 16px;
            white-space: nowrap;
        }
        
        .total-line.main-total {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            font-size: 12px;
        }
        
        /* ใบเสร็จแบบตาราง */
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 15px;
        }
        
        .receipt-header h2 {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: bold;
        }
        
        .receipt-info {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 1px solid #000;
        }
        
        .receipt-table th,
        .receipt-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        
        .receipt-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        
        .item-header {
            width: 50%;
        }
        
        .qty-header {
            width: 20%;
            text-align: center;
        }
        
        .price-header {
            width: 30%;
            text-align: center;
        }
        
        .item-col {
            vertical-align: top;
        }
        
        .menu-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .menu-option {
            font-size: 12px;
            color: #666;
            margin: 2px 0;
        }
        
        .menu-remark {
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 3px;
        }
        
        .qty-col {
            text-align: center;
            vertical-align: top;
            font-weight: bold;
        }
        
        .price-col {
            text-align: right;
            vertical-align: top;
            font-weight: bold;
        }
        
        .receipt-total {
            border: 1px solid #000;
            border-top: 2px solid #000;
            background-color: #f5f5f5;
            padding: 10px;
            margin-top: 10px;
        }
        
        .total-line {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
        }
        
        /* สำหรับออเดอร์ในครัว */
        .cook-header {
            background-color: #f0f0f0;
            padding: 5px;
            margin-bottom: 10px;
            text-align: center;
            font-weight: bold;
        }
        
        .no-print {
            display: block;
            text-align: center;
            padding: 20px;
        }
        
        /* สำหรับตารางในออเดอร์ */
        .order-table {
            width: 100%;
            margin: 10px 0;
        }
        
        .order-table td {
            padding: 5px;
            vertical-align: top;
            font-size: 14px;
        }
        
        .order-table .item-col {
            text-align: left;
            width: 60%;
        }
        
        .order-table .qty-col {
            text-align: center;
            width: 15%;
        }
        
        .order-table .price-col {
            text-align: right;
            width: 25%;
            white-space: nowrap;
        }
        
        .order-table .total-row td {
            border-top: 2px solid #000;
            padding-top: 10px;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
        }

        /* สำหรับแสดงผลใน iframe */
        .preview-mode {
            background-color: #f8f9fa !important;
            padding: 20px;
        }

        .preview-mode .print-wrapper {
            margin: 0 auto;
            background-color: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 20px;
            max-width: 600px;
        }

        .preview-mode .no-print {
            display: block !important;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .preview-mode .no-print button {
            margin: 0 5px;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .preview-mode .no-print button:first-child {
            background-color: #007bff;
            color: white;
        }

        .preview-mode .no-print button:last-child {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="handlePrint()" style="padding: 10px 20px; font-size: 14px;">พิมพ์</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; margin-left: 10px;">ปิด</button>
    </div>

    <div class="print-wrapper">
        <div id="print-content">
            <!-- เนื้อหาจะถูกสร้างโดย JavaScript -->
        </div>
    </div>

    <script>
        const jsonData = {!! $jsonData !!};
        const data = jsonData;
        
        // ตรวจสอบว่าอยู่ใน iframe หรือไม่
        const isInIframe = window.self !== window.top;
        
        // ตรวจสอบว่ามาจาก mobile app หรือไม่
        function isMobileApp() {
            // ตรวจสอบจาก user agent หรือ URL parameters ที่ส่งมา
            const urlParams = new URLSearchParams(window.location.search);
            const channel = urlParams.get('channel');
            const device = urlParams.get('device');
            
            return channel === 'pos-app' && (device === 'android' || device === 'ios');
        }
        
        // ฟังก์ชันสำหรับ print ผ่าน jsbridge
        function printViaJSBridge(data) {
            const printData = {
                type: 'print_receipt',
                data: data
            };
            
            // ส่งข้อมูลไปยัง mobile app ผ่าน jsbridge
            if (window.Android && window.Android.printReceipt) {
                // Android
                window.Android.printReceipt(JSON.stringify(printData));
            } else if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.printReceipt) {
                // iOS
                window.webkit.messageHandlers.printReceipt.postMessage(printData);
            } else {
                // Fallback - ถ้าไม่มี jsbridge ให้ใช้ window.print
                console.warn('JSBridge not available, falling back to window.print');
                window.print();
            }
        }
        // ตั้งค่าเริ่มต้นสำหรับ QZ Tray (สำหรับการพิมพ์อัตโนมัติบนเว็บ)
        if (window.qz) {
            qz.security.setCertificatePromise(function(resolve, reject) { resolve(); });
            qz.security.setSignaturePromise(function(toSign) {
                return function(resolve, reject) { resolve(); };
            });
        }

        function printViaQZ(html) {
            return qz.websocket.connect()
                .then(() => qz.printers.getDefault())
                .then(printer => {
                    const cfg = qz.configs.create(printer);
                    return qz.print(cfg, [{ type: 'html', format: 'plain', data: html }]);
                });
        }
        
        if (isInIframe) {
            document.body.classList.add('preview-mode');
        }
        
        function formatPrice(price) {
            return parseFloat(price).toFixed(2);
        }
        
        function formatDateTime(dateTime) {
            const date = new Date(dateTime);
            return date.toLocaleDateString('th-TH') + ' ' + date.toLocaleTimeString('th-TH');
        }
        
        function groupOrderItems(orderItems) {
            const grouped = {};
            
            orderItems.forEach(item => {
                let key = `${item.menu ? item.menu.name : 'เมนู'}_${item.price}`;
                
                if (item.option && item.option.length > 0) {
                    const optionKeys = item.option
                        .map(opt => opt.option ? opt.option.type : '')
                        .sort()
                        .join(',');
                    key += `_${optionKeys}`;
                }
                
                if (item.remark) {
                    key += `_${item.remark}`;
                }
                
                if (grouped[key]) {
                    grouped[key].quantity = parseInt(grouped[key].quantity) + parseInt(item.quantity);
                } else {
                    grouped[key] = {
                        ...item,
                        quantity: parseInt(item.quantity)
                    };
                }
            });
            
            return Object.values(grouped);
        }
        
        function renderContent() {
            const container = document.getElementById('print-content');
            let html = '';
            
            console.log('Data type:', data.type);
            console.log('Data:', data);
            
            if (data.type === 'normal') {
                html = renderNormalReceipt();
            } else if (data.type === 'taxfull') {
                html = renderTaxReceipt();
            } else if (data.type === 'order_admin') {
                html = renderOrderAdmin();
            } else if (data.type === 'order_cook') {
                html = renderOrderCook();
            } else {
                // Default เป็น normal receipt
                html = renderNormalReceipt();
            }
            
            container.innerHTML = html;
        }
        
        function renderNormalReceipt() {
            let html = `
                <div class="receipt-header">
                    <h2>${data.config.name || 'ร้านค้าออนไลน์'}</h2>
                    <div class="receipt-info">
                        <div>เลขที่ใบเสร็จ #${data.pay.payment_number}</div>
                        <div>วันที่: ${formatDateTime(data.pay.created_at)}</div>
                    </div>
                </div>
                
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th class="item-header">เมนู</th>
                            <th class="qty-header">จำนวน</th>
                            <th class="price-header">ราคา</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            let total = 0;
            if (data.order && data.order.length > 0) {
                const groupedItems = groupOrderItems(data.order);
                groupedItems.forEach(item => {
                    const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                    total += itemTotal;
                    
                    html += `
                        <tr>
                            <td class="item-col">
                                <div class="menu-name">${item.menu ? item.menu.name : 'เมนู'}</div>
                    `;
                    
                    if (item.option && item.option.length > 0) {
                        item.option.forEach(opt => {
                            if (opt.option) {
                                html += `<div class="menu-option">+ ${opt.option.type}</div>`;
                            }
                        });
                    }
                    
                    if (item.remark) {
                        html += `<div class="menu-remark">หมายเหตุ: ${item.remark}</div>`;
                    }
                    
                    html += `
                            </td>
                            <td class="qty-col">${item.quantity}</td>
                            <td class="price-col">${formatPrice(itemTotal)} ฿</td>
                        </tr>
                    `;
                });
            }
            
            html += `
                    </tbody>
                </table>
                
                <div class="receipt-total">
                    <div class="total-line">
                        <strong>Total: ${formatPrice(data.pay.total)} ฿</strong>
                    </div>
                </div>
            `;
            
            return html;
        }
        
        function renderTaxReceipt() {
            let html = `
                <div class="header">
                    <div class="shop-name">${data.config.name || 'ร้านอาหาร'}</div>
                    <div>ใบกำกับภาษี</div>
                </div>
                
                <div class="order-info">
                    <div>เลขที่: ${data.pay.payment_number}</div>
                    <div>โต๊ะ: ${data.pay.table_id || '-'}</div>
                    <div>วันที่: ${formatDateTime(data.pay.created_at)}</div>
            `;
            
            if (data.tax_full) {
                html += `
                    <div style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 5px;">
                        <div>ชื่อ: ${data.tax_full.name}</div>
                        <div>เบอร์โทร: ${data.tax_full.tel}</div>
                        <div>เลขภาษี: ${data.tax_full.tax_id}</div>
                        <div>ที่อยู่: ${data.tax_full.address}</div>
                    </div>
                `;
            }
            
            html += `</div><div class="receipt-section">`;
            
            let total = 0;
            if (data.order && data.order.length > 0) {
                const groupedItems = groupOrderItems(data.order);
                groupedItems.forEach(item => {
                    const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                    total += itemTotal;
                    
                    html += `
                        <div class="order-item">
                            <div class="item-name">${item.menu ? item.menu.name : 'เมนู'}</div>
                    `;
                    
                    if (item.option && item.option.length > 0) {
                        item.option.forEach(opt => {
                            if (opt.option) {
                                html += `<div class="item-option">+ ${opt.option.type}</div>`;
                            }
                        });
                    }
                    
                    if (item.remark) {
                        html += `<div class="item-remark">หมายเหตุ: ${item.remark}</div>`;
                    }
                    
                    html += `
                            <div class="quantity-price">
                                ${item.quantity} x ${formatPrice(item.price)} = ${formatPrice(itemTotal)}฿
                            </div>
                        </div>
                    `;
                });
            }
            
            const vat = total * 0.07;
            const totalWithVat = total + vat;
            
            html += `
                </div>
                
                <div class="total-section">
                    <div class="total-line">
                        ยอดรวม: ${formatPrice(total)}฿
                    </div>
                    <div class="total-line">
                        ภาษีมูลค่าเพิ่ม 7%: ${formatPrice(vat)}฿
                    </div>
                    <div class="total-line main-total">
                        รวมทั้งสิ้น: ${formatPrice(totalWithVat)}฿
                    </div>
                </div>
                
                <div class="footer">
                    <div>ใบกำกับภาษีอย่างย่อ</div>
                    <div>ขอบคุณที่ใช้บริการ</div>
                </div>
            `;
            
            return html;
        }
        
        function renderOrderAdmin() {
            let html = `
                <div class="header">
                    <div class="shop-name">${data.config.name || 'ร้านค้าออนไลน์'}</div>
                    <div>เลขที่โต๊ะ: #${data.table ? data.table.table_number : data.table_id}</div>
                    <div>วันที่: ${formatDateTime(new Date())}</div>
                </div>
                
                <table class="order-table">
                    <tbody>
            `;
            
            if (data.order_details && data.order_details.length > 0) {
                const groupedItems = groupOrderItems(data.order_details);
                groupedItems.forEach(item => {
                    html += `
                        <tr>
                            <td class="item-col">
                                <div style="font-weight: bold;">${item.menu ? item.menu.name : 'เมนู'}</div>
                    `;
                    
                    if (item.option && item.option.length > 0) {
                        item.option.forEach(opt => {
                            if (opt.option) {
                                html += `<div style="font-size: 14px; color: #666;">+ ${opt.option.type}</div>`;
                            }
                        });
                    }
                    
                    if (item.remark) {
                        html += `<div style="font-size: 14px; color: #666;">หมายเหตุ: ${item.remark}</div>`;
                    }
                    
                    html += `
                            </td>
                            <td class="qty-col">${item.quantity}</td>
                            <td class="price-col">${formatPrice(item.price)}฿</td>
                        </tr>
                    `;
                });
            }
            
            // คำนวณราคารวม
            let total = 0;
            if (data.order_details && data.order_details.length > 0) {
                const groupedItems = groupOrderItems(data.order_details);
                total = groupedItems.reduce((sum, item) => {
                    return sum + (parseFloat(item.price) * parseInt(item.quantity));
                }, 0);
            }
            
            html += `
                        <tr class="total-row">
                            <td colspan="3">Total: ${formatPrice(total)}฿</td>
                        </tr>
                    </tbody>
                </table>
            `;
            
            return html;
        }
        
        function renderOrderCook() {
            const isDelivery = !data.table && (data.table_id === null || data.table_id === undefined);
            const orderLabel = isDelivery
                ? `เลขออเดอร์: #${data.orders && data.orders.length > 0 ? data.orders[0].id : ''}`
                : `เลขที่โต๊ะ: #${data.table ? data.table.table_number : data.table_id}`;
            let html = `
                <div class="header">
                    <div class="shop-name">${data.config.name || 'ร้านค้าออนไลน์'}</div>
                    <div style="background-color: #ffeb3b; padding: 5px; margin: 10px 0; border-radius: 3px;">
                        <strong>สำหรับครัว - ${isDelivery ? 'ออเดอร์เดลิเวอรี่' : 'ออเดอร์ในร้าน'}</strong>
                    </div>
                    <div>${orderLabel}</div>
                    <div>วันที่: ${formatDateTime(new Date())}</div>
                </div>
                
                <div style="margin: 15px 0; font-size: 16px; font-weight: bold; text-align: center; border: 2px solid #000; padding: 10px;">
                    รายการอาหารที่ต้องทำ
                </div>
            `;
            
            if (data.order_details && data.order_details.length > 0) {
                const groupedItems = groupOrderItems(data.order_details);
                groupedItems.forEach((item, index) => {
                    html += `
                        <div class="order-item" style="border: 1px solid #ddd; margin: 10px 0; padding: 10px; background-color: #f9f9f9;">
                            <div style="font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 5px;">
                                ${index + 1}. ${item.menu ? item.menu.name : 'เมนู'}
                            </div>
                            <div style="text-align: center; font-size: 20px; font-weight: bold; color: #e91e63; margin: 5px 0;">
                                จำนวน: ${item.quantity} ${item.quantity > 1 ? 'จาน' : 'จาน'}
                            </div>
                    `;
                    
                    if (item.option && item.option.length > 0) {
                        html += `<div style="margin: 5px 0; font-weight: bold;">ตัวเลือก:</div>`;
                        item.option.forEach(opt => {
                            if (opt.option) {
                                html += `<div style="font-size: 16px; color: #666; margin: 2px 0; text-align: center;">• ${opt.option.type}</div>`;
                            }
                        });
                    }
                    
                    if (item.remark) {
                        html += `
                            <div style="margin: 10px 0; padding: 5px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px;">
                                <strong>หมายเหตุ:</strong> ${item.remark}
                            </div>
                        `;
                    }
                    
                    html += `</div>`;
                });
            }
            
            html += `
                <div style="margin-top: 20px; text-align: center; font-size: 14px; color: #666;">
                    --- สิ้นสุดรายการ ---
                </div>
            `;
            
            return html;
        }
        
        // ฟังก์ชันจัดการการพิมพ์
        function handlePrint() {
            if (isMobileApp()) {
                console.log('Mobile app detected, using JSBridge');
                printViaJSBridge(data);
                return Promise.resolve();
            } else if (window.qz) {
                console.log('QZ Tray detected, printing silently');
                const html = `<html><head><meta charset="UTF-8"></head><body>${document.querySelector('.print-wrapper').innerHTML}</body></html>`;
                return printViaQZ(html).catch(err => {
                    console.error('QZ Tray error', err);
                    window.print();
                });
            } else {
                console.log('Web browser detected, using window.print');
                window.print();
                return Promise.resolve();
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, rendering content...');
            renderContent();
            
            // Auto print handling
            if (!isInIframe) {
                if (data.type === 'order_cook') {
                    console.log('Auto print triggered for kitchen');
                    setTimeout(function() {
                        handlePrint().finally(function() {
                            setTimeout(function() {
                                if (window.opener) {
                                    window.opener.postMessage('cook-print-done', '*');
                                    window.close();
                                } else {
                                    window.location.href = '/admin/order';
                                }
                            }, 1000);
                        });
                    }, 3000); // show for 3 seconds before printing
                } else if (data.type === 'order_admin') {
                    console.log('Auto print triggered for admin');
                    setTimeout(function() {
                        handlePrint();
                    }, 1000);
                }
            }
        });
        
        // เพิ่ม error handling
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
            document.getElementById('print-content').innerHTML = '<div style="text-align: center; color: red; padding: 20px;">เกิดข้อผิดพลาดในการโหลดข้อมูล<br>กรุณาลองใหม่อีกครั้ง</div>';
        });
    </script>
</body>
</html>