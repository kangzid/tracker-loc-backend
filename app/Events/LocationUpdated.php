<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $trackableType;  // 'employee' atau 'vehicle'
    public $trackableId;
    public $latitude;
    public $longitude;
    public $speed;
    public $accuracy;
    public $recordedAt;
    public $entityName;

    /**
     * Create a new event instance.
     */
    public function __construct($trackableType, $trackableId, $latitude, $longitude, $speed = null, $accuracy = null, $recordedAt = null, $entityName = null)
    {
        $this->trackableType = $trackableType;
        $this->trackableId = $trackableId;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->speed = $speed;
        $this->accuracy = $accuracy;
        $this->recordedAt = $recordedAt ?? now();
        $this->entityName = $entityName;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("location.{$this->trackableType}.{$this->trackableId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'trackable_type' => $this->trackableType,
            'trackable_id' => $this->trackableId,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'speed' => $this->speed ? (float) $this->speed : null,
            'accuracy' => $this->accuracy ? (float) $this->accuracy : null,
            'recorded_at' => $this->recordedAt->toIso8601String(),
            'entity_name' => $this->entityName,
        ];
    }

    /**
     * Nama event yang akan diterima client
     */
    public function broadcastAs(): string
    {
        return 'location.updated';
    }
}
