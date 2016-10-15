<?php

namespace App\Broadcasting;

use App\Models\User;
use App\Models\Employee;

class LocationChannel
{
    /**
     * Authenticate the user's access to the channel.
     * Channel: location.{trackable_type}.{trackable_id}
     * Contoh: location.employee.5 atau location.vehicle.3
     */
    public function join(User $user, $trackableType, $trackableId)
    {
        // Admin hanya bisa subscribe ke location employee/vehicle milik tenant mereka
        if ($user->isAdmin()) {
            if ($trackableType === 'employee') {
                $employee = Employee::where('admin_id', $user->id)
                    ->where('id', $trackableId)
                    ->exists();
                return $employee;
            } elseif ($trackableType === 'vehicle') {
                // Vehicle juga harus di-check untuk admin_id
                return \App\Models\Vehicle::where('admin_id', $user->id)
                    ->where('id', $trackableId)
                    ->exists();
            }
        }

        // Employee hanya bisa subscribe ke location diri sendiri atau vehicle di tenant mereka
        if ($user->isEmployee()) {
            $employee = $user->employee;
            if (!$employee) {
                return false;
            }

            if ($trackableType === 'employee') {
                return $employee->id == $trackableId;
            } elseif ($trackableType === 'vehicle') {
                // Employee bisa subscribe ke vehicle history tenant mereka
                return \App\Models\Vehicle::where('admin_id', $employee->admin_id)
                    ->where('id', $trackableId)
                    ->exists();
            }
        }

        return false;
    }
}
