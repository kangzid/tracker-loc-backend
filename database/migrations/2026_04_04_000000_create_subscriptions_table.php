<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->onDelete('set null');
            $table->foreignId('pending_plan_id')->nullable()->constrained('plans')->onDelete('set null');

            $table->unsignedInteger('max_employees')->default(1);
            $table->unsignedInteger('max_vehicles')->default(1);
            $table->integer('ai_credits_limit')->default(0);
            $table->integer('ai_credits_used')->default(0);

            $table->string('company_name')->nullable();
            $table->string('contact_phone', 20)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            // Payment Details (Midtrans)
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending', 'unpaid', 'failed'])->default('pending');
            $table->string('midtrans_order_id')->nullable()->unique();
            $table->integer('plan_price')->default(0);
            $table->integer('discount_applied')->default(0);
            $table->string('voucher_code')->nullable();
            
            $table->string('snap_token')->nullable();
            $table->string('payment_url')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->timestamp('last_payment_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
