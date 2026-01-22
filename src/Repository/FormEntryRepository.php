<?php

namespace App\Repository;

use App\Entity\App\FormEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormEntry>
 *
 * @method FormEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormEntry[]    findAll()
 * @method FormEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormEntry::class);
    }

    public function save(FormEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FormEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Encuentra todas las respuestas de un formulario específico
     */
    public function findByFormTemplate($formTemplate): array
    {
        return $this->createQueryBuilder('fe')
            ->leftJoin('fe.user', 'u')
            ->addSelect('u')
            ->where('fe.formTemplate = :formTemplate')
            ->andWhere('fe.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->orderBy('fe.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta las respuestas activas de un formulario
     */
    public function countByFormTemplate($formTemplate): int
    {
        return $this->createQueryBuilder('fe')
            ->select('COUNT(fe.id)')
            ->where('fe.formTemplate = :formTemplate')
            ->andWhere('fe.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Encuentra una respuesta con sus valores cargados
     */
    public function findWithValues(int $id): ?FormEntry
    {
        return $this->createQueryBuilder('fe')
            ->leftJoin('fe.formEntryValues', 'fev')
            ->leftJoin('fev.formTemplateField', 'ftf')
            ->leftJoin('fe.user', 'u')
            ->addSelect('fev', 'ftf', 'u')
            ->where('fe.id = :id')
            ->andWhere('fe.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }
}