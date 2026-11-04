<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Initial expense categories. Running it again only adds the missing ones,
 * so categories renamed or deactivated by the super admin are kept.
 */
class ExpenseCategorySeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const CATEGORIES = [
        'Insumos de limpieza',
        'Lavandería',
        'Mantenimiento y reparaciones',
        'Servicios básicos',
        'Material de oficina',
        'Transporte',
        'Otros',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
