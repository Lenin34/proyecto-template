<?php

namespace App\Repository;

use App\Entity\Master\MasterUser;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MasterUser>
 */
class MasterUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MasterUser::class);
    }

    /**
     * Encuentra un usuario Master activo por email
     */
    public function findActiveByEmail(string $email): ?MasterUser
    {
        return $this->createQueryBuilder('mu')
            ->where('mu.email = :email')
            ->andWhere('mu.status = :status')
            ->setParameter('email', $email)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra todos los usuarios Master activos
     *
     * @return MasterUser[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('mu')
            ->where('mu.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('mu.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra usuarios Master por rol
     *
     * @return MasterUser[]
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('mu')
            ->where('JSON_CONTAINS(mu.roles, :role) = 1')
            ->andWhere('mu.status = :status')
            ->setParameter('role', json_encode($role))
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta usuarios Master activos
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('mu')
            ->select('COUNT(mu.id)')
            ->where('mu.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

