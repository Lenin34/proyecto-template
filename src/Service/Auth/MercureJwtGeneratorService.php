<?php

namespace App\Service\Auth;

use Firebase\JWT\JWT;

class MercureJwtGeneratorService
{
    private string $mercureSecret;

    public function __construct(string $mercureSecret)
    {
        $this->mercureSecret = $mercureSecret;
    }

    public function generate(array $subscribeTopics = []): string
    {
        $payload = [
            'mercure' => [
                'subscribe' => $subscribeTopics,
            ],
            'exp' => (new \DateTime('+1 hour'))->getTimestamp(),
        ];

        return JWT::encode($payload, $this->mercureSecret, 'HS256');
    }
}
