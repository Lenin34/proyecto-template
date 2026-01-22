<?php

namespace App\Service;

use App\Entity\App\Conversation;
use App\Entity\App\UnreadMessage;
use App\Entity\App\User;
use App\Repository\ConversationRepository;

class ConversationService
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly TenantManager $tenantManager,
    )
    {

    }

    /**
     * @param $companies
     * @return Conversation[]
     */
    public function findByCompanies($companies): array
    {
        return $this->conversationRepository->getConversationsByCompanies($companies);
    }

    public function getSender(User $receiver, Conversation $conversation): User
    {
        foreach ($conversation->getUsers() as $user) {
            if ($receiver->getId() !== $user->getId()) {
                return $user;
            }
        }
        return $receiver; // fallback en caso de conversaciones con un único usuario
    }

    public function markAsRead(string $dominio, Conversation $conversation)
    {
        try {
            $this->tenantManager->setCurrentTenant($dominio);

            $em = $this->tenantManager->getEntityManager();

            $query = $em->createQueryBuilder()
                ->delete(UnreadMessage::class, 'um')
                ->where('um.conversation = :conversation')
                ->setParameter('conversation', $conversation)
                ->getQuery();

            $query->execute();


        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }


}