<?php

namespace App\Factory;

use App\Entity\App\Conversation;
use App\Entity\App\Message;
use App\Entity\App\User;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;

class MessageFactory
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    )
    {
    }

    public function create(Conversation $conversation, User $author, string $content, string $dominio): Message
    {
        $this->tenantManager->setCurrentTenant($dominio);
        $em = $this->tenantManager->getEntityManager();

        $message = new Message();

        $conversationReference = $em->getReference(Conversation::class, $conversation->getId());
        $authorReference = $em->getReference(User::class, $author->getId());

        $message->setConversation($conversationReference);
        $message->setAuthor($authorReference);
        $message->setContent($content);
        $message->setCreatedAt(new \DateTime());

        $em->persist($message);
        $em->flush();


        return $message;
    }
}