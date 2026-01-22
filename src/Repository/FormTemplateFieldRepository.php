<?php

namespace App\Repository;

use App\Entity\App\FormTemplate;
use App\Entity\App\FormTemplateField;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormTemplateField>
 *
 * @method FormTemplateField|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormTemplateField|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormTemplateField[]    findAll()
 * @method FormTemplateField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormTemplateFieldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormTemplateField::class);
    }

    public function save(FormTemplateField $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FormTemplateField $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Encuentra campos activos de un formulario ordenados por posición
     */
    public function findActiveByFormTemplate(FormTemplate $formTemplate): array
    {
        return $this->createQueryBuilder('ftf')
            ->where('ftf.formTemplate = :formTemplate')
            ->andWhere('ftf.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ftf.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra un campo por ID y formulario con validación
     */
    public function findByIdAndFormTemplate(int $fieldId, FormTemplate $formTemplate): ?FormTemplateField
    {
        return $this->createQueryBuilder('ftf')
            ->where('ftf.id = :fieldId')
            ->andWhere('ftf.formTemplate = :formTemplate')
            ->andWhere('ftf.status != :deletedStatus')
            ->setParameter('fieldId', $fieldId)
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('deletedStatus', Status::DELETED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Obtiene la siguiente posición disponible para un formulario
     */
    public function getNextPosition(FormTemplate $formTemplate): int
    {
        $result = $this->createQueryBuilder('ftf')
            ->select('MAX(ftf.position)')
            ->where('ftf.formTemplate = :formTemplate')
            ->andWhere('ftf.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Verifica si un nombre de campo ya existe en el formulario
     */
    public function isFieldNameUnique(string $name, FormTemplate $formTemplate, ?int $excludeFieldId = null): bool
    {
        $qb = $this->createQueryBuilder('ftf')
            ->select('COUNT(ftf.id)')
            ->where('ftf.name = :name')
            ->andWhere('ftf.formTemplate = :formTemplate')
            ->andWhere('ftf.status = :status')
            ->setParameter('name', $name)
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('status', Status::ACTIVE);

        if ($excludeFieldId) {
            $qb->andWhere('ftf.id != :excludeId')
               ->setParameter('excludeId', $excludeFieldId);
        }

        return $qb->getQuery()->getSingleScalarResult() == 0;
    }

    /**
     * Reordena campos después de eliminar uno
     */
    public function reorderFieldsAfterDelete(FormTemplate $formTemplate, int $deletedPosition): void
    {
        $this->createQueryBuilder('ftf')
            ->update()
            ->set('ftf.position', 'ftf.position - 1')
            ->where('ftf.formTemplate = :formTemplate')
            ->andWhere('ftf.position > :deletedPosition')
            ->andWhere('ftf.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('deletedPosition', $deletedPosition)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->execute();
    }

    /**
     * Cuenta campos activos de un formulario
     */
    public function countActiveByFormTemplate(FormTemplate $formTemplate): int
    {
        return $this->createQueryBuilder('ftf')
            ->select('COUNT(ftf.id)')
            ->where('ftf.formTemplate = :formTemplate')
            ->andWhere('ftf.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Encuentra campos por tipo
     */
    public function findByType(FormTemplate $formTemplate, string $type): array
    {
        return $this->createQueryBuilder('ftf')
            ->where('ftf.formTemplate = :formTemplate')
            ->andWhere('ftf.type = :type')
            ->andWhere('ftf.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('type', $type)
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ftf.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}