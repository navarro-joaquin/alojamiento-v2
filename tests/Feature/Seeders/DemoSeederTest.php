<?php

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a working demo branch and can run again', function () {
    $this->seed([RolesAndPermissionsSeeder::class, DemoSeeder::class, DemoSeeder::class]);

    $branch = Branch::query()->sole();
    $receptionist = User::query()->where('email', 'recepcion@alojamiento.test')->sole();

    expect(Room::count())->toBe(9)
        ->and($receptionist->tienePermisoEnSucursal(BranchPermission::CheckInStays, $branch->id))->toBeTrue()
        ->and($receptionist->sucursalesAsignadas()->modelKeys())->toBe([$branch->id]);
});
