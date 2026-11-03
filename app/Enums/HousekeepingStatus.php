<?php

namespace App\Enums;

enum HousekeepingStatus: string
{
    case Clean = 'clean';
    case Dirty = 'dirty';
    case Inspected = 'inspected';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Clean => 'Limpia',
            self::Dirty => 'Sucia',
            self::Inspected => 'Inspeccionada',
        };
    }

    /**
     * Color of the status badge (see the badge Blade component).
     */
    public function color(): string
    {
        return match ($this) {
            self::Clean => 'gray',
            self::Dirty => 'amber',
            self::Inspected => 'green',
        };
    }

    /**
     * Whether a room in this housekeeping state can receive a new guest.
     */
    public function permiteAsignacion(): bool
    {
        return $this !== self::Dirty;
    }
}
