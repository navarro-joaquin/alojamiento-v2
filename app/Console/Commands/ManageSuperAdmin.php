<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The only way to grant or revoke the super admin flag: it is deliberately
 * not exposed in any screen.
 */
#[Signature('lodging:super-admin {email : Email of the user} {--revoke : Remove the flag instead of granting it}')]
#[Description('Grant or revoke the super admin flag of a user')]
class ManageSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AuditService $auditService): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("No user has the email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $isSuperAdmin = ! $this->option('revoke');
        $user->forceFill(['is_super_admin' => $isSuperAdmin])->save();

        $auditService->registrar('security', $isSuperAdmin ? 'user.super_admin_granted' : 'user.super_admin_revoked', $user, null, null);

        $this->info($isSuperAdmin ? "{$user->email} is now a super admin." : "{$user->email} is no longer a super admin.");

        return self::SUCCESS;
    }
}
