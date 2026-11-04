<?php

namespace App\Enums;

enum FixedAssetStatus: string
{
    case InUse = 'in_use';
    case InStorage = 'in_storage';
    case InRepair = 'in_repair';
    case Retired = 'retired';

    public function etiqueta(): string
    {
        return match ($this) {
            self::InUse => 'En uso',
            self::InStorage => 'En depósito',
            self::InRepair => 'En reparación',
            self::Retired => 'Dado de baja',
        };
    }

    /**
     * Color of the status badge (see the badge Blade component).
     */
    public function color(): string
    {
        return match ($this) {
            self::InUse => 'green',
            self::InStorage => 'gray',
            self::InRepair => 'amber',
            self::Retired => 'red',
        };
    }

    /**
     * Statuses an asset can be moved to; retiring has its own operation.
     *
     * @return list<self>
     */
    public static function operativos(): array
    {
        return [self::InUse, self::InStorage, self::InRepair];
    }
}
