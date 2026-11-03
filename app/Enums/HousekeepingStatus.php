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
     * Whether a room in this housekeeping state can receive a new guest.
     */
    public function permiteAsignacion(): bool
    {
        return $this !== self::Dirty;
    }
}
