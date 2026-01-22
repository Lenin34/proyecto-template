<?php

namespace  App\Enum;

enum Features: string
{
    case BENEFICIARIES = 'beneficiarios';
    case BENEFITS = 'beneficios';
    case CHAT = 'chat';
    case CHECKIN = 'checador';
    case CREDENTIAL= 'credencial';
    case DOCS = 'documentos';
    case EVENTS = 'eventos';

    case FORMS   = 'formularios';
    case SOCIAL = 'social';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}
