<?php

use App\Models\Branch;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('closes every due business date of the active branches', function () {
    $auditUser = User::factory()->create();
    config(['lodging.night_audit_user_email' => $auditUser->email]);
    $branch = Branch::factory()->create(['current_business_date' => '2026-11-03', 'timezone' => 'America/La_Paz']);
    $stay = Stay::factory()->for(Room::factory()->for($branch))->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 04:30', 'America/La_Paz'));

    $this->artisan('lodging:night-audit')->assertSuccessful();

    expect($branch->fresh()->current_business_date->toDateString())->toBe('2026-11-05')
        ->and($stay->charges()->orderBy('service_date')->pluck('service_date')->map->toDateString()->all())
        ->toBe(['2026-11-03', '2026-11-04'])
        ->and($stay->charges()->first()->created_by)->toBe($auditUser->id);
});

it('skips inactive branches', function () {
    config(['lodging.night_audit_user_email' => User::factory()->create()->email]);
    $branch = Branch::factory()->inactiva()->create(['current_business_date' => '2026-11-03']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 04:30', 'America/La_Paz'));

    $this->artisan('lodging:night-audit')->assertSuccessful();

    expect($branch->fresh()->current_business_date->toDateString())->toBe('2026-11-03');
});

it('fails when the audit user is not configured', function () {
    config(['lodging.night_audit_user_email' => null]);

    $this->artisan('lodging:night-audit')->assertFailed();
});
