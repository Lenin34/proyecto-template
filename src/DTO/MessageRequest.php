<?php

namespace App\DTO;

readonly final class MessageRequest
{
    public function __construct(
        public string $content,
        public int $conversationId,)
    {

    }
}