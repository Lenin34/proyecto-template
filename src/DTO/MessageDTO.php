<?php

namespace App\DTO;

use App\Entity\App\Message;

class MessageDTO
{
    public int $id;
    public string $content;
    public string $created_at;

    public function __construct(Message $message)
    {
        $this->id = $message->getId();
        $this->content = $message->getContent();
        $this->created_at = $message->getCreatedAt()->format('Y-m-d H:i:s');
    }
}