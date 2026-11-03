<?php

namespace Database\Seeders;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development until the catalog screens exist: one
 * branch with rooms, rates and a register, and one user per role. Every
 * demo user has the password "password". Safe to run again.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->firstOrCreate(['code' => 'CEN'], [
            'name' => 'Sucursal Centro',
            'address' => 'Av. 16 de Julio 1234',
            'timezone' => 'America/La_Paz',
            'current_business_date' => now('America/La_Paz')->toDateString(),
        ]);

        CashRegister::query()->firstOrCreate(['branch_id' => $branch->id, 'name' => 'Caja recepción']);

        $this->crearHabitaciones($branch);
        $this->crearUsuarios($branch);
    }

    private function crearHabitaciones(Branch $branch): void
    {
        $roomTypes = [
            ['name' => 'Simple', 'default_capacity' => 1, 'amount' => '180.00', 'rooms' => ['101', '102', '103']],
            ['name' => 'Doble', 'default_capacity' => 2, 'amount' => '250.00', 'rooms' => ['201', '202', '203', '204']],
            ['name' => 'Familiar', 'default_capacity' => 4, 'amount' => '380.00', 'rooms' => ['301', '302']],
        ];

        foreach ($roomTypes as $definition) {
            $roomType = RoomType::query()->firstOrCreate(['name' => $definition['name']], [
                'default_capacity' => $definition['default_capacity'],
            ]);

            RoomRate::query()->firstOrCreate(['branch_id' => $branch->id, 'room_type_id' => $roomType->id, 'name' => 'Tarifa estándar'], [
                'amount' => $definition['amount'],
                'valid_from' => '2026-01-01',
            ]);

            foreach ($definition['rooms'] as $roomNumber) {
                Room::query()->firstOrCreate(['branch_id' => $branch->id, 'room_number' => $roomNumber], [
                    'room_type_id' => $roomType->id,
                    'floor' => $roomNumber[0],
                    'capacity' => $definition['default_capacity'],
                ]);
            }
        }
    }

    private function crearUsuarios(Branch $branch): void
    {
        $users = [
            'admin@alojamiento.test' => ['Administración', BranchRole::Admin],
            'supervisor@alojamiento.test' => ['Supervisión', BranchRole::Supervisor],
            'recepcion@alojamiento.test' => ['Recepción', BranchRole::Receptionist],
            'limpieza@alojamiento.test' => ['Limpieza', BranchRole::Housekeeping],
        ];

        foreach ($users as $email => [$name, $role]) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
            ]);

            $user->asignarRolEnSucursal($role, $branch->id);
        }

        User::query()->firstOrCreate(['email' => 'superadmin@alojamiento.test'], [
            'name' => 'Superadministración',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->forceFill(['is_super_admin' => true])->save();

        User::query()->firstOrCreate(['email' => 'auditoria@alojamiento.test'], [
            'name' => 'Auditoría nocturna',
            'password' => str()->random(40),
        ]);
    }
}
