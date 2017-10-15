<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_notification_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('notification_id')->constrained('notifications')->onDelete('cascade');
            $table->datetime('read_at')->nullable();
            $table->datetime('deleted_by_admin_at')->nullable();
            $table->timestamps();

            $table->unique(['admin_id', 'notification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_status');
    }
};
