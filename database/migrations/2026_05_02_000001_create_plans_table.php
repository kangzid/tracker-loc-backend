<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                // Starter, Pro, Custom
            $table->string('slug')->unique();       // starter, pro, custom
            $table->text('description')->nullable();
            $table->integer('price_monthly');       // Harga per bulan (IDR)
            $table->integer('max_employees');       // Batas karyawan (0 = unlimited)
            $table->integer('max_vehicles');        // Batas kendaraan (0 = unlimited)
            $table->json('features')->nullable();   // Daftar fitur dalam JSON
            $table->boolean('is_active')->default(true);
            $table->boolean('is_custom')->default(false); // custom/enterprise plan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
