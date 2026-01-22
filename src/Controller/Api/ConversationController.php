<?php
namespace App\Controller\Api;

use App\DTO\MessageDTO;
use App\DTO\MessageRequest;
use App\Entity\App\Company;
use App\Entity\App\Conversation;
use App\Entity\App\Message;
use App\Entity\App\UnreadMessage;
use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\UserErrorCodes;
use App\Enum\Status;
use App\Factory\MessageFactory;
use App\Repository\ConversationRepository;
use App\Service\Auth\MercureJwtGeneratorService;
use App\Service\ErrorResponseService;
use App\Service\TenantManager;
use App\Service\TopicService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class ConversationController extends AbstractController
{

    private string $mercurePublicUrl;


    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TopicService $topicService,
        private readonly JWTTokenManagerInterface $JWTTokenManager,
        private readonly ErrorResponseService $errorResponseService,
        string $mercurePublicUrl,
        private readonly MercureJwtGeneratorService $mercureJwtGenerator,
        private readonly MessageFactory $messageFactory,
        private readonly HubInterface $hub,
    )
    {
        $this->mercurePublicUrl = $mercurePublicUrl;
    }

    #[Route('/users/{sender}/subscribe', name: 'app_conversation_load', methods: ['GET'])]
    public function subscribe(string $dominio, User $sender): JsonResponse
    {
        // Verificar si el usuario existe, si no, devolver error
        if (!$sender) {
            return $this->errorResponseService->createErrorResponse(UserErrorCodes::USER_NOT_FOUND);
        }

        // Configurar el tenant
        $em = $this->tenantManager->getEntityManager();


        // Verificar si el usuario tiene una conversación
        $conversations = $sender->getConversations();

        $company = $sender->getCompany();

        if ($company) {
            // Asegurarse de que la entidad Company esté completamente cargada usando consulta directa
            $company = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.id = :id')
                ->setParameter('id', $company->getId())
                ->getQuery()
                ->getOneOrNullResult();
        }


        if ($conversations->isEmpty()) {
            $conversation = new Conversation();
            $conversation->setStatus(Status::ACTIVE);
            $conversation->setCompany($company);
            $conversation->setCreatedAt(new \DateTime());

            // Aquí agregas el usuario a la conversación:
            $conversation->addUser($sender);

            $em->persist($conversation);
            $em->flush();

            $topic = $this->topicService->getTopicUrl($conversation);

            $jwt = $this->mercureJwtGenerator->generate([$topic]);

            $sseUrl = $this->mercurePublicUrl . '?topic=' . urlencode($topic);

            return new JsonResponse([
                'conversation' => $conversation->getId(),
                'messages' => [],
                'topic' => $sseUrl,
                'authorization' => $jwt,
            ], 200);
        }


        $conversation = $conversations->first();

        // Si ya existe una conversación, obtenemos los mensajes usando lógica directa
        $messagesCollection = $conversation->getMessages()->toArray();

        if (empty($messagesCollection)) {
            $messages = [];
        } else {
            $messagesCollection = array_reverse($messagesCollection);
            $messages = array_map(function ($message) {
                $author = $message->getAuthor();
                return [
                    'id' => $message->getId(),
                    'content' => $message->getContent(),
                    'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                    'author' => [
                        'id' => $author->getId(),
                        'name' => $author->getName(),
                        'lastName' => $author->getLastName(),
                        'roles' => $author->getRoles(),
                    ]
                ];
            }, $messagesCollection);
        }

        $topic = $this->topicService->getTopicUrl($conversation);

        $jwt = $this->mercureJwtGenerator->generate([$topic]);

        $sseUrl = $this->mercurePublicUrl . '?topic=' . urlencode($topic);

        return new JsonResponse([
            'conversation' => $conversation->getId(),
            'messages' => $messages,
            'topic' => $sseUrl,
            'authorization' => $jwt,
        ]);
    }

    #[Route('/users/{sender}/publish', name: 'app_conversation_publish', methods: ['POST'])]
    public function publish(#[MapRequestPayload] MessageRequest $payload, User $sender, string $dominio): JsonResponse
    {
        // Configurar el tenant
        try {
            $em = $this->tenantManager->getEntityManager();
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'messages' => 'tenant not found',
            ]);
        }

        try {
            $conversation = $this->tenantManager->getEntityManager()->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Conversation', 'c')
                ->where('c.id = :id')
                ->setParameter('id', $payload->conversationId)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'messages' => 'conversation not found',
            ]);
        }

        try {
            $message = $this->messageFactory->create(
                conversation: $conversation,
                author: $sender,
                content: $payload->content,
                dominio: $dominio
            );
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'messages' => 'conversation not found',
            ]);
        }

        try {
            $unreadMessage = new UnreadMessage();

            $conversationRef = $em->getReference(Conversation::class, $conversation->getId());
            $messageRef = $em->getReference(Message::class, $message->getId());

            $unreadMessage->setConversation($conversationRef);
            $unreadMessage->setMessage($messageRef);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'messages' => 'unread not found',
            ]);
        }

        try {
            $em->persist($unreadMessage);
            $em->flush();
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'messages' => 'flush not found',
            ]);
        }

        try {
            $data = [
                'author' => [
                    'id' => $sender->getId(),
                    'name' => $sender->getName(),
                    'lastName' => $sender->getLastname(),
                    'role' => $sender->getRoles(),
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
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'messages' => 'failed to publish',
            ]);
        }

        return new JsonResponse([
            'conversation' => $conversation->getId(),

        ], 200);
    }

    // Método para generar el JWT
    private function generateJwt(User $sender, Conversation $conversation): string
    {
        $topic = $this->topicService->getTopicUrl($conversation);

        // Generar el JWT con el topic de la conversación
        return $this->JWTTokenManager->createFromPayload($sender, [
            'mercure' => [
                'subscribe' => [$topic],  // Solo suscripción para el topic específico
            ]
        ]);
    }

    private function messagesToArray(Collection $messages): array
    {
        return array_map(
            fn ($message) => new MessageDTO($message),
            $messages->toArray()
        );
    }



}