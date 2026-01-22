<?php

namespace App\Factory;

use App\Entity\App\Conversation;
use App\Entity\App\User;
use App\Repository\ConversationRepository;

class ConversationFactory
{

    public function __construct(
        private readonly ConversationRepository $conversationRepository,
    )
    {

    }

    public function create(User $sender): Conversation
    {
        $conversation = new Conversation();

        $conversation->addUser($sender);

        $this->conversationRepository->save($conversation);
        return $conversation;
    }
}