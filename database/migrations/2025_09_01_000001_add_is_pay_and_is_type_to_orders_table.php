<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'is_pay')) {
                $table->tinyInteger('is_pay')->default(0)->after('status');
            }
            if (!Schema::hasColumn('orders', 'is_type')) {
                $table->tinyInteger('is_type')->default(0)->after('is_pay');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'is_type')) {
                $table->dropColumn('is_type');
            }
            if (Schema::hasColumn('orders', 'is_pay')) {
                $table->dropColumn('is_pay');
            }
        });
    }
};

