<?php

namespace App\Enum;

enum PunchType: string
{
    case CHECK_IN = 'check_in';
    case CHECK_OUT = 'check_out';
    case BREAK_START = 'break_start';
    case BREAK_END = 'break_end';

    public static function getValues(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match($this) {
            self::CHECK_IN => 'Entrada',
            self::CHECK_OUT => 'Salida',
            self::BREAK_START => 'Inicio de Descanso',
            self::BREAK_END => 'Fin de Descanso',
        };
    }
}
