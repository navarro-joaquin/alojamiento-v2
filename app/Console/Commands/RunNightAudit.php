<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\User;
use App\Services\NightAuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lodging:night-audit {--branch=* : Limit the audit to these branch IDs}')]
#[Description('Close every business date that is due: charge the night to active stays and advance the date')]
class RunNightAudit extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NightAuditService $nightAuditService): int
    {
        $auditUser = $this->obtenerUsuarioDeAuditoria();

        if ($auditUser === null) {
            $this->error('Set LODGING_NIGHT_AUDIT_USER_EMAIL to an existing user to run the night audit.');

            return self::FAILURE;
        }

        $branches = Branch::query()
            ->where('is_active', true)
            ->when($this->option('branch'), fn ($query, array $branchIds) => $query->whereKey($branchIds))
            ->orderBy('id')
            ->get();

        foreach ($branches as $branch) {
            while ($nightAuditService->puedeCerrar($branch)) {
                $closedDate = $branch->current_business_date->toDateString();
                $chargedNights = $nightAuditService->cerrarFechaDeNegocio($branch, $auditUser);
                $branch->refresh();

                $this->info("{$branch->code}: closed {$closedDate}, {$chargedNights} nights charged.");
            }
        }

        return self::SUCCESS;
    }

    private function obtenerUsuarioDeAuditoria(): ?User
    {
        $email = config('lodging.night_audit_user_email');

        return $email ? User::query()->where('email', $email)->first() : null;
    }
}
