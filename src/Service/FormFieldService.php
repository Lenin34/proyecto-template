<?php

namespace App\Service;

use App\DTO\Form\FormFieldCreateRequest;
use App\DTO\Form\FormFieldUpdateRequest;
use App\Entity\App\FormTemplate;
use App\Entity\App\FormTemplateField;
use App\Enum\Status;
use App\Repository\FormTemplateFieldRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FormFieldService
{
    private TenantManager $tenantManager;
    private TenantLoggerService $logger;

    public function __construct(
        TenantManager $tenantManager,
        TenantLoggerService $logger
    ) {
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->tenantManager->getEntityManager();
    }

    /**
     * Obtiene el FormTemplateFieldRepository del tenant actual
     */
    private function getFieldRepository(): FormTemplateFieldRepository
    {
        return $this->getEntityManager()->getRepository(FormTemplateField::class);
    }

    /**
     * Obtiene todos los campos activos de un formulario
     */
    public function getActiveFieldsByFormTemplate(FormTemplate $formTemplate): array
    {
        return $this->getFieldRepository()->findActiveByFormTemplate($formTemplate);
    }

    /**
     * Obtiene un campo por ID con validación
     * Usa query directa con el EntityManager del tenant para evitar problemas de multi-tenancy
     */
    public function getFieldById(int $fieldId, FormTemplate $formTemplate): FormTemplateField
    {
        $em = $this->getEntityManager();
        
        $field = $em->createQueryBuilder()
            ->select('ftf')
            ->from(FormTemplateField::class, 'ftf')
            ->where('ftf.id = :fieldId')
            ->andWhere('ftf.formTemplate = :formTemplate')
            ->andWhere('ftf.status != :deletedStatus')
            ->setParameter('fieldId', $fieldId)
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('deletedStatus', Status::DELETED)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$field) {
            throw new NotFoundHttpException('Campo no encontrado o no pertenece a este formulario.');
        }

        return $field;
    }

    /**
     * Crea un nuevo campo
     */
    public function createField(FormTemplate $formTemplate, FormFieldCreateRequest $dto): FormTemplateField
    {
        $em = $this->getEntityManager();
        $fieldRepository = $this->getFieldRepository();

        // Validar que el nombre del campo sea único
        if (!$fieldRepository->isFieldNameUnique($dto->getName(), $formTemplate)) {
            throw new BadRequestHttpException('Ya existe un campo con ese nombre en este formulario.');
        }

        try {
            $em->beginTransaction();

            $field = new FormTemplateField();
            $field->setFormTemplate($formTemplate);
            $field->setLabel($dto->getLabel());
            $field->setName($dto->getName());
            $field->setType($dto->getType());
            $field->setIsRequired($dto->getIsRequired() ?? false);
            $field->setOptions($dto->getOptions());
            $field->setHelp($dto->getHelp());
            $field->setMultiple($dto->getMultiple() ?? false);
            $field->setCols($dto->getCols());
            $field->setTextareaCols($dto->getTextareaCols());
            $field->setStatus(Status::ACTIVE);

            // Asignar la siguiente posición disponible
            $field->setPosition($fieldRepository->getNextPosition($formTemplate));

            $em->persist($field);
            $em->flush();

            $this->logger->info('Form field created', [
                'field_id' => $field->getId(),
                'form_id' => $formTemplate->getId(),
                'field_name' => $field->getName(),
                'field_type' => $field->getType()
            ]);

            $em->commit();

            return $field;

        } catch (\Exception $e) {
            $em->rollback();

            $this->logger->error('Error creating form field', [
                'error' => $e->getMessage(),
                'form_id' => $formTemplate->getId(),
                'field_name' => $dto->getName()
            ]);

            throw $e;
        }
    }

    /**
     * Actualiza un campo existente
     */
    public function updateField(int $fieldId, FormTemplate $formTemplate, FormFieldUpdateRequest $dto): FormTemplateField
    {
        $em = $this->getEntityManager();
        $fieldRepository = $this->getFieldRepository();
        $field = $this->getFieldById($fieldId, $formTemplate);

        // Validar que el nombre del campo sea único (excluyendo el campo actual)
        if (!$fieldRepository->isFieldNameUnique($dto->getName(), $formTemplate, $fieldId)) {
            throw new BadRequestHttpException('Ya existe un campo con ese nombre en este formulario.');
        }

        try {
            $em->beginTransaction();

            $field->setLabel($dto->getLabel());
            $field->setName($dto->getName());
            $field->setType($dto->getType());
            $field->setIsRequired($dto->getIsRequired() ?? false);
            $field->setOptions($dto->getOptions());
            $field->setHelp($dto->getHelp());
            $field->setMultiple($dto->getMultiple() ?? false);
            $field->setCols($dto->getCols());
            $field->setTextareaCols($dto->getTextareaCols());

            $em->flush();

            $this->logger->info('Form field updated', [
                'field_id' => $field->getId(),
                'form_id' => $formTemplate->getId(),
                'field_name' => $field->getName(),
                'field_type' => $field->getType()
            ]);

            $em->commit();

            return $field;

        } catch (\Exception $e) {
            $em->rollback();

            $this->logger->error('Error updating form field', [
                'error' => $e->getMessage(),
                'field_id' => $fieldId,
                'form_id' => $formTemplate->getId()
            ]);

            throw $e;
        }
    }

    /**
     * Elimina un campo (soft delete)
     */
    public function deleteField(int $fieldId, FormTemplate $formTemplate): void
    {
        $em = $this->getEntityManager();
        $fieldRepository = $this->getFieldRepository();
        $field = $this->getFieldById($fieldId, $formTemplate);

        try {
            $em->beginTransaction();

            $deletedPosition = $field->getPosition();
            $field->setStatus(Status::INACTIVE);

            $em->flush();

            // Reordenar campos restantes
            $fieldRepository->reorderFieldsAfterDelete($formTemplate, $deletedPosition);

            $this->logger->info('Form field deleted', [
                'field_id' => $field->getId(),
                'form_id' => $formTemplate->getId(),
                'field_name' => $field->getName()
            ]);

            $em->commit();

        } catch (\Exception $e) {
            $em->rollback();

            $this->logger->error('Error deleting form field', [
                'error' => $e->getMessage(),
                'field_id' => $fieldId,
                'form_id' => $formTemplate->getId()
            ]);

            throw $e;
        }
    }

    /**
     * Reordena campos
     */
    public function reorderFields(FormTemplate $formTemplate, array $fieldOrder): void
    {
        $em = $this->getEntityManager();

        try {
            $em->beginTransaction();

            foreach ($fieldOrder as $position => $fieldId) {
                $field = $this->getFieldById($fieldId, $formTemplate);
                $field->setPosition($position + 1); // Las posiciones empiezan en 1
            }

            $em->flush();

            $this->logger->info('Form fields reordered', [
                'form_id' => $formTemplate->getId(),
                'field_count' => count($fieldOrder)
            ]);

            $em->commit();

        } catch (\Exception $e) {
            $em->rollback();

            $this->logger->error('Error reordering form fields', [
                'error' => $e->getMessage(),
                'form_id' => $formTemplate->getId()
            ]);

            throw $e;
        }
    }

    /**
     * Duplica un campo
     */
    public function duplicateField(int $fieldId, FormTemplate $formTemplate): FormTemplateField
    {
        $em = $this->getEntityManager();
        $fieldRepository = $this->getFieldRepository();
        $originalField = $this->getFieldById($fieldId, $formTemplate);

        try {
            $em->beginTransaction();

            $newField = new FormTemplateField();
            $newField->setFormTemplate($formTemplate);
            $newField->setLabel($originalField->getLabel() . ' (Copia)');
            $newField->setName($this->generateUniqueFieldName($originalField->getName(), $formTemplate));
            $newField->setType($originalField->getType());
            $newField->setIsRequired($originalField->getIsRequired());
            $newField->setOptions($originalField->getOptions());
            $newField->setHelp($originalField->getHelp());
            $newField->setMultiple($originalField->getMultiple());
            $newField->setCols($originalField->getCols());
            $newField->setTextareaCols($originalField->getTextareaCols());
            $newField->setStatus(Status::ACTIVE);
            $newField->setPosition($fieldRepository->getNextPosition($formTemplate));

            $em->persist($newField);
            $em->flush();

            $this->logger->info('Form field duplicated', [
                'original_field_id' => $originalField->getId(),
                'new_field_id' => $newField->getId(),
                'form_id' => $formTemplate->getId()
            ]);

            $em->commit();

            return $newField;

        } catch (\Exception $e) {
            $em->rollback();

            $this->logger->error('Error duplicating form field', [
                'error' => $e->getMessage(),
                'field_id' => $fieldId,
                'form_id' => $formTemplate->getId()
            ]);

            throw $e;
        }
    }

    /**
     * Obtiene estadísticas de campos
     */
    public function getFieldStats(FormTemplate $formTemplate): array
    {
        $fields = $this->getActiveFieldsByFormTemplate($formTemplate);
        $typeCount = [];

        foreach ($fields as $field) {
            $type = $field->getType();
            $typeCount[$type] = ($typeCount[$type] ?? 0) + 1;
        }

        return [
            'total_fields' => count($fields),
            'required_fields' => count(array_filter($fields, fn($f) => $f->getIsRequired())),
            'field_types' => $typeCount
        ];
    }

    /**
     * Genera un nombre único para un campo duplicado
     */
    private function generateUniqueFieldName(string $baseName, FormTemplate $formTemplate): string
    {
        $fieldRepository = $this->getFieldRepository();
        $counter = 1;
        $newName = $baseName . '_copy';

        while (!$fieldRepository->isFieldNameUnique($newName, $formTemplate)) {
            $counter++;
            $newName = $baseName . '_copy_' . $counter;
        }

        return $newName;
    }
}
