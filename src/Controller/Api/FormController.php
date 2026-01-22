<?php

namespace App\Controller\Api;

use App\DTO\FormTemplateDTO;
use App\DTO\FormTemplateFieldDTO;
use App\Entity\App\FormEntry;
use App\Entity\App\FormEntryValue;
use App\Entity\App\FormTemplate;
use App\Entity\App\FormTemplateField;
use App\Entity\App\User;
use App\Enum\Status;
use App\Service\FileUploadService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/{dominio}/api/forms')]
class FormController extends AbstractController
{
    private SerializerInterface $serializer;
    private Security $security;
    private CacheInterface $cache;
    private TenantManager $tenantManager;
    private FileUploadService $fileUploadService;

    public function __construct(
        SerializerInterface $serializer,
        Security $security,
        CacheInterface $cache,
        TenantManager $tenantManager,
        FileUploadService $fileUploadService
    ) {
        $this->serializer = $serializer;
        $this->security = $security;
        $this->cache = $cache;
        $this->tenantManager = $tenantManager;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->tenantManager->getEntityManager();
    }

    private function formTemplateToDTO($formTemplate): FormTemplateDTO
    {
        $fields = [];
        foreach ($formTemplate->getFormTemplateFields() as $field) {
            if (method_exists($field, 'getStatus') && $field->getStatus() !== Status::ACTIVE) {
                continue;
            }
            $fields[] = new FormTemplateFieldDTO(
                $field->getId(),
                $field->getLabel(),
                $field->getName(),
                $field->getType(),
                $field->isRequired(),
                $field->getOptions(),
                $field->getPosition(),
                $field->getHelp(),
                $field->isMultiple() ?? false,
                $field->getCols() !== null ? (int)$field->getCols() : null,
                $field->getTextareaCols() !== null ? (int)$field->getTextareaCols() : null
            );
        }
        return new FormTemplateDTO(
            $formTemplate->getId(),
            $formTemplate->getName(),
            $formTemplate->getDescription() ?? '',
            $formTemplate->getCreatedAt()->format('Y-m-d H:i:s'),
            $formTemplate->getUpdatedAt()->format('Y-m-d H:i:s'),
            $fields
        );
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('', name: 'api_forms_index', methods: ['GET'])]
    public function index(string $dominio): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        // Get current user and their company
        $user = $this->security->getUser();
        $userCompany = $user instanceof User ? $user->getCompany() : null;

        // Create cache key that includes user company to avoid showing wrong forms to different users
        $cacheKey = 'forms_index_company_' . ($userCompany ? $userCompany->getId() : 'all');

        error_log("🔍 [API] Checking cache for key: {$cacheKey}");

        // TEMPORAL: Limpiar cache en cada request (SOLO PARA DEBUG - REMOVER EN PRODUCCIÓN)
        $this->cache->delete($cacheKey);
        error_log("🗑️ [API] TEMPORAL: Cache cleared for debug purposes");

