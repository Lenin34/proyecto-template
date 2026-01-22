<?php

namespace App\Service;

use App\Entity\App\Conversation;
use Symfony\Component\HttpFoundation\RequestStack;

class TopicService
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getPublicTopic(): string
    {
        return $this->getServerUrl() . "/conversations/";
    }

    public function getTopicUrl(Conversation $conversation): string
    {
//        return "{this->getServerUrl()}/conversations/{$conversation->getId()}";
        return $this->getServerUrl() . "/conversations/" . $conversation->getId();
    }

    private function getServerUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();
        $portUrl = $port ? ":$port" : '';

        return "{$scheme}://{$host}{$portUrl}";
    }

}