<?php

namespace App\Service;

use App\Enum\ErrorCodes\PushNotificationErrorCodes;
use ExpoSDK\Expo;
use ExpoSDK\ExpoMessage;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class ExpoNotificationService
{
    private ApplicationErrorService $applicationErrorService;
    private HttpClientInterface $httpClient;
    private string $expoAccessToken;

    public function __construct(ApplicationErrorService $applicationErrorService,
                                HttpClientInterface $httpClient,
                                string $expoAccessToken)
    {
        $this->applicationErrorService = $applicationErrorService;
        $this->httpClient = $httpClient;
        $this->expoAccessToken = $expoAccessToken;
    }

    public function sendExpoNotification(array $deviceTokens, string $title, string $message): array
    {
        error_log("=== ExpoNotificationService::sendExpoNotification ===");
        error_log("Token count: " . count($deviceTokens));
        error_log("Title: " . $title);
        error_log("Message: " . $message);
        error_log("Access Token (first 10 chars): " . substr($this->expoAccessToken, 0, 10) . "...");

        if (empty($deviceTokens)) {
            error_log("ERROR: No device tokens provided");
            return ['success' => false, 'error' => 'No device tokens provided'];
        }

        // Validar formato de tokens Expo
        $validTokens = [];
        $invalidTokens = [];
        
        foreach ($deviceTokens as $token) {
            // Los tokens de Expo tienen el formato: ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]
            // o ExpoPushToken[xxxxxxxxxxxxxxxxxxxxxx]
            if (preg_match('/^Expo(nent)?PushToken\[[\w-]+\]$/', $token)) {
                $validTokens[] = $token;
            } else {
                $invalidTokens[] = $token;
                error_log("WARNING: Invalid Expo token format: " . $token);
            }
        }

        if (empty($validTokens)) {
            error_log("ERROR: No valid Expo tokens found");
            error_log("Invalid tokens: " . json_encode($invalidTokens));
            return [
                'success' => false, 
                'error' => 'No valid Expo tokens found. All tokens have invalid format.',
                'invalid_tokens' => $invalidTokens
            ];
        }

        if (!empty($invalidTokens)) {
            error_log("WARNING: Found " . count($invalidTokens) . " invalid tokens, proceeding with " . count($validTokens) . " valid tokens");
        }

        $notifications = array_map(fn($token) => [
            'to' => $token,
            'title' => $title,
            'body' => $message,
            'sound' => 'default',
            'channelId' => 'default',
            'priority' => 'high',
            'badge' => 1
        ], $validTokens);

        error_log("Notification payload: " . json_encode($notifications));

        try {
            error_log("Making HTTP request to Expo API...");
            
            $response = $this->httpClient->request('POST', 'https://exp.host/--/api/v2/push/send', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->expoAccessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                ],
                'json' => $notifications,
            ]);

            $statusCode = $response->getStatusCode();
            error_log("HTTP Status Code: " . $statusCode);
            
            $content = $response->getContent(false);
            error_log("Raw Response: " . $content);

            $data = json_decode($content, true);
            error_log("Parsed Response: " . json_encode($data));

            if ($statusCode >= 200 && $statusCode < 300) {
                // Analizar la respuesta de Expo para detectar errores individuales
                $successCount = 0;
                $errorCount = 0;
                $errors = [];

                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $index => $result) {
                        if (isset($result['status'])) {
                            if ($result['status'] === 'ok') {
                                $successCount++;
                                error_log("  Token #{$index}: SUCCESS - ID: " . ($result['id'] ?? 'N/A'));
                            } else if ($result['status'] === 'error') {
                                $errorCount++;
                                $errorMsg = $result['message'] ?? 'Unknown error';
                                $errorDetails = $result['details'] ?? [];
                                $errors[] = [
                                    'token_index' => $index,
                                    'token' => $validTokens[$index] ?? 'unknown',
                                    'message' => $errorMsg,
                                    'details' => $errorDetails
                                ];
                                error_log("  Token #{$index}: ERROR - {$errorMsg} - Details: " . json_encode($errorDetails));
                            }
                        }
                    }
                } else {
                    error_log("WARNING: Unexpected response format from Expo API");
                }

                error_log("SUCCESS: Expo API returned success - Sent: {$successCount}, Errors: {$errorCount}");
                
                return [
                    'success' => true, 
                    'response' => $data,
                    'stats' => [
                        'total_tokens' => count($validTokens),
                        'success_count' => $successCount,
                        'error_count' => $errorCount,
                        'invalid_tokens_count' => count($invalidTokens)
                    ],
                    'errors' => $errors,
                    'invalid_tokens' => $invalidTokens
                ];
            } else {
                error_log("ERROR: Expo API returned error status");
                return ['success' => false, 'error' => 'HTTP ' . $statusCode . ': ' . $content];
            }
        } catch (\Throwable $e) {
            error_log("EXCEPTION in sendExpoNotification: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendBulkExpoNotifications(array $messages): array
    {
        error_log("=== ExpoNotificationService::sendBulkExpoNotifications ===");
        error_log("Message count: " . count($messages));

        if (empty($messages)) {
            return ['success' => false, 'error' => 'No messages provided'];
        }

        try {
            $response = $this->httpClient->request('POST', 'https://exp.host/--/api/v2/push/send', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->expoAccessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $messages,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $data = json_decode($content, true);

            if ($statusCode >= 200 && $statusCode < 300) {
                return ['success' => true, 'response' => $data];
            } else {
                return ['success' => false, 'error' => 'HTTP ' . $statusCode . ': ' . $content];
            }
        } catch (\Throwable $e) {
            error_log("EXCEPTION in sendBulkExpoNotifications: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}