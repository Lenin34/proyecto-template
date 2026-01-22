<?php
namespace App\Enum\ErrorCodes;

class TwilioWhatsAppErrorCodes 
{
    public const TWILIO_WHATSAPP_SERVICE_PARAMETERS_MISSING = [
        'code' => 'TWAS-001',
        'message' => 'Faltan parámetros para enviar el mensaje de WhatsApp por Twilio.',
        'http_code' => 400,
    ];

    public const TWILIO_WHATSAPP_SERVICE_ERROR = [
        'code' => 'TWAS-002',
        'message' => 'Error al enviar el mensaje de WhatsApp por Twilio.',
        'http_code' => 500,
    ];

    public const TWILIO_SMS_SERVICE_PARAMETERS_MISSING = [


        'code' => 'TWAS-003',


        'message' => 'Faltan parámetros para enviar el mensaje de SMS por Twilio.',


        'http_code' => 400,


    ];

}
