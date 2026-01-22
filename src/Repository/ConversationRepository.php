<?php

namespace App\Repository;

use App\Entity\App\Conversation;
use App\Entity\App\Message;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    //    /**
    //     * @return Conversation[] Returns an array of Conversation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Conversation
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function getConversationsByCompanies($company)
    {
        return $this->createQueryBuilder('c')
            ->addSelect('COUNT(um.id) AS HIDDEN unreadCount')
            ->andWhere('c.company IN (:company)')
            ->leftJoin('c.unreadMessages', 'um')
            ->andWhere('c.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('company', $company)
            ->groupBy('c.id')
            ->orderBy('unreadCount', 'DESC')
            ->addOrderBy('c.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function getUsersByConversationId(int $conversationId): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'u')
            ->innerJoin('c.users', 'u')
            ->where('c.id = :id')
            ->andWhere('c.status = :status')
            ->setParameter('id', $conversationId)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getResult();
    }

    public function save(Conversation $conversation)
    {
        $this->getEntityManager()->persist($conversation);
        $this->getEntityManager()->flush();
    }

    public function getMessagesByConversationId(Conversation $conversation): array
    {
        $messages = $conversation->getMessages()->toArray();

        if (empty($messages)) {
            return [];
        }

        $messages = array_reverse($messages);

        return array_map(function (Message $message) {
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
        }, $messages);
    }


}
