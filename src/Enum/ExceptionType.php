<?php

namespace App\Enum;

enum ExceptionType: string
{
    case VACATION = 'vacation';
    case SICK_LEAVE = 'sick_leave';
    case PERSONAL_LEAVE = 'personal_leave';
    case BUSINESS_TRIP = 'business_trip';
    case JUSTIFIED_ABSENCE = 'justified_absence';
    case MEDICAL_APPOINTMENT = 'medical_appointment';
    case TRAINING = 'training';
    case EMERGENCY = 'emergency';

    public static function getValues(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match($this) {
            self::VACATION => 'Vacaciones',
            self::SICK_LEAVE => 'Incapacidad Médica',
            self::PERSONAL_LEAVE => 'Permiso Personal',
            self::BUSINESS_TRIP => 'Viaje de Trabajo',
            self::JUSTIFIED_ABSENCE => 'Falta Justificada',
            self::MEDICAL_APPOINTMENT => 'Cita Médica',
            self::TRAINING => 'Capacitación',
            self::EMERGENCY => 'Emergencia',
        };
    }

    public function requiresApproval(): bool
    {
        return match($this) {
            self::VACATION, 
            self::PERSONAL_LEAVE, 
            self::BUSINESS_TRIP => true,
            default => false,
        };
    }
}
