<?php

namespace App\Events;

use App\Models\SupportMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(SupportMessage $message)
    {
        $this->message = $message->load(['sender:id,name,email']);
    }

    public function broadcastOn(): array
    {
        // Broadcast to the receiver's private channel
        return [
            new PrivateChannel('support.chat.' . $this->message->receiver_id),
            new PrivateChannel('support.chat.' . $this->message->sender_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }
}
