<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('grants the super admin flag and records it', function () {
    $user = User::factory()->create(['email' => 'jefa@alojamiento.test']);

    $this->artisan('lodging:super-admin', ['email' => 'jefa@alojamiento.test'])->assertSuccessful();

    expect($user->fresh()->is_super_admin)->toBeTrue();
    $this->assertDatabaseHas('activity_log', ['event' => 'user.super_admin_granted', 'subject_id' => $user->id, 'causer_id' => null]);
});

it('revokes the super admin flag', function () {
    $user = User::factory()->superAdministrador()->create();

    $this->artisan('lodging:super-admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();

    expect($user->fresh()->is_super_admin)->toBeFalse();
});

it('fails for an unknown email', function () {
    $this->artisan('lodging:super-admin', ['email' => 'nadie@alojamiento.test'])->assertFailed();
});

it('cannot be set through mass assignment', function () {
    $user = User::factory()->create();

    $user->update(['is_super_admin' => true]);

    expect($user->fresh()->is_super_admin)->toBeFalse();
});
