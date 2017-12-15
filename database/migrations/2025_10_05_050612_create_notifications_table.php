<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // Superadmin who created it
            
            $table->string('title', 150);
            $table->text('message');
            $table->string('image_url')->nullable();
            
            $table->string('type')->default('info');
            $table->string('recipient_type')->default('personal'); // personal, broadcast
            $table->json('admin_ids')->nullable(); // For specific broadcast recipients
            
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_broadcast')->default(false);
            $table->enum('broadcast_type', ['all', 'admins', 'employees'])->nullable();
            $table->integer('views_count')->default(0);
            $table->timestamps();

            // PERFORMANCE INDEXES
            $table->index('admin_id', 'idx_notifications_admin_id');
            $table->index('is_read', 'idx_notifications_is_read');
            $table->index(['employee_id', 'is_read'], 'idx_notifications_employee_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};