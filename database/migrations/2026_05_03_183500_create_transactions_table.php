<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            
            $table->string('midtrans_order_id')->unique();
            $table->string('snap_token')->nullable();
            
            $table->string('company_name')->nullable();
            $table->string('plan_name')->nullable();
            
            $table->integer('original_price')->default(0);
            $table->integer('discount_applied')->default(0); // percentage
            $table->integer('final_price')->default(0);
            
            $table->string('voucher_code')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('payment_type')->nullable();
            
            $table->json('payload')->nullable(); // full midtrans response
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