        $data = $this->cache->get($cacheKey, function () use ($em, $userCompany, $cacheKey) {
            error_log("💾 [API] Cache MISS for {$cacheKey} - Querying database");
            // Get all active form templates using direct query
            $allFormTemplates = $em->createQueryBuilder()
                ->select('ft')
                ->from('App\Entity\App\FormTemplate', 'ft')
                ->where('ft.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->orderBy('ft.created_at', 'DESC')
                ->getQuery()
                ->getResult();

            // Filter forms based on company access (same logic as UserFormController)
            $accessibleFormTemplates = [];
            foreach ($allFormTemplates as $formTemplate) {
                // If form is available for all companies or specifically for the user's company
                if (!$userCompany || $formTemplate->isAvailableForAllCompanies() ||
                    $formTemplate->isAvailableForCompany($userCompany)) {
                    $accessibleFormTemplates[] = $formTemplate;
                }
            }

            $result = [];
            foreach ($accessibleFormTemplates as $formTemplate) {
                $dto = $this->formTemplateToDTO($formTemplate);
                $result[] = $this->serializer->normalize($dto);
            }
            return $result;
        });

        error_log("✅ [API] Cache HIT for {$cacheKey} - Returning " . count($data) . " forms");

        return new JsonResponse(['forms' => $data]);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/{id}', name: 'api_forms_show', methods: ['GET'])]
    public function show(string $dominio, int $id): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $formTemplate = $em->createQueryBuilder()
            ->select('ft')
            ->from('App\Entity\App\FormTemplate', 'ft')
            ->where('ft.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$formTemplate) {
            return new JsonResponse(['error' => 'Form template not found (no entity)'], Response::HTTP_NOT_FOUND);
        }

        $status = $formTemplate->getStatus();
        $statusValue = $status instanceof Status ? $status->value : $status;

        $dto = $this->formTemplateToDTO($formTemplate);
        $formData = $this->serializer->normalize($dto);

        // Check if current user has already submitted this form
        $user = $this->security->getUser();
        $userSubmission = null;
        $hasSubmitted = false;

        if ($user instanceof User) {
            // Buscar tanto entradas ACTIVE como INACTIVE que tengan valores ACTIVE usando consulta directa
            $existingEntry = $em->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.formTemplate = :formTemplate')
                ->andWhere('fe.user = :user')
                ->andWhere('fe.status = :status')
                ->setParameter('formTemplate', $formTemplate)
                ->setParameter('user', $user)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            // Si no hay entrada ACTIVE, buscar INACTIVE que tenga valores ACTIVE
            if (!$existingEntry) {
                $qb = $em->createQueryBuilder();
                $existingEntry = $qb->select('fe')
                    ->from(FormEntry::class, 'fe')
                    ->join('fe.formEntryValues', 'fev')
                    ->where('fe.formTemplate = :formTemplate')
                    ->andWhere('fe.user = :user')
                    ->andWhere('fe.status = :inactiveStatus')
                    ->andWhere('fev.status = :activeStatus')
                    ->setParameter('formTemplate', $formTemplate)
                    ->setParameter('user', $user)
                    ->setParameter('inactiveStatus', Status::INACTIVE)
                    ->setParameter('activeStatus', Status::ACTIVE)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existingEntry) {
                    error_log("📋 [SHOW] Found INACTIVE FormEntry with ACTIVE values for user {$user->getId()}, form {$formTemplate->getId()}");
                }
            }

            if ($existingEntry) {
                $hasSubmitted = true;

                // AQUÍ ESTÁ LA PARTE NUEVA: Obtener los valores de la respuesta usando consulta directa
                $submissionValues = [];
                $formEntryValues = $em->createQueryBuilder()
                    ->select('fev')
                    ->from('App\Entity\App\FormEntryValue', 'fev')
                    ->where('fev.formEntry = :formEntry')
                    ->andWhere('fev.status = :status')
                    ->setParameter('formEntry', $existingEntry)
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getResult();

                foreach ($formEntryValues as $entryValue) {
                    $fieldId = $entryValue->getFormTemplateField()->getId();
                    $rawValue = $entryValue->getValue();

                    // Manejar valores de archivos de forma inteligente
                    $field = $entryValue->getFormTemplateField();
                    if ($field->getType() === 'file' && $this->isFileValue($rawValue)) {
                        $fileInfo = $this->getFileInfoFromValue($rawValue);

                        // Agregar URL para visualizar el archivo
                        if ($fileInfo && isset($fileInfo['file_path'])) {
                            $pathParts = explode('/', ltrim($fileInfo['file_path'], '/'));

                            // Nueva estructura: /forms/{userId}/{formTemplateId}/{filename}
                            if (count($pathParts) >= 4 && $pathParts[0] === 'forms') {
                                $userId = $pathParts[1];
                                $formTemplateId = $pathParts[2];
                                $filename = $pathParts[3];

                                // Usar el nuevo endpoint de FileController
                                $fileInfo['view_url'] = "/{$dominio}/api/files/{$userId}/{$formTemplateId}/{$filename}";

                                // También verificar si el archivo existe
                                $uploadsDir = $this->getParameter('uploads_directory');
                                $fullPath = $uploadsDir . $fileInfo['file_path'];
                                $fileInfo['file_exists'] = file_exists($fullPath);

                                error_log("📎 [SHOW] File info for field {$fieldId}: " . json_encode([
                                    'file_path' => $fileInfo['file_path'],
                                    'view_url' => $fileInfo['view_url'],
                                    'file_exists' => $fileInfo['file_exists'],
                                    'structure' => 'new'
                                ]));
                            }
                            // Estructura legacy: /uploads/forms/{userId}/{filename}
                            elseif (count($pathParts) >= 4 && $pathParts[0] === 'uploads' && $pathParts[1] === 'forms') {
                                $userId = $pathParts[2];
                                $filename = $pathParts[3];

                                // Usar endpoint legacy
                                $fileInfo['view_url'] = "/{$dominio}/api/files/legacy/{$userId}/{$filename}";

                                // También verificar si el archivo existe
                                $uploadsDir = $this->getParameter('uploads_directory');
                                $fullPath = $uploadsDir . $fileInfo['file_path'];
                                $fileInfo['file_exists'] = file_exists($fullPath);

                                error_log("📎 [SHOW] File info for field {$fieldId}: " . json_encode([
                                    'file_path' => $fileInfo['file_path'],
                                    'view_url' => $fileInfo['view_url'],
                                    'file_exists' => $fileInfo['file_exists'],
                                    'structure' => 'legacy'
                                ]));
                            }
                        }

                        $submissionValues[$fieldId] = $fileInfo;
                    } else {
                        // Para campos normales, intentar decodificar JSON si es válido
                        $decodedValue = json_decode($rawValue, true);
                        $submissionValues[$fieldId] = (json_last_error() === JSON_ERROR_NONE) ? $decodedValue : $rawValue;
                    }
                }

                $userSubmission = [
                    'id' => $existingEntry->getId(),
                    'submitted_at' => $existingEntry->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $existingEntry->getUpdatedAt()->format('Y-m-d H:i:s'),
                    'values' => $submissionValues  // ← ESTO ES LO QUE FALTA
                ];
            }
        }

        $formData['user_submission_status'] = [
            'has_submitted' => $hasSubmitted,
            'can_submit' => !$hasSubmitted,
            'submission' => $userSubmission
        ];

