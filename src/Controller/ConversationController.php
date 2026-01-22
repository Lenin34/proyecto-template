<?php

namespace App\Controller;

use App\Entity\App\Conversation;
use App\Entity\App\User;
use App\Enum\Status;
use App\Service\ConversationService;
use App\Service\TenantManager;
use App\Service\TopicService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Discovery;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @method User|null getUser()
 */

#[Route('/{dominio}/conversation')]
final class ConversationController extends AbstractController
{

    public function __construct(
        private readonly Authorization $authorization,
        private readonly ConversationService $conversationService,
        private readonly Discovery $discovery,
        private readonly TopicService $topicService,
        private readonly TenantManager $tenantManager
    ){
    }


    #[Route('/', name: 'app_conversation_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        try {

            $user = $this->getUser();

            $userRegions = $user->getRegions();
            $regionCompanies = [];
            foreach ($userRegions as $region) {
                foreach ($region->getCompanies() as $company) {
                    $regionCompanies[] = $company;
                }
            }

            $conversations = $this->conversationService->findByCompanies($regionCompanies);

            return $this->render('conversation/index.html.twig', [
                'controller_name' => 'ConversationController',
                'conversations' => $conversations,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}', name: 'app_conversation_show', methods: ['GET'])]
    public function show(Conversation $conversation, Request $request, string $dominio): Response
    {
        try {

            $messages = $conversation->getMessages();

            $topic = $this->topicService->getTopicUrl($conversation);

            $this->discovery->addLink($request);

            $this->authorization->setCookie($request, [$topic]);

            $this->conversationService->markAsRead($dominio, $conversation);

            return $this->render('conversation/show.html.twig', [
                'conversation' => $conversation,
                'messages' => $messages,
                'topic' => $this->topicService->getTopicUrl($conversation),
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{conversation}', name: 'app_conversation_delete', methods: ['DELETE'])]
    public function delete(Conversation $conversation, string $dominio, Request $request): Response
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            if ($this->isCsrfTokenValid('delete'.$conversation->getId(), $request->request->get('_token'))) {
                $conversation->setStatus(Status::INACTIVE);
                $entityManager->flush();
            }
            return $this->redirectToRoute('app_conversation_index', ['dominio' => $dominio], Response::HTTP_SEE_OTHER);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }

    }

}
