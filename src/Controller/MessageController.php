<?php

namespace App\Controller;

use App\DTO\MessageRequest;
use App\Entity\App\User;
use App\Factory\MessageFactory;
use App\Service\ExpoNotificationService;
use App\Service\TenantManager;
use App\Service\TopicService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @method User|null getUser()
 */

#[Route('/{dominio}/message')]
final class MessageController extends AbstractController
{

    public function __construct(
        private readonly MessageFactory $messageFactory,
        private readonly TopicService $topicService,
        private readonly HubInterface $hub,
        private readonly TenantManager $tenantManager,
        private readonly ExpoNotificationService $expoNotificationService,
    )
    {
    }

    #[Route('/', name: 'app_message_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] MessageRequest $payload, string $dominio): Response
    {
        try {
            // Configurar el tenant
            $em = $this->tenantManager->getEntityManager();
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found', $e);
        }

        try {
            // Buscar conversación usando TenantManager
            $conversation = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Conversation', 'c')
                ->where('c.id = :id')
                ->setParameter('id', $payload->conversationId)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$conversation) {
                throw $this->createNotFoundException('Conversation not found');
            }
        } catch (\Exception $e) {
            throw $this->createNotFoundException('conversation not found', $e);
        }

        try {
            $message = $this->messageFactory->create(
                conversation: $conversation,
                author: $this->getUser(),
                content: $payload->content,
                dominio: $dominio,
            );
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Message not found', $e);
        }


        try {
            $data = [
                'author' => [
                    'id' => $this->getUser()->getId(),
                    'name' => $this->getUser()->getName(),
                    'lastName' => $this->getUser()->getLastname(),
                    'role' => $this->getUser()->getRoles(),
                ],
                'content' => $message->getContent(),
                'id' => $message->getId(),
                'createdAt' => $message->getCreatedAt()->setTimezone(new \DateTimeZone('America/Mexico_City'))->format('d/m/Y H:i'),
            ];

            $update = new Update(
                topics: $this->topicService->getTopicUrl($conversation),
                data: json_encode($data),
                private: true
            );

            $this->hub->publish($update);

            $conversationUsers = $conversation->getUsers()->toArray();

            $receiver = array_filter($conversationUsers, function (User $user) {
                return $user->getId() !== $this->getUser()->getId();
            });

            $receiver = array_values($receiver);

            if (!isset($receiver[0])) {
                throw $this->createNotFoundException('No se encontró el receptor de la conversación.');
            }

            $receiverDeviceTokens = [];

            foreach ($receiver as $user) {
                foreach ($user->getDeviceTokens() as $token) {
                    $receiverDeviceTokens[] = $token->getToken(); // extrae el string del token
                }
            }

            try {
                $result = $this->expoNotificationService->sendExpoNotification(
                    deviceTokens: $receiverDeviceTokens,
                    title: 'Mensaje nuevo',
                    message: $message->getContent(),
                );

                if (!$result['success']) {
                    throw new \RuntimeException('Error enviando notificación: ' . ($result['error'] ?? 'Sin detalle'));
                }

            } catch (\Exception $e) {
                throw $this->createNotFoundException('Notification not sent', $e);
            }


        } catch (\Exception $e) {
            throw $this->createNotFoundException('publish not found', $e);
        }

            return new Response($message->getId(), Response::HTTP_CREATED);

    }
}
