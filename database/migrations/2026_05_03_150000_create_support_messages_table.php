<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $blueprint->text('message');
            $blueprint->boolean('is_read')->default(false);
            $blueprint->timestamp('sender_deleted_at')->nullable();
            $blueprint->timestamp('receiver_deleted_at')->nullable();
            $blueprint->timestamps();
            
            $blueprint->index(['sender_id', 'receiver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
