<?php

namespace App\Service;

use App\DTO\Form\FormTemplateCreateRequest;
use App\DTO\Form\FormTemplateUpdateRequest;
use App\Entity\App\Company;
use App\Entity\App\FormTemplate;
use App\Enum\Status;
use App\Repository\CompanyRepository;
use App\Repository\FormTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FormTemplateService
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
     * Obtiene el FormTemplateRepository del tenant actual
     */
    private function getFormTemplateRepository(): FormTemplateRepository
    {
        return $this->getEntityManager()->getRepository(FormTemplate::class);
    }

    /**
     * Obtiene el CompanyRepository del tenant actual
     */
    private function getCompanyRepository(): CompanyRepository
    {
        return $this->getEntityManager()->getRepository(Company::class);
    }

    /**
     * Obtiene todos los formularios activos del tenant actual
     * Usa query directa con el EntityManager del tenant para evitar problemas de multi-tenancy
     */
    public function getActiveFormTemplates(): array
    {
        $em = $this->getEntityManager();

        // Query directa usando el EntityManager del tenant actual
        // No usamos el repositorio porque ServiceEntityRepository usa su propio EM interno
        $results = $em->createQueryBuilder()
            ->select('ft.id, ft.name, ft.description, ft.created_at')
            ->addSelect('COUNT(DISTINCT ftf.id) as fields_count')
            ->addSelect('COUNT(DISTINCT fe.id) as responses_count')
            ->from(FormTemplate::class, 'ft')
            ->leftJoin('ft.formTemplateFields', 'ftf', 'WITH', 'ftf.status = :fieldStatus')
            ->leftJoin('ft.formEntries', 'fe', 'WITH', 'fe.status = :entryStatus')
            ->where('ft.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('fieldStatus', Status::ACTIVE)
            ->setParameter('entryStatus', Status::ACTIVE)
            ->groupBy('ft.id, ft.name, ft.description, ft.created_at')
            ->orderBy('ft.created_at', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Transformar resultados para mantener compatibilidad
        return array_map(function ($row) {
            return [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'created_at' => $row['created_at'],
                'fields_count' => (int) $row['fields_count'],
                'responses_count' => (int) $row['responses_count'],
            ];
        }, $results);
    }

    /**
     * Obtiene todos los formularios activos con empresas cargadas
     * Usa query directa con el EntityManager del tenant
     */
    public function getActiveFormTemplatesWithCompanies(): array
    {
        $em = $this->getEntityManager();

        return $em->createQueryBuilder()
            ->select('ft', 'c')
            ->from(FormTemplate::class, 'ft')
            ->leftJoin('ft.companies', 'c')
            ->where('ft.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ft.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene un formulario por ID
     * Usa query directa con el EntityManager del tenant
     */
    public function getFormTemplateById(int $id): FormTemplate
    {
        $em = $this->getEntityManager();

        $formTemplate = $em->createQueryBuilder()
            ->select('ft')
            ->from(FormTemplate::class, 'ft')
            ->where('ft.id = :id')
            ->andWhere('ft.status != :deletedStatus')
            ->setParameter('id', $id)
            ->setParameter('deletedStatus', Status::DELETED)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$formTemplate) {
            throw new NotFoundHttpException('Formulario no encontrado.');
        }

        return $formTemplate;
    }

    /**
     * Crea un nuevo formulario
     */
    public function createFormTemplate(FormTemplateCreateRequest $dto, ?string $dominio = null): FormTemplate
    {
        // Configurar el tenant si se proporciona el dominio
        if ($dominio) {
            $this->tenantManager->setCurrentTenant($dominio);
        }

        $entityManager = $this->getEntityManager();

        try {
            $entityManager->beginTransaction();

            $formTemplate = new FormTemplate();
            $formTemplate->setName($dto->getName());
            $formTemplate->setDescription($dto->getDescription());
            $formTemplate->setCreatedAt(new \DateTime());
            $formTemplate->setUpdatedAt(new \DateTime());
            $formTemplate->setStatus(Status::ACTIVE);

            // Manejar empresas asignadas usando el EntityManager correcto
            $this->assignCompaniesToFormTemplate($formTemplate, $dto->getCompanyIds(), $entityManager);

            file_put_contents(__DIR__ . '/../../var/log/debug_form.log', "[FormTemplateService] Persisting form template...\n", FILE_APPEND);
            $entityManager->persist($formTemplate);
            
            file_put_contents(__DIR__ . '/../../var/log/debug_form.log', "[FormTemplateService] Flushing...\n", FILE_APPEND);
            $entityManager->flush();
            file_put_contents(__DIR__ . '/../../var/log/debug_form.log', "[FormTemplateService] Flushed. ID: " . $formTemplate->getId() . "\n", FILE_APPEND);

            // Limpiar cache (metodo vacio ahora)
            $this->clearFormTemplatesCache();

            $this->logger->info('Form template created', [
                'id' => $formTemplate->getId(),
                'name' => $formTemplate->getName(),
                'companies' => $formTemplate->getCompanyNames()
            ]);

            file_put_contents(__DIR__ . '/../../var/log/debug_form.log', "[FormTemplateService] Committing transaction...\n", FILE_APPEND);
            $entityManager->commit();
            file_put_contents(__DIR__ . '/../../var/log/debug_form.log', "[FormTemplateService] Committed.\n", FILE_APPEND);

            return $formTemplate;

        } catch (\Exception $e) {
            $entityManager->rollback();

            $this->logger->error('Error creating form template', [
                'error' => $e->getMessage(),
                'name' => $dto->getName()
            ]);

            throw $e;
        }
    }

    /**
     * Actualiza un formulario existente
     */
    public function updateFormTemplate(int $id, FormTemplateUpdateRequest $dto, ?string $dominio = null): FormTemplate
    {
        // Configurar el tenant si se proporciona el dominio
        if ($dominio) {
            $this->tenantManager->setCurrentTenant($dominio);
        }

        $formTemplate = $this->getFormTemplateById($id);
        $entityManager = $this->getEntityManager();

        try {
            $entityManager->beginTransaction();

            $formTemplate->setName($dto->getName());
            $formTemplate->setDescription($dto->getDescription());
            $formTemplate->setUpdatedAt(new \DateTime());

            // Actualizar empresas asignadas usando el EntityManager correcto
            $this->updateFormTemplateCompanies($formTemplate, $dto->getCompanyIds(), $entityManager);

            $entityManager->flush();

            // Limpiar cache (metodo vacio ahora)
            $this->logger->info('About to clear cache after form template update', [
                'form_id' => $formTemplate->getId(),
                'form_name' => $formTemplate->getName()
            ]);
            $this->clearFormTemplatesCache();

            $this->logger->info('Form template updated', [
                'id' => $formTemplate->getId(),
                'name' => $formTemplate->getName(),
                'companies' => $formTemplate->getCompanyNames()
            ]);

            $entityManager->commit();

            return $formTemplate;

        } catch (\Exception $e) {
            $entityManager->rollback();

            $this->logger->error('Error updating form template', [
                'error' => $e->getMessage(),
                'form_id' => $id
            ]);

            throw $e;
        }
    }

    /**
     * Elimina un formulario (soft delete)
     */
    public function deleteFormTemplate(int $id): void
    {
        $formTemplate = $this->getFormTemplateById($id);
        $entityManager = $this->getEntityManager();

        try {
            $entityManager->beginTransaction();

            $formTemplate->setStatus(Status::INACTIVE);
            $formTemplate->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            // Limpiar caché (método vacío ahora)
            $this->clearFormTemplatesCache();

            $this->logger->info('Form template deleted', [
                'id' => $formTemplate->getId(),
                'name' => $formTemplate->getName()
            ]);

            $entityManager->commit();

        } catch (\Exception $e) {
            $entityManager->rollback();
            
            $this->logger->error('Error deleting form template', [
                'error' => $e->getMessage(),
                'form_id' => $id
            ]);

            throw $e;
        }
    }

    /**
     * Busca formularios por nombre
     */
    public function searchFormTemplates(string $searchTerm): array
    {
        return $this->getFormTemplateRepository()->searchByName($searchTerm);
    }

    /**
     * Obtiene estadísticas de formularios
     */
    public function getFormTemplateStats(): array
    {
        return [
            'total_forms' => $this->getFormTemplateRepository()->countActive(),
            'forms_with_fields' => count($this->getFormTemplateRepository()->findWithFields())
        ];
    }

    /**
     * Valida acceso al formulario (ya no necesario con multi-tenant por BD)
     */
    public function validateFormTemplateAccess(FormTemplate $formTemplate): void
    {
        // Ya no es necesario validar tenant - el aislamiento es por base de datos
        // Si el formulario existe en esta BD, el usuario tiene acceso
    }

    /**
     * Duplica un formulario existente
     */
    public function duplicateFormTemplate(int $id, string $newName): FormTemplate
    {
        $originalTemplate = $this->getFormTemplateById($id);
        $entityManager = $this->getEntityManager();

        try {
            $entityManager->beginTransaction();

            $newTemplate = new FormTemplate();
            $newTemplate->setName($newName);
            $newTemplate->setDescription($originalTemplate->getDescription() . ' (Copia)');
            $newTemplate->setCreatedAt(new \DateTime());
            $newTemplate->setUpdatedAt(new \DateTime());
            $newTemplate->setStatus(Status::ACTIVE);

            $entityManager->persist($newTemplate);
            $entityManager->flush();

            // Limpiar caché (método vacío ahora)
            $this->clearFormTemplatesCache();

            $this->logger->info('Form template duplicated', [
                'original_id' => $originalTemplate->getId(),
                'new_id' => $newTemplate->getId(),
                'new_name' => $newName
            ]);

            $entityManager->commit();

            return $newTemplate;

        } catch (\Exception $e) {
            $entityManager->rollback();
            
            $this->logger->error('Error duplicating form template', [
                'error' => $e->getMessage(),
                'original_id' => $id
            ]);

            throw $e;
        }
    }

    /**
     * Asigna empresas a un formulario durante la creacion
     *
     * @param FormTemplate $formTemplate El formulario al que asignar empresas
     * @param array $companyIds IDs de las empresas a asignar
     * @param EntityManagerInterface|null $entityManager EntityManager del tenant correcto
     */
    private function assignCompaniesToFormTemplate(
        FormTemplate $formTemplate,
        array $companyIds,
        ?EntityManagerInterface $entityManager = null
    ): void {
        if (empty($companyIds)) {
            // Array vacio significa que el formulario esta disponible para todas las empresas
            return;
        }

        // Usar el EntityManager proporcionado o el del tenant actual
        $em = $entityManager ?? $this->getEntityManager();
        $companyRepository = $em->getRepository(Company::class);

        foreach ($companyIds as $companyId) {
            // Obtener la empresa del EntityManager correcto para evitar problemas de entidades huerfanas
            $company = $companyRepository->find($companyId);
            if ($company && $company->getStatus() === Status::ACTIVE) {
                $formTemplate->addCompany($company);
            }
        }
    }

    /**
     * Actualiza las empresas asignadas a un formulario
     *
     * @param FormTemplate $formTemplate El formulario a actualizar
     * @param array $companyIds IDs de las nuevas empresas
     * @param EntityManagerInterface|null $entityManager EntityManager del tenant correcto
     */
    private function updateFormTemplateCompanies(
        FormTemplate $formTemplate,
        array $companyIds,
        ?EntityManagerInterface $entityManager = null
    ): void {
        // Remover todas las empresas actuales
        foreach ($formTemplate->getCompanies() as $company) {
            $formTemplate->removeCompany($company);
        }

        // Asignar las nuevas empresas usando el EntityManager correcto
        $this->assignCompaniesToFormTemplate($formTemplate, $companyIds, $entityManager);
    }

    /**
     * Obtiene formularios disponibles para una empresa específica
     */
    public function getFormTemplatesForCompany(Company $company): array
    {
        return $this->getFormTemplateRepository()->findAvailableForCompany($company);
    }

    /**
     * Verifica si un formulario está disponible para una empresa
     */
    public function isFormTemplateAvailableForCompany(int $formTemplateId, Company $company): bool
    {
        return $this->getFormTemplateRepository()->isAvailableForCompany($formTemplateId, $company);
    }

    /**
     * Obtiene todas las empresas activas del sistema
     */
    public function getActiveCompanies(): array
    {
        return $this->getCompanyRepository()->findBy(['status' => Status::ACTIVE], ['name' => 'ASC']);
    }

    /**
     * Limpia el caché de formularios
     */
    public function clearFormTemplatesCache(): void
    {
        // Método mantenido por compatibilidad pero vacío ya que se eliminó el servicio de caché
        $this->logger->info('Form templates cache clear requested (cache disabled)', [
            'tenant' => $this->tenantManager->getCurrentTenant()
        ]);
    }
}
