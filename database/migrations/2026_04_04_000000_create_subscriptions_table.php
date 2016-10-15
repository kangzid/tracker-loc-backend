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

            // FK ke user dengan role=admin yang mendaftar
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Plan type
            $table->enum('plan', ['trial', 'monthly', 'yearly'])->default('trial');

            // Kuota maksimum
            $table->unsignedInteger('max_employees')->default(1);
            $table->unsignedInteger('max_vehicles')->default(1);

            // Info perusahaan/tenant (denormalisasi ringan agar tidak perlu tabel tenants dulu)
            $table->string('company_name')->nullable();
            $table->string('contact_phone')->nullable();

            // Waktu berlaku
            $table->timestamp('started_at');
            $table->timestamp('expired_at');

            // Status langganan
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
