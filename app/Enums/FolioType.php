<?php

namespace App\Enums;

enum FolioType: string
{
    case Stay = 'stay';
    case Counter = 'counter';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Stay => 'Estadía',
            self::Counter => 'Mostrador',
        };
    }
}
