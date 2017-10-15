<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete()->after('user_id');
            $table->integer('plan_price')->default(0)->after('plan');   // Harga saat bayar
            $table->string('midtrans_order_id')->nullable()->unique()->after('plan_price');
            $table->string('payment_status')->default('free')->after('midtrans_order_id'); // free|pending|settlement|expire
            $table->string('snap_token')->nullable()->after('payment_status');
            $table->string('voucher_code')->nullable()->after('snap_token');
            $table->integer('discount_applied')->default(0)->after('voucher_code'); // persen
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['plan_id', 'plan_price', 'midtrans_order_id', 'payment_status', 'snap_token', 'voucher_code', 'discount_applied']);
        });
    }
};
