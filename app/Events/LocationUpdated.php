<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Employee;
use App\Models\Vehicle;

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
    public $tenantId;

    /**
     * Create a new event instance.
     */
    public function __construct($trackableType, $trackableId, $latitude, $longitude, $speed = null, $accuracy = null, $recordedAt = null, $entityName = null, $tenantId = null)
    {
        $this->trackableType = $trackableType;
        $this->trackableId = $trackableId;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->speed = $speed;
        $this->accuracy = $accuracy;
        $this->recordedAt = $recordedAt ?? now();
        $this->entityName = $entityName;
        
        // Ensure tenantId is resolved
        if ($tenantId) {
            $this->tenantId = (int)$tenantId;
        } else {
            if ($trackableType === 'employee') {
                $emp = Employee::find($trackableId);
                $this->tenantId = $emp ? (int)$emp->admin_id : null;
            } elseif ($trackableType === 'vehicle') {
                $veh = Vehicle::find($trackableId);
                $this->tenantId = $veh ? (int)$veh->admin_id : null;
            }
        }
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("location.{$this->trackableType}.{$this->trackableId}"),
        ];

        // Broadcast to aggregated tenant channel for high-performance single-subscription monitoring
        if ($this->tenantId) {
            $channels[] = new PrivateChannel("tenant.{$this->tenantId}.locations");
        }

        return $channels;
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
            'tenant_id' => $this->tenantId,
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
