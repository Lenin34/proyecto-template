<?php
namespace App\Service;

use AllowDynamicProperties;
use Twilio\Rest\Client;
use App\Service\ApplicationErrorService;
use App\Enum\ErrorCodes\TwilioWhatsAppErrorCodes;
use Psr\Log\LoggerInterface;

#[AllowDynamicProperties]
class TwilioWhatsAppService
{
    private Client $twilioClient;
    private string $twilioWhatsAppNumber;
    private ApplicationErrorService $applicationErrorService;
    private string $twilioSMSNumber;
    private LoggerInterface $logger;

    public function __construct(
        string $twilioAccountSid,
        string $twilioAuthToken,
        string $twilioWhatsAppNumber,
        string $twilioSMSNumber,
        ApplicationErrorService $applicationErrorService,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        // Detailed logging for debugging
        error_log('Twilio Service Initialization:');
        error_log('Account SID: ' . $twilioAccountSid);
        error_log('Auth Token (first/last 4 chars): ' . substr($twilioAuthToken, 0, 4) . '...' . substr($twilioAuthToken, -4));
        error_log('SMS Number: ' . $twilioSMSNumber);
        error_log('WhatsApp Number: ' . $twilioWhatsAppNumber);
        
        try {
            $this->twilioClient = new Client($twilioAccountSid, $twilioAuthToken);
            $this->twilioWhatsAppNumber = $twilioWhatsAppNumber;
            $this->applicationErrorService = $applicationErrorService;
            $this->twilioSMSNumber = $twilioSMSNumber;

            // Verify credentials with a simple API call (non-blocking)
            try {
                $this->twilioClient->account->fetch();
                error_log('Twilio credentials verified successfully');
            } catch (\Throwable $credentialError) {
                error_log('Twilio credentials verification failed (service will continue):');
                error_log('Error Message: ' . $credentialError->getMessage());
                error_log('Error Code: ' . $credentialError->getCode());
                // NO lanzamos la excepción - permitimos que el servicio continúe
            }
        } catch (\Throwable $e) {
            error_log('Twilio Service Critical Initialization Error:');
            error_log('Error Message: ' . $e->getMessage());
            error_log('Error Code: ' . $e->getCode());
            throw $e; // Solo lanzamos si es un error crítico de inicialización
        }
    }
    
    public function sendWhatsAppTemplateMessage(string $to, string $templateName, array $templateParams): bool
    {
        if (empty($to) || empty($templateName) || empty($templateParams)) {
            $this->applicationErrorService->createError(TwilioWhatsAppErrorCodes::TWILIO_WHATSAPP_SERVICE_PARAMETERS_MISSING,[
                    'to' => $to,
                    'templateParams' => $templateParams,
                ]
            );
            
            return false;
        }

        try {
            error_log('Attempting to send WhatsApp template message:');
            error_log('To: whatsapp:' . $to);
            error_log('From: whatsapp:' . $this->twilioWhatsAppNumber);
            error_log('Template: ' . $templateName);
            error_log('Params: ' . json_encode($templateParams));

            $message = $this->twilioClient->messages->create(
                "whatsapp:$to",
                [
                    'from' => "whatsapp:{$this->twilioWhatsAppNumber}",
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => 'es'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => array_map(function($param) {
                                    return ['type' => 'text', 'text' => $param];
                                }, $templateParams),
                            ],
                        ],
                    ],
                ] 
            );
            
            error_log('WhatsApp template message sent successfully. Message SID: ' . $message->sid);
            return true;
        } catch (\Throwable $e) {
            error_log('WhatsApp Template Message Error:');
            error_log('Error Message: ' . $e->getMessage());
            error_log('Error Code: ' . ($e->getCode() ?? 'No code'));
            if (method_exists($e, 'getMoreInfo')) {
                error_log('More Info: ' . $e->getMoreInfo());
            }

            // Si es error 401 (credenciales), logeamos pero no bloqueamos la app
            if ($e->getCode() === 401 || (method_exists($e, 'getStatusCode') && $e->getStatusCode() === 401)) {
                error_log('Twilio credentials issue - WhatsApp not sent but app continues');
            } else {
                $this->applicationErrorService->createError(TwilioWhatsAppErrorCodes::TWILIO_WHATSAPP_SERVICE_ERROR, [
                    'exception' => $e->getMessage(),
                    'to' => $to,
                    'templateName' => $templateName,
                    'templateParams' => $templateParams,
                ]);
            }

            return false;
        }
    }

    public function sendSMSMessage(string $to, string $message): bool
    {
        if (empty($to) || empty($message)) {
            $this->applicationErrorService->createError(TwilioWhatsAppErrorCodes::TWILIO_SMS_SERVICE_PARAMETERS_MISSING,[
                    'to' => $to,
                    'message' => $message,
                ]
            );
            return false;
        }

        $phone = $to;
        if (!str_starts_with($phone, '+')) {
            $phone = '+52' . preg_replace('/[^0-9]/', '', $phone);
        }
        
        try {
            $this->logger->info('Attempting to send SMS message', [
                'to' => $phone,
                'from' => $this->twilioSMSNumber,
                'message_length' => strlen($message)
            ]);

            $result = $this->twilioClient->messages->create(
                $phone,
                [
                    'from' => $this->twilioSMSNumber,
                    'body' => $message,
                ]
            );

            $this->logger->info('SMS sent successfully', [
                'message_sid' => $result->sid,
                'status' => $result->status,
                'direction' => $result->direction,
                'to' => $phone
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('SMS Sending Error', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode() ?? 'No code',
                'to' => $phone,
                'from' => $this->twilioSMSNumber,
                'message_length' => strlen($message),
                'more_info' => method_exists($e, 'getMoreInfo') ? $e->getMoreInfo() : null
            ]);

            // Si es error 401 (credenciales), logeamos pero no bloqueamos la app
            if ($e->getCode() === 401 || (method_exists($e, 'getStatusCode') && $e->getStatusCode() === 401)) {
                $this->logger->warning('Twilio credentials issue - SMS not sent but app continues', [
                    'error' => 'Authentication failed - check Twilio account status',
                    'to' => $phone
                ]);
            } else {
                $this->applicationErrorService->createError(TwilioWhatsAppErrorCodes::TWILIO_WHATSAPP_SERVICE_ERROR, [
                    'exception' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'to' => $phone,
                    'from' => $this->twilioSMSNumber,
                    'message_length' => strlen($message),
                ]);
            }

            return false;
        }
    } 
}