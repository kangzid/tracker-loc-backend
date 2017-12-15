<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
// Location tracking channels untuk real-time location updates (WebSocket)
// Format: location.{trackableType}.{trackableId}
// Contoh: location.employee.5 atau location.vehicle.3
Broadcast::channel('location.{trackableType}.{trackableId}', function ($user, $trackableType, $trackableId) {
    // Gunakan LocationChannel untuk authorization logic
    return app('App\Broadcasting\LocationChannel')->join($user, $trackableType, $trackableId);
});

Broadcast::channel('support.chat.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
// Tenant aggregated location channel (Optimasi 1 channel untuk semua armada/karyawan tenant)
Broadcast::channel('tenant.{tenantId}.locations', function ($user, $tenantId) {
    if ($user->isAdmin()) {
        return (int) $user->id === (int) $tenantId;
    }
    if ($user->isEmployee() && $user->employee) {
        return (int) $user->employee->admin_id === (int) $tenantId;
    }
    return false;
});
