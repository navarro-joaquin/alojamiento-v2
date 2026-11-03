<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * Thin wrapper over spatie/laravel-activitylog that always records the branch,
 * the request id and the IP address of the operation.
 */
class AuditService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function registrar(string $logName, string $event, Model $subject, User $causer, int $branchId, array $properties = []): void
    {
        activity($logName)
            ->performedOn($subject)
            ->causedBy($causer)
            ->event($event)
            ->withProperties([
                ...$properties,
                'branch_id' => $branchId,
                'request_id' => Context::get('request_id'),
                'ip_address' => Context::get('ip_address'),
            ])
            ->log($event);
    }
}
