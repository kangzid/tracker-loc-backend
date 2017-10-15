<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('employee_id')->constrained('users')->onDelete('cascade');
            $table->string('image_url')->nullable()->after('message');
            $table->enum('recipient_type', ['task_completion', 'broadcast'])->default('task_completion')->after('type');
            $table->json('admin_ids')->nullable()->comment('JSON array of admin IDs for broadcast notifications')->after('recipient_type');
            $table->softDeletes()->after('is_read');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'image_url', 'recipient_type', 'admin_ids', 'deleted_at']);
        });
    }
};
