<?php
namespace App\Service;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Contract\Messaging;
use App\Service\ApplicationErrorService;
use App\Enum\ErrorCodes\PushNotificationErrorCodes;

class PushNotificationService
{
    private Messaging $messaging;
    private ApplicationErrorService $applicationErrorService;

    public function __construct(Messaging $messaging, ApplicationErrorService $applicationErrorService)
    {
        $this->messaging = $messaging;
        $this->applicationErrorService = $applicationErrorService;
    }

    public function sendBatchNotification(array $deviceTokens, string $title, string $body, int $batchSize = 500)
    {
        if (empty($deviceTokens)) {
            $this->applicationErrorService->createError(PushNotificationErrorCodes::PUSH_NOTIFICATION_NO_USERS_TOKENS);

            return ['success' => [], 'failed' => $deviceTokens];
        }

        $successTokens = [];
        $failedTokens = [];

        $batches = array_chunk($deviceTokens, $batchSize);

        foreach ($batches as $batch) {
            try {
                $message = CloudMessage::new()
                    ->withNotification(['title' => $title, 'body' => $body]);

                $response = $this->messaging->sendMulticast($message, $batch);

                $successTokens = array_merge($successTokens, $response->validTokens());
                foreach ($response->failures() as $failure) {
                    $failedTokens[] = $failure->token();
                }

                $this->applicationErrorService->createError(PushNotificationErrorCodes::PUSH_NOTIFICATION_SOME_SEND_FAILED, [
                    'failed_tokens' => $failedTokens,
                ]);
            } catch (\Throwable $e) {
                $this->applicationErrorService->createError(PushNotificationErrorCodes::PUSH_NOTIFICATION_SEND_FAILED , [
                    'error' => $e->getMessage(),
                    'batch' => $batch,
                ]);
                $failedTokens = array_merge($failedTokens, $batch);
            }
        }

        return [
            'success' => $successTokens,
            'failed' => $failedTokens,
        ];
    }
}