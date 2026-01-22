<?php

namespace App\Enum;

enum Status: string
{
    case ACTIVE = '1';
    case INACTIVE = '0';
    case DELETED = '2';

    public static function getValues(): array
    {
        return array_map(fn(self $case) => $case->name, self::cases());
    }
}
