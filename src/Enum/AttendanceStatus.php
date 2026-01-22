<?php

namespace App\Enum;

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case PARTIAL = 'partial';
    case LATE = 'late';
    case EARLY_DEPARTURE = 'early_departure';
    case PENDING_CALCULATION = 'pending_calculation';

    public static function getValues(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match($this) {
            self::PRESENT => 'Presente',
            self::ABSENT => 'Ausente',
            self::PARTIAL => 'Asistencia Parcial',
            self::LATE => 'Llegada Tardía',
            self::EARLY_DEPARTURE => 'Salida Temprana',
            self::PENDING_CALCULATION => 'Pendiente de Cálculo',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::PRESENT => 'badge-success',
            self::ABSENT => 'badge-danger',
            self::PARTIAL => 'badge-warning',
            self::LATE => 'badge-warning',
            self::EARLY_DEPARTURE => 'badge-warning',
            self::PENDING_CALCULATION => 'badge-secondary',
        };
    }
}