        return new JsonResponse($formData);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/{id}/submit', name: 'api_forms_submit', methods: ['POST'])]
    public function submit(string $dominio, Request $request, int $id): JsonResponse
    {
        try {
            error_log("🚀 FormController: Iniciando submit para formulario {$id}");

            $em = $this->tenantManager->getEntityManager();
            $em->beginTransaction();

            error_log("✅ Tenant configurado: {$dominio}");

            // Asegurar que las entidades estén en el contexto correcto usando consulta directa
            $formTemplate = $em->createQueryBuilder()
                ->select('ft')
                ->from('App\Entity\App\FormTemplate', 'ft')
                ->where('ft.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$formTemplate || $formTemplate->getStatus() !== Status::ACTIVE) {
                return new JsonResponse(['error' => 'Form template not found'], Response::HTTP_NOT_FOUND);
            }

            // Get current user y asegurar que esté en el contexto de persistencia
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
            }

            // Detectar si es multipart/form-data (con archivos) o JSON
            $contentType = $request->headers->get('Content-Type');
            $isMultipart = strpos($contentType, 'multipart/form-data') !== false;

            if ($isMultipart) {
                // Manejar formulario con archivos
                error_log('📎 Procesando formulario con archivos');

                // Obtener valores normales del FormData
                $valuesJson = $request->request->get('values');
                if (!$valuesJson) {
                    return new JsonResponse(['error' => 'Values are required in multipart request'], Response::HTTP_BAD_REQUEST);
                }

                $values = json_decode($valuesJson, true);
                if (!is_array($values)) {
                    return new JsonResponse(['error' => 'Invalid values format'], Response::HTTP_BAD_REQUEST);
                }

                error_log('📄 Valores normales: ' . print_r($values, true));

                // Procesar archivos subidos
                $uploadedFiles = $request->files->all();
                error_log('📎 Archivos recibidos: ' . print_r(array_keys($uploadedFiles), true));

                // Procesar cada archivo con nueva estructura mejorada
                foreach ($uploadedFiles as $fileKey => $uploadedFile) {
                    if ($uploadedFile && strpos($fileKey, 'file_') === 0) {
                        // Extraer el ID del campo del nombre del archivo
                        $fieldId = str_replace('file_', '', $fileKey);

                        error_log("📎 FormController: Procesando archivo para campo {$fieldId}");
                        error_log("📄 Archivo recibido: " . $uploadedFile->getClientOriginalName());

                        // Verificar si el archivo es válido
                        if (!$uploadedFile->isValid()) {
                            error_log("❌ Archivo no válido: " . $uploadedFile->getErrorMessage());
                            return new JsonResponse([
                                'error' => 'Invalid file uploaded',
                                'message' => $uploadedFile->getErrorMessage(),
                                'field_id' => $fieldId
                            ], Response::HTTP_BAD_REQUEST);
                        }

                        // Usar el nuevo FileUploadService con estructura mejorada: uploads/{userId}/{formTemplateId}/
                        $fileInfo = $this->fileUploadService->uploadFile(
                            $uploadedFile,
                            $user->getId(),
                            $formTemplate->getId()
                        );

                        if ($fileInfo) {
                            // Guardar información estructurada en los valores
                            $values[$fieldId] = $fileInfo;
                            error_log("✅ FormController: Archivo guardado con nueva estructura: " . $fileInfo['file_path']);
                        } else {
                            error_log("❌ FormController: Error al procesar archivo con FileUploadService");
                            return new JsonResponse([
                                'error' => 'Error uploading file',
                                'message' => 'File upload service failed',
                                'field_id' => $fieldId,
                                'file_name' => $uploadedFile->getClientOriginalName()
                            ], Response::HTTP_INTERNAL_SERVER_ERROR);
                        }
                    }
                }

                $data = ['values' => $values];
            } else {
                // Manejar formulario JSON normal
                error_log('📄 Procesando formulario JSON');
                $data = json_decode($request->getContent(), true);

                if (!isset($data['values']) || !is_array($data['values'])) {
                    return new JsonResponse(['error' => 'Values are required'], Response::HTTP_BAD_REQUEST);
                }

                // Verificar si hay content:// URIs en los valores
                foreach ($data['values'] as $fieldId => $value) {
                    if (is_string($value) && strpos($value, 'content://') === 0) {
                        error_log("⚠️ Content URI detectado en campo {$fieldId}: {$value}");
                        return new JsonResponse([
                            'error' => 'Android content URI detected',
                            'message' => 'Files must be uploaded as multipart/form-data, not as content:// URIs',
                            'field_id' => $fieldId,
                            'content_uri' => $value,
                            'solution' => 'Please ensure your React Native app converts the file to a proper File object before uploading'
                        ], Response::HTTP_BAD_REQUEST);
                    }
                }
            }

            error_log('📋 Datos finales a procesar: ' . print_r($data['values'], true));

            // Refrescar las entidades en el contexto actual del EntityManager
            $formTemplate = $em->find(FormTemplate::class, $formTemplate->getId());
            $user = $em->find(User::class, $user->getId());
        } catch (\Exception $e) {
            error_log('❌ Error durante inicialización: ' . $e->getMessage());
            return new JsonResponse([
                'error' => 'Server error during initialization',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            // Check if user has already submitted this form using direct query
            $existingEntry = $em->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.formTemplate = :formTemplate')
                ->andWhere('fe.user = :user')
                ->andWhere('fe.status = :status')
                ->setParameter('formTemplate', $formTemplate)
                ->setParameter('user', $user)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingEntry) {
                return new JsonResponse([
                    'error' => 'You have already submitted this form',
                    'existing_submission_id' => $existingEntry->getId(),
                    'submitted_at' => $existingEntry->getCreatedAt()->format('Y-m-d H:i:s')
                ], Response::HTTP_CONFLICT);
            }

            // Create form entry
            $formEntry = new FormEntry();
            $formEntry->setFormTemplate($formTemplate);
            $formEntry->setUser($user);
            $formEntry->setCreatedAt(new \DateTime());
            $formEntry->setUpdatedAt(new \DateTime());
            $formEntry->setStatus(Status::ACTIVE);

            $em->persist($formEntry);
            error_log("🔄 FormEntry persistido para formulario {$formTemplate->getId()}, usuario {$user->getId()}");

            // Flush para obtener el ID del FormEntry
            $em->flush();
            error_log("✅ FormEntry guardado con ID: " . $formEntry->getId());

            // Create form entry values using direct queries
            foreach ($data['values'] as $fieldId => $value) {
                $field = $em->createQueryBuilder()
                    ->select('ftf')
                    ->from('App\Entity\App\FormTemplateField', 'ftf')
                    ->where('ftf.id = :id')
                    ->setParameter('id', $fieldId)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (!$field || $field->getFormTemplate()->getId() !== $formTemplate->getId() || $field->getStatus() !== Status::ACTIVE) {
                    continue; // Skip invalid fields
                }

                // Asegurar que el campo esté en el contexto correcto
                $field = $em->find(FormTemplateField::class, $field->getId());

                // Check if required field is empty
                if ($field->isRequired() && empty($value)) {
                    $em->rollback();
                    return new JsonResponse(['error' => 'Field ' . $field->getLabel() . ' is required'], Response::HTTP_BAD_REQUEST);
                }

                $formEntryValue = new FormEntryValue();
                $formEntryValue->setFormEntry($formEntry);
                $formEntryValue->setFormTemplateField($field);

                // Manejo inteligente según el tipo de campo
                if ($field->getType() === 'file' && is_array($value) && isset($value['file_path'])) {
                    // Es un archivo - guardar JSON estructurado
                    $formEntryValue->setValue(json_encode($value));
                    error_log("💾 Guardando archivo en BD: " . json_encode($value));
                } else {
                    // No es archivo - guardar valor normal
                    $formEntryValue->setValue(is_array($value) ? json_encode($value) : $value);
                    error_log("💾 Guardando valor normal en BD: " . (is_array($value) ? json_encode($value) : $value));
                }

                $formEntryValue->setStatus(Status::ACTIVE);
                $em->persist($formEntryValue);
                error_log("🔄 FormEntryValue persistido para campo {$field->getId()}: " . $formEntryValue->getValue());
            }

            error_log("💾 Antes del flush - Total de FormEntryValues a guardar: " . count($data['values']));

            // Commit la transacción
            $em->flush();
            error_log("✅ Flush completado exitosamente");

            $em->commit();
            error_log("✅ Transacción commitada exitosamente");

            // Borrar cache relevante
            $this->cache->delete('forms_index');
            $this->cache->delete('form_show_' . $id);

            return new JsonResponse([
                'id' => $formEntry->getId(),
                'form_template_id' => $formTemplate->getId(),
                'created_at' => $formEntry->getCreatedAt()->format('Y-m-d H:i:s'),
                'message' => 'Form submitted successfully'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $em->rollback();
            error_log('❌ Error al enviar formulario: ' . $e->getMessage());

            return new JsonResponse([
                'error' => 'Failed to submit form',
                'message' => $e->getMessage(),
                'details' => [
                    'form_id' => $id,
                    'user_id' => $user ? $user->getId() : null,
                    'error_type' => get_class($e)
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/{id}/my-submission', name: 'api_forms_my_submission', methods: ['GET'])]
    public function getMySubmission(int $id): JsonResponse
    {
        $entityManager = $this->getEntityManager();
        $formTemplate = $entityManager->createQueryBuilder()
            ->select('ft')
            ->from('App\Entity\App\FormTemplate', 'ft')
            ->where('ft.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$formTemplate || $formTemplate->getStatus() !== Status::ACTIVE) {
            return new JsonResponse(['error' => 'Form template not found'], Response::HTTP_NOT_FOUND);
        }

        // Get current user
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Find user's submission for this form using direct query
        $formEntry = $entityManager->createQueryBuilder()
            ->select('fe')
            ->from('App\Entity\App\FormEntry', 'fe')
            ->where('fe.formTemplate = :formTemplate')
            ->andWhere('fe.user = :user')
            ->andWhere('fe.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('user', $user)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$formEntry) {
            return new JsonResponse(['error' => 'No submission found for this form'], Response::HTTP_NOT_FOUND);
        }

        // Get form entry values using direct query
        $formValues = $entityManager->createQueryBuilder()
            ->select('fev')
            ->from('App\Entity\App\FormEntryValue', 'fev')
            ->where('fev.formEntry = :formEntry')
            ->andWhere('fev.status = :status')
            ->setParameter('formEntry', $formEntry)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getResult();

        $submissionData = [
            'id' => $formEntry->getId(),
            'form_template' => [
                'id' => $formTemplate->getId(),
                'name' => $formTemplate->getName(),
                'description' => $formTemplate->getDescription(),
            ],
            'submitted_at' => $formEntry->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $formEntry->getUpdatedAt()->format('Y-m-d H:i:s'),
            'status' => $formEntry->getStatus()->value,
            'values' => [],
            'fields' => []
        ];

        // Organize values by field
        foreach ($formValues as $value) {
            $field = $value->getFormTemplateField();
            $fieldId = $field->getId();

            $submissionData['values'][$fieldId] = $value->getValue();
            $submissionData['fields'][] = [
                'field_id' => $fieldId,
                'field_name' => $field->getName(),
                'field_label' => $field->getLabel(),
                'field_type' => $field->getType(),
                'field_required' => $field->getIsRequired(),
                'field_options' => $field->getOptions(),
                'value' => $value->getValue(),
                'sort_order' => $field->getSortOrder()
            ];
        }

        // Sort fields by sort_order
        usort($submissionData['fields'], function($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        return new JsonResponse($submissionData);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/available', name: 'api_forms_available', methods: ['GET'])]
    public function available(string $dominio): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        // Get current user and their company
        $user = $this->security->getUser();
        $userCompany = $user instanceof User ? $user->getCompany() : null;

        // Get all active form templates using direct query
        $allFormTemplates = $em->createQueryBuilder()
            ->select('ft')
            ->from('App\Entity\App\FormTemplate', 'ft')
            ->where('ft.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ft.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        // Filter forms based on company access
        $data = [];
        foreach ($allFormTemplates as $formTemplate) {
            // If form is available for all companies or specifically for the user's company
            if (!$userCompany || $formTemplate->isAvailableForAllCompanies() ||
                $formTemplate->isAvailableForCompany($userCompany)) {
                $data[] = [
                    'id' => $formTemplate->getId(),
                    'name' => $formTemplate->getName(),
                    'description' => $formTemplate->getDescription(),
                ];
            }
        }

        return new JsonResponse(['forms' => $data]);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/{id}/submissions', name: 'api_forms_submissions', methods: ['GET'])]
    public function submissions(int $id): JsonResponse
    {
        $entityManager = $this->getEntityManager();
        $formTemplate = $entityManager->createQueryBuilder()
            ->select('ft')
            ->from('App\Entity\App\FormTemplate', 'ft')
            ->where('ft.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$formTemplate || $formTemplate->getStatus() !== Status::ACTIVE) {
            return new JsonResponse(['error' => 'Form template not found'], Response::HTTP_NOT_FOUND);
        }

        $submissions = $this->entityManager->createQueryBuilder()
            ->select('fe')
            ->from('App\Entity\App\FormEntry', 'fe')
            ->where('fe.formTemplate = :formTemplate')
            ->andWhere('fe.status = :status')
            ->setParameter('formTemplate', $formTemplate)
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('fe.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($submissions as $submission) {
            $data[] = [
                'id' => $submission->getId(),
                'user' => $submission->getUser() ? [
                    'id' => $submission->getUser()->getId(),
                    'email' => $submission->getUser()->getEmail(),
                ] : null,
                'created_at' => $submission->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $submission->getUpdatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse(['submissions' => $data]);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/submissions/{id}', name: 'api_forms_submission_show', methods: ['GET'])]
    public function showSubmission(int $id): JsonResponse
    {
        $entityManager = $this->getEntityManager();
        $submission = $entityManager->createQueryBuilder()
            ->select('fe')
            ->from('App\Entity\App\FormEntry', 'fe')
            ->where('fe.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$submission || $submission->getStatus() !== Status::ACTIVE) {
            return new JsonResponse(['error' => 'Submission not found'], Response::HTTP_NOT_FOUND);
        }

        $values = [];
        foreach ($submission->getFormEntryValues() as $value) {
            if ($value->getStatus() === Status::ACTIVE) {
                $field = $value->getFormTemplateField();
                $values[] = [
                    'field_id' => $field->getId(),
                    'field_label' => $field->getLabel(),
                    'field_name' => $field->getName(),
                    'field_type' => $field->getType(),
                    'value' => $value->getValue(),
                ];
            }
        }

        return new JsonResponse([
            'id' => $submission->getId(),
            'form_template' => [
                'id' => $submission->getFormTemplate()->getId(),
                'name' => $submission->getFormTemplate()->getName(),
            ],
            'user' => $submission->getUser() ? [
                'id' => $submission->getUser()->getId(),
                'email' => $submission->getUser()->getEmail(),
            ] : null,
            'created_at' => $submission->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $submission->getUpdatedAt()->format('Y-m-d H:i:s'),
            'values' => $values,
        ]);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/submissions/{id}/approve', name: 'api_forms_submission_approve', methods: ['PUT'])]
    public function approveSubmission(int $id): JsonResponse
    {
        $entityManager = $this->getEntityManager();
        $submission = $entityManager->createQueryBuilder()
            ->select('fe')
            ->from('App\Entity\App\FormEntry', 'fe')
            ->where('fe.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$submission || $submission->getStatus() !== Status::ACTIVE) {
            return new JsonResponse(['error' => 'Submission not found'], Response::HTTP_NOT_FOUND);
        }

        // Here you would implement the approval logic
        // For now, we'll just update the status
        $submission->setStatus(Status::ACTIVE);
        $submission->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        return new JsonResponse([
            'id' => $submission->getId(),
            'status' => 'approved',
            'updated_at' => $submission->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/submissions/{id}/reject', name: 'api_forms_submission_reject', methods: ['PUT'])]
    public function rejectSubmission(int $id): JsonResponse
    {
        $entityManager = $this->getEntityManager();
        $submission = $entityManager->createQueryBuilder()
            ->select('fe')
            ->from('App\Entity\App\FormEntry', 'fe')
            ->where('fe.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$submission || $submission->getStatus() !== Status::ACTIVE) {
            return new JsonResponse(['error' => 'Submission not found'], Response::HTTP_NOT_FOUND);
        }

        // Here you would implement the rejection logic
        // For now, we'll just update the status
        $submission->setStatus(Status::INACTIVE);
        $submission->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        return new JsonResponse([
            'id' => $submission->getId(),
            'status' => 'rejected',
            'updated_at' => $submission->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/stats', name: 'api_forms_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $formTemplates = $this->entityManager->createQueryBuilder()
            ->select('ft')
            ->from('App\Entity\App\FormTemplate', 'ft')
            ->where('ft.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getResult();

        $stats = [
            'total_forms' => count($formTemplates),
            'total_submissions' => 0,
            'forms' => [],
        ];

        foreach ($formTemplates as $formTemplate) {
            $submissions = $this->entityManager->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.formTemplate = :formTemplate')
                ->setParameter('formTemplate', $formTemplate)
                ->getQuery()
                ->getResult();

            $formStats = [
                'id' => $formTemplate->getId(),
                'name' => $formTemplate->getName(),
                'submissions_count' => count($submissions),
            ];

            $stats['total_submissions'] += count($submissions);
            $stats['forms'][] = $formStats;
        }

        return new JsonResponse(['stats' => $stats]);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    #[Route('/{id}/stats', name: 'api_forms_stats_show', methods: ['GET'])]
    public function showStats(int $id): JsonResponse
    {
        $entityManager = $this->getEntityManager();
        $formTemplate = $entityManager->createQueryBuilder()
            ->select('ft')
            ->from('App\Entity\App\FormTemplate', 'ft')
            ->where('ft.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$formTemplate || $formTemplate->getStatus() !== Status::ACTIVE) {
            return new JsonResponse(['error' => 'Form template not found'], Response::HTTP_NOT_FOUND);
        }

        $submissions = $this->entityManager->createQueryBuilder()
            ->select('fe')
            ->from('App\Entity\App\FormEntry', 'fe')
            ->where('fe.formTemplate = :formTemplate')
            ->setParameter('formTemplate', $formTemplate)
            ->getQuery()
            ->getResult();

        $stats = [
            'id' => $formTemplate->getId(),
            'name' => $formTemplate->getName(),
            'submissions_count' => count($submissions),
            'fields_count' => $formTemplate->getFormTemplateFields()->count(),
            'created_at' => $formTemplate->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        return new JsonResponse(['stats' => $stats]);
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Procesa archivos subidos y los guarda organizados por ID de usuario
     */
    private function processUploadedFiles(Request $request, int $userId): array
    {
        $uploadedFiles = [];
        $uploadsDir = $this->getParameter('uploads_directory');

        // Crear directorio específico para el usuario: /forms/userId/
        $userUploadDir = $uploadsDir . '/forms/' . $userId;
        if (!is_dir($userUploadDir)) {
            if (!mkdir($userUploadDir, 0755, true)) {
                error_log("❌ Error creando directorio: {$userUploadDir}");
                throw new \Exception("No se pudo crear el directorio de uploads para el usuario");
            }
            error_log("📁 Directorio creado: {$userUploadDir}");
        }

        // Procesar cada archivo subido
        foreach ($request->files->all() as $key => $file) {
            if ($file && $file->isValid()) {
                // Extraer el ID del campo del nombre del archivo (ej: file_11 -> 11)
                if (preg_match('/^file_(\d+)$/', $key, $matches)) {
                    $fieldId = (int)$matches[1];

                    // Generar nombre único para el archivo
                    $originalName = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $uniqueName = uniqid() . '_' . time() . '.' . $extension;

                    // Validar extensión (opcional - agregar más validaciones según necesites)
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
                    if (!in_array(strtolower($extension), $allowedExtensions)) {
                        error_log("❌ Extensión no permitida: {$extension}");
                        continue;
                    }

                    try {
                        // Mover archivo al directorio del usuario
                        $file->move($userUploadDir, $uniqueName);

                        // Guardar ruta relativa (desde public/)
                        $relativePath = 'uploads/forms/' . $userId . '/' . $uniqueName;
                        $uploadedFiles[$fieldId] = $relativePath;

                        error_log("✅ Archivo guardado: {$relativePath}");
                        error_log("📄 Original: {$originalName} -> Nuevo: {$uniqueName}");

                    } catch (\Exception $e) {
                        error_log("❌ Error moviendo archivo {$originalName}: " . $e->getMessage());
                        throw new \Exception("Error al guardar el archivo: " . $originalName);
                    }
                } else {
                    error_log("⚠️ Nombre de archivo no válido: {$key}");
                }
            } else {
                error_log("❌ Archivo no válido o corrupto: {$key}");
            }
        }

        return $uploadedFiles;
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Endpoint para visualizar archivos subidos de forma segura
     * Soporta tanto estructura antigua como nueva: uploads/forms/{userId}/{formTemplateId}/{filename}
     */
    #[Route('/files/{userId}/{filename}', name: 'api_forms_view_file', methods: ['GET'])]
    #[Route('/files/{userId}/{formTemplateId}/{filename}', name: 'api_forms_view_file_new', methods: ['GET'])]
    public function viewFile(string $dominio, int $userId, string $filename, ?int $formTemplateId = null): Response
    {
        try {

            // Verificar que el usuario actual tenga permisos para ver este archivo
            $currentUser = $this->security->getUser();
            if (!$currentUser instanceof User) {
                return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
            }

            // Solo permitir que los usuarios vean sus propios archivos (o admins)
            if ($currentUser->getId() !== $userId && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
                return new Response('Forbidden', Response::HTTP_FORBIDDEN);
            }

            // Construir la ruta del archivo (soporta estructura antigua y nueva)
            $uploadsDir = $this->getParameter('uploads_directory');

            if ($formTemplateId) {
                // Nueva estructura: uploads/forms/{userId}/{formTemplateId}/{filename}
                $filePath = $uploadsDir . '/forms/' . $userId . '/' . $formTemplateId . '/' . $filename;
                $allowedDir = realpath($uploadsDir . '/forms/' . $userId . '/' . $formTemplateId);
            } else {
                // Estructura antigua: uploads/forms/{userId}/{filename}
                $filePath = $uploadsDir . '/forms/' . $userId . '/' . $filename;
                $allowedDir = realpath($uploadsDir . '/forms/' . $userId);
            }

            // Verificar que el archivo existe y está dentro del directorio permitido
            if (!file_exists($filePath) || !is_file($filePath)) {
                return new Response('File not found', Response::HTTP_NOT_FOUND);
            }

            // Verificar que el archivo está dentro del directorio de uploads (seguridad)
            $realFilePath = realpath($filePath);

            if (!$realFilePath || !$allowedDir || strpos($realFilePath, $allowedDir) !== 0) {
                return new Response('Invalid file path', Response::HTTP_FORBIDDEN);
            }

            // Determinar el tipo MIME
            $mimeType = mime_content_type($filePath);
            if (!$mimeType) {
                $mimeType = 'application/octet-stream';
            }

            // Leer el archivo
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                return new Response('Error reading file', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Crear respuesta con headers apropiados
            $response = new Response($fileContent);
            $response->headers->set('Content-Type', $mimeType);
            $response->headers->set('Content-Length', (string) filesize($filePath));

            // Para imágenes, permitir visualización en el navegador
            if (strpos($mimeType, 'image/') === 0) {
                $response->headers->set('Content-Disposition', 'inline; filename="' . basename($filename) . '"');
            } else {
                // Para otros archivos, forzar descarga
                $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($filename) . '"');
            }

            // Headers de cache
            $response->headers->set('Cache-Control', 'private, max-age=3600');
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

            error_log("✅ Archivo servido: {$filePath} ({$mimeType})");

            return $response;

        } catch (\Exception $e) {
            error_log("❌ Error sirviendo archivo: " . $e->getMessage());
            return new Response('Internal server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Obtener información de archivos de una respuesta específica
     */
    #[Route('/submissions/{id}/files', name: 'api_forms_submission_files', methods: ['GET'])]
    public function getSubmissionFiles(string $dominio, int $id): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            // Buscar la respuesta del formulario usando consulta directa
            $formEntry = $em->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$formEntry || $formEntry->getStatus() !== Status::ACTIVE) {
                return new JsonResponse(['error' => 'Form submission not found'], Response::HTTP_NOT_FOUND);
            }

            // Verificar permisos
            $currentUser = $this->security->getUser();
            if (!$currentUser instanceof User) {
                return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
            }

            // Solo permitir ver archivos propios o si es admin
            if ($formEntry->getUser()->getId() !== $currentUser->getId() && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
                return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            // Obtener valores que son archivos usando consulta directa
            $fileValues = [];
            $formEntryValues = $em->createQueryBuilder()
                ->select('fev')
                ->from('App\Entity\App\FormEntryValue', 'fev')
                ->where('fev.formEntry = :formEntry')
                ->andWhere('fev.status = :status')
                ->setParameter('formEntry', $formEntry)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getResult();

            foreach ($formEntryValues as $value) {
                $field = $value->getFormTemplateField();

                // Solo procesar campos de tipo archivo
                if ($field->getType() === 'file' && !empty($value->getValue())) {
                    $fileData = $this->getFileInfoFromValue($value->getValue());

                    if ($fileData) {
                        $uploadsDir = $this->getParameter('uploads_directory');
                        $fullPath = $uploadsDir . $fileData['file_path'];

                        $fileInfo = [
                            'field_id' => $field->getId(),
                            'field_label' => $field->getLabel(),
                            'field_name' => $field->getName(),
                            'file_path' => $fileData['file_path'],
                            'original_name' => $fileData['original_name'],
                            'file_name' => $fileData['file_name'] ?? basename($fileData['file_path']),
                            'file_size' => $fileData['file_size'],
                            'mime_type' => $fileData['mime_type'],
                            'uploaded_at' => $fileData['uploaded_at'],
                            'file_exists' => file_exists($fullPath),
                            'view_url' => null,
                            'is_legacy' => isset($fileData['legacy_format'])
                        ];

                        // Si el archivo existe y no tenemos info completa, obtenerla del filesystem
                        if ($fileInfo['file_exists']) {
                            if (!$fileInfo['file_size']) {
                                $fileInfo['file_size'] = filesize($fullPath);
                            }
                            if (!$fileInfo['mime_type']) {
                                $fileInfo['mime_type'] = mime_content_type($fullPath) ?: 'application/octet-stream';
                            }

                            // Construir URL para visualizar el archivo
                            $pathParts = explode('/', ltrim($fileData['file_path'], '/'));
                            if (count($pathParts) >= 3 && $pathParts[0] === 'uploads' && $pathParts[1] === 'forms') {
                                $userId = $pathParts[2];
                                $filename = $pathParts[3];
                                $fileInfo['view_url'] = "/{$dominio}/api/forms/test-files/{$userId}/{$filename}";
                            }
                        }

                        $fileValues[] = $fileInfo;
                    }
                }
            }

            return new JsonResponse([
                'submission_id' => $formEntry->getId(),
                'form_template_id' => $formEntry->getFormTemplate()->getId(),
                'form_name' => $formEntry->getFormTemplate()->getName(),
                'user_id' => $formEntry->getUser()->getId(),
                'files' => $fileValues,
                'total_files' => count($fileValues)
            ]);

        } catch (\Exception $e) {
            error_log("❌ Error obteniendo archivos de respuesta: " . $e->getMessage());
            return new JsonResponse([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Endpoint temporal para probar visualización de archivos (SIN AUTENTICACIÓN - SOLO PARA PRUEBAS)
     */
    #[Route('/test-files/{userId}/{filename}', name: 'api_forms_test_view_file', methods: ['GET'])]
    public function testViewFile(string $dominio, int $userId, string $filename): Response
    {
        try {
            // Construir la ruta del archivo
            $uploadsDir = $this->getParameter('uploads_directory');
            $filePath = $uploadsDir . '/forms/' . $userId . '/' . $filename;

            // Verificar que el archivo existe
            if (!file_exists($filePath) || !is_file($filePath)) {
                return new Response('File not found: ' . $filePath, Response::HTTP_NOT_FOUND);
            }

            // Determinar el tipo MIME
            $mimeType = mime_content_type($filePath);
            if (!$mimeType) {
                $mimeType = 'application/octet-stream';
            }

            // Leer el archivo
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                return new Response('Error reading file', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Crear respuesta con headers apropiados
            $response = new Response($fileContent);
            $response->headers->set('Content-Type', $mimeType);
            $response->headers->set('Content-Length', (string) filesize($filePath));

            // Para imágenes, permitir visualización en el navegador
            if (strpos($mimeType, 'image/') === 0) {
                $response->headers->set('Content-Disposition', 'inline; filename="' . basename($filename) . '"');
            } else {
                $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($filename) . '"');
            }

            // Headers para permitir CORS y cache
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Cache-Control', 'public, max-age=3600');

            error_log("✅ Archivo de prueba servido: {$filePath} ({$mimeType})");

            return $response;

        } catch (\Exception $e) {
            error_log("❌ Error sirviendo archivo de prueba: " . $e->getMessage());
            return new Response('Internal server error: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Listar todos los archivos de un usuario (TEMPORAL - SOLO PARA PRUEBAS)
     */
    #[Route('/test-files/{userId}', name: 'api_forms_test_list_files', methods: ['GET'])]
    public function testListUserFiles(string $dominio, int $userId): JsonResponse
    {
        try {
            $uploadsDir = $this->getParameter('uploads_directory');
            $userDir = $uploadsDir . '/forms/' . $userId;

            if (!is_dir($userDir)) {
                return new JsonResponse([
                    'user_id' => $userId,
                    'directory' => $userDir,
                    'files' => [],
                    'message' => 'No files directory found for user'
                ]);
            }

            $files = [];
            $fileList = scandir($userDir);

            foreach ($fileList as $filename) {
                if ($filename === '.' || $filename === '..') {
                    continue;
                }

                $filePath = $userDir . '/' . $filename;
                if (is_file($filePath)) {
                    $fileInfo = [
                        'filename' => $filename,
                        'size' => filesize($filePath),
                        'size_human' => $this->formatBytes(filesize($filePath)),
                        'mime_type' => mime_content_type($filePath) ?: 'unknown',
                        'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'view_url' => "/{$dominio}/api/forms/test-files/{$userId}/{$filename}",
                        'is_image' => strpos(mime_content_type($filePath) ?: '', 'image/') === 0
                    ];
                    $files[] = $fileInfo;
                }
            }

            return new JsonResponse([
                'user_id' => $userId,
                'directory' => $userDir,
                'total_files' => count($files),
                'files' => $files
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error listing files',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Formatear bytes en formato legible
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Obtener información de archivo desde el valor almacenado en BD
     */
    private function getFileInfoFromValue(string $value): ?array
    {
        // Intentar decodificar como JSON (nuevo formato)
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['file_path'])) {
            // Es el nuevo formato JSON estructurado
            return $decoded;
        }

        // Es el formato antiguo (solo ruta) - convertir a nuevo formato
        if (strpos($value, '/uploads/forms/') === 0) {
            $pathParts = explode('/', $value);
            $fileName = end($pathParts);

            return [
                'file_path' => $value,
                'original_name' => $fileName,
                'file_name' => $fileName,
                'file_size' => null,
                'mime_type' => null,
                'uploaded_at' => null,
                'user_id' => null,
                'legacy_format' => true
            ];
        }

        return null;
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Verificar si un valor representa un archivo
     */
    private function isFileValue(string $value): bool
    {
        // Verificar si es JSON con file_path
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['file_path'])) {
            return true;
        }

        // Verificar si es ruta de archivo (formato legacy)
        return strpos($value, '/uploads/forms/') === 0;
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Endpoint para ver los datos raw de una respuesta (TEMPORAL - PARA DEBUG)
     */
    #[Route('/debug/submission/{id}', name: 'api_forms_debug_submission', methods: ['GET'])]
    public function debugSubmission(string $dominio, int $id): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $formEntry = $em->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$formEntry) {
                return new JsonResponse(['error' => 'Form submission not found'], Response::HTTP_NOT_FOUND);
            }

            $formEntryValues = $em->createQueryBuilder()
                ->select('fev')
                ->from('App\Entity\App\FormEntryValue', 'fev')
                ->where('fev.formEntry = :formEntry')
                ->andWhere('fev.status = :status')
                ->setParameter('formEntry', $formEntry)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getResult();

            $debugData = [
                'submission_id' => $formEntry->getId(),
                'form_name' => $formEntry->getFormTemplate()->getName(),
                'user_id' => $formEntry->getUser()->getId(),
                'created_at' => $formEntry->getCreatedAt()->format('Y-m-d H:i:s'),
                'values' => []
            ];

            foreach ($formEntryValues as $value) {
                $field = $value->getFormTemplateField();
                $rawValue = $value->getValue();

                $valueInfo = [
                    'field_id' => $field->getId(),
                    'field_name' => $field->getName(),
                    'field_label' => $field->getLabel(),
                    'field_type' => $field->getType(),
                    'raw_value' => $rawValue,
                    'is_json' => $this->isValidJson($rawValue),
                    'is_file' => $field->getType() === 'file',
                    'is_file_value' => $this->isFileValue($rawValue)
                ];

                // Si es un archivo, agregar información decodificada
                if ($field->getType() === 'file' && $this->isFileValue($rawValue)) {
                    $valueInfo['decoded_file_info'] = $this->getFileInfoFromValue($rawValue);
                }

                $debugData['values'][] = $valueInfo;
            }

            return new JsonResponse($debugData);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Debug error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Verificar si una cadena es JSON válido
     */
    private function isValidJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Endpoint para limpiar cache de formularios (TEMPORAL - SOLO PARA DEBUG)
     */
    #[Route('/clear-cache', name: 'api_forms_clear_cache', methods: ['POST'])]
    public function clearCache(string $dominio): JsonResponse
    {
        try {

            // Limpiar todos los caches relacionados con formularios
            $cacheKeys = [
                'forms_index',
                'forms_index_company_1',
                'forms_index_company_2',
                'forms_index_company_3',
                'forms_index_company_4',
                'forms_index_company_all'
            ];

            $clearedKeys = [];
            foreach ($cacheKeys as $key) {
                if ($this->cache->delete($key)) {
                    $clearedKeys[] = $key;
                }
            }

            return new JsonResponse([
                'message' => 'Cache cleared successfully',
                'cleared_keys' => $clearedKeys,
                'total_cleared' => count($clearedKeys)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error clearing cache',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */

}
