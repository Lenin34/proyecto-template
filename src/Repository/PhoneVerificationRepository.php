<?php

namespace App\Repository;

use App\Entity\App\PhoneVerification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PhoneVerification>
 *
 * @method PhoneVerification|null find($id, $lockMode = null, $lockVersion = null)
 * @method PhoneVerification|null findOneBy(array $criteria, array $orderBy = null)
 * @method PhoneVerification[]    findAll()
 * @method PhoneVerification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PhoneVerificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PhoneVerification::class);
    }

    public function save(PhoneVerification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PhoneVerification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
