<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * Thin wrapper over spatie/laravel-activitylog that always records the branch,
 * the request id and the IP address of the operation. Global operations (for
 * example on branches or user accounts) have no branch, and console
 * operations have no causer.
 */
class AuditService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function registrar(string $logName, string $event, Model $subject, ?User $causer, ?int $branchId, array $properties = []): void
    {
        $activity = activity($logName)
            ->performedOn($subject)
            ->event($event)
            ->withProperties([
                ...$properties,
                // Omitted rather than null: the generated activity_log.branch_id column cannot cast a JSON null.
                ...($branchId === null ? [] : ['branch_id' => $branchId]),
                'request_id' => Context::get('request_id'),
                'ip_address' => Context::get('ip_address'),
            ]);

        if ($causer !== null) {
            $activity->causedBy($causer);
        }

        $activity->log($event);
    }
}
