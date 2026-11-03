<?php

namespace App\Enums;

enum DocumentType: string
{
    case Ci = 'ci';
    case Passport = 'passport';
    case ForeignId = 'foreign_id';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ci => 'Cédula de identidad',
            self::Passport => 'Pasaporte',
            self::ForeignId => 'Documento extranjero',
        };
    }
}
