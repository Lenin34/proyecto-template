<?php


namespace App\Service;

use Symfony\Component\Mercure\Update;
use Symfony\Component\HttpFoundation\JsonResponse;

class MercureService
{
    private $publisher;

    public function __construct(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    public function publishMessage(string $topic, array $data): JsonResponse
    {
        $update = new Update(
            $topic, // topic e.g., 'chat/1'
            json_encode($data) // the actual message data
        );

        $this->publisher->publish($update);

        return new JsonResponse(['status' => 'success']);
    }
}
