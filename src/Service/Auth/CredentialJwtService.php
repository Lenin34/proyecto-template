<?php

namespace App\Service\Auth;

use App\Entity\App\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CredentialJwtService
{
    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl)
    {
        $this->secret = $secret;
        $this->ttl = $ttl;
    }

    public function generateCredentialToken(User $user): string
    {
        $now = time();
        $payload = [
            'sub' => $user->getId(),
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function decodeCredentialToken(string $token): ?array
    {
        try {
            return (array) JWT::decode($token, new Key($this->secret, 'HS256'));

        } catch (ExpiredException $e) {
            // Token expirado
            return null;
        } catch (\Throwable $e) {
            // Token inválido
            return null;
        }
    }


}
