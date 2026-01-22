<?php

namespace App\Repository;

use App\Entity\App\FormEntryValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormEntryValue>
 *
 * @method FormEntryValue|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormEntryValue|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormEntryValue[]    findAll()
 * @method FormEntryValue[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormEntryValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormEntryValue::class);
    }

    public function save(FormEntryValue $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FormEntryValue $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}