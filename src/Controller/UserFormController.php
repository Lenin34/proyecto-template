<?php

namespace App\Controller;

use App\Entity\App\FormEntry;
use App\Entity\App\FormEntryValue;
use App\Entity\App\FormTemplate;
use App\Enum\Status;
use App\Service\FormDebugService;
use App\Service\FormFieldTypeResolver;
use App\Service\TenantManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{dominio}/forms')]
class UserFormController extends AbstractController
{
    private TenantManager $tenantManager;
    private FormDebugService $formDebugService;
    private FormFieldTypeResolver $typeResolver;
    private LoggerInterface $logger;

    public function __construct(
        TenantManager $tenantManager,
        FormDebugService $formDebugService,
        FormFieldTypeResolver $typeResolver,
        LoggerInterface $logger
    ) {
        $this->tenantManager = $tenantManager;
        $this->formDebugService = $formDebugService;
        $this->typeResolver = $typeResolver;
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_user_forms_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            // Get the current user
            $user = $this->getUser();
            $userCompany = $user ? $user->getCompany() : null;

            // Get all active form templates
            $allFormTemplates = $entityManager
                ->getRepository(FormTemplate::class)
                ->findBy(['status' => Status::ACTIVE], ['created_at' => 'DESC']);

            // Filter forms based on company access
            $accessibleFormTemplates = [];
            foreach ($allFormTemplates as $formTemplate) {
                // If form is available for all companies or specifically for the user's company
                if (!$userCompany || $formTemplate->isAvailableForAllCompanies() || 
                    $formTemplate->isAvailableForCompany($userCompany)) {
                    $accessibleFormTemplates[] = $formTemplate;
                }
            }

            $this->logger->info('[FORM_ACCESS_FILTERED]', [
                'user_id' => $user ? $user->getId() : 'anonymous',
                'company_id' => $userCompany ? $userCompany->getId() : 'none',
                'total_forms' => count($allFormTemplates),
                'accessible_forms' => count($accessibleFormTemplates)
            ]);

            return $this->render('user_form/index.html.twig', [
                'form_templates' => $accessibleFormTemplates,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/{id}', name: 'app_user_forms_show', methods: ['GET', 'POST'])]
    public function show(string $dominio, Request $request, int $id): Response
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $formTemplate = $entityManager->createQueryBuilder()
                ->select('ft')
                ->from('App\Entity\App\FormTemplate', 'ft')
                ->where('ft.id = :id')
                ->andWhere('ft.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$formTemplate) {
                throw $this->createNotFoundException('Form template not found');
            }

            // Check if the form is active
            if ($formTemplate->getStatus() !== Status::ACTIVE) {
                throw $this->createNotFoundException('Form not found or inactive');
            }

            // Get the current user and their company
            $user = $this->getUser();
            $userCompany = $user ? $user->getCompany() : null;

            // Check if the user's company has access to this form
            if ($userCompany && !$formTemplate->isAvailableForAllCompanies() && 
                !$formTemplate->isAvailableForCompany($userCompany)) {

                $this->logger->warning('[FORM_ACCESS_DENIED]', [
                    'user_id' => $user->getId(),
                    'company_id' => $userCompany->getId(),
                    'form_id' => $formTemplate->getId(),
                    'form_name' => $formTemplate->getName()
                ]);

                throw $this->createAccessDeniedException('You do not have access to this form');
            }

            // Handle form submission
            if ($request->isMethod('POST')) {
                // 🔍 DEBUGGING: Capturar todos los datos del formulario
                $debugData = $this->formDebugService->logFormSubmission($request, (string)$formTemplate->getId());

                // 🔍 DEBUGGING: Validar datos de textarea específicamente
                $textareaValidation = $this->formDebugService->validateTextareaData($request->request->all());

                if (!empty($textareaValidation)) {
                    foreach ($textareaValidation as $validation) {
                        if (!$validation['is_valid']) {
                            $this->logger->error('[FORM_TEXTAREA_ERROR] Textarea validation failed', [
                                'form_id' => $formTemplate->getId(),
                                'field' => $validation['field'],
                                'issues' => $validation['issues']
                            ]);

                            $this->addFlash('error', 'Error en el campo ' . $validation['field'] . ': ' . implode(', ', $validation['issues']));
                            return $this->redirectToRoute('app_user_forms_show', ['id' => $formTemplate->getId(), 'dominio' => $dominio]);
                        }
                    }
                }
                try {
                    $entityManager->beginTransaction();

                    $formEntry = new FormEntry();
                    $formEntry->setFormTemplate($formTemplate);
                    $formEntry->setUser($this->getUser()); // Set current user if authenticated
                    $formEntry->setSubmittedAt(new \DateTime());

                    $entityManager->persist($formEntry);

                    // Process each field in the form
                    foreach ($formTemplate->getFormTemplateFields() as $field) {
                        // Skip inactive fields
                        if ($field->getStatus() !== Status::ACTIVE) {
                            continue;
                        }

                        $fieldName = $field->getName();
                        $fieldValue = $request->request->get($fieldName);

                        // � RESOLVER: Resolver tipo real del campo
                        $originalType = $field->getType();
                        $resolvedType = $this->typeResolver->resolveFieldType($field);

                        // �🔍 DEBUGGING: Log field processing
                        $this->logger->info('[FORM_FIELD_PROCESSING]', [
                            'form_id' => $formTemplate->getId(),
                            'field_name' => $fieldName,
                            'original_type' => $originalType,
                            'resolved_type' => $resolvedType,
                            'field_required' => $field->isRequired(),
                            'value_length' => is_string($fieldValue) ? strlen($fieldValue) : 'not_string',
                            'value_type' => gettype($fieldValue),
                            'type_mismatch' => $originalType !== $resolvedType
                        ]);

                        // Handle required fields
                        if ($field->isRequired() && empty($fieldValue)) {
                            $this->logger->warning('[FORM_REQUIRED_FIELD_EMPTY]', [
                                'form_id' => $formTemplate->getId(),
                                'field_name' => $fieldName
                            ]);
                            $this->addFlash('error', 'Please fill in all required fields: ' . $field->getLabel());
                            return $this->redirectToRoute('app_user_forms_show', ['id' => $formTemplate->getId(), 'dominio' => $dominio]);
                        }

                        // 🔍 DEBUGGING: Special handling for textarea fields (usar tipo resuelto)
                        if ($resolvedType === 'textarea' && !empty($fieldValue)) {
                            $this->logger->info('[FORM_TEXTAREA_PROCESSING]', [
                                'form_id' => $formTemplate->getId(),
                                'field_name' => $fieldName,
                                'original_type' => $originalType,
                                'resolved_type' => $resolvedType,
                                'value_length' => strlen($fieldValue),
                                'value_lines' => substr_count($fieldValue, "\n") + 1,
                                'encoding' => mb_detect_encoding($fieldValue),
                                'has_null_bytes' => strpos($fieldValue, "\0") !== false
                            ]);

                            // Sanitizar textarea para evitar problemas
                            $fieldValue = $this->sanitizeTextareaValue($fieldValue);
                        }

                        // Handle multiple values (checkboxes, multi-select)
                        if ($field->isMultiple() && is_array($fieldValue)) {
                            $fieldValue = json_encode($fieldValue);
                        }

                        // Create form entry value
                        $formEntryValue = new FormEntryValue();
                        $formEntryValue->setFormEntry($formEntry);
                        $formEntryValue->setFormTemplateField($field);
                        $formEntryValue->setValue($fieldValue ?? '');

                        $entityManager->persist($formEntryValue);
                    }

                    $entityManager->flush();
                    $entityManager->commit();

                    $this->logger->info('[FORM_SUBMISSION_SUCCESS]', [
                        'form_id' => $formTemplate->getId(),
                        'entry_id' => $formEntry->getId(),
                        'user_id' => $this->getUser() ? $this->getUser()->getId() : null
                    ]);

                    $this->addFlash('success', 'Form submitted successfully.');
                    return $this->redirectToRoute('app_user_forms_thank_you', ['id' => $formEntry->getId(), 'dominio' => $dominio]);

                } catch (\Exception $e) {
                    $entityManager->rollback();

                    // 🔍 DEBUGGING: Log the error with all context
                    $this->formDebugService->logFormError($e, $debugData ?? []);

                    $this->addFlash('error', 'Error al procesar el formulario. Por favor, inténtelo de nuevo.');
                    return $this->redirectToRoute('app_user_forms_show', ['id' => $formTemplate->getId(), 'dominio' => $dominio]);
                }
            }

            return $this->render('user_form/show.html.twig', [
                'form_template' => $formTemplate,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/thank-you/{id}', name: 'app_user_forms_thank_you', methods: ['GET'])]
    public function thankYou(string $dominio, int $id): Response
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $formEntry = $entityManager->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.id = :id')
                ->andWhere('fe.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$formEntry) {
                throw $this->createNotFoundException('Form entry not found');
            }

            // Get current user and their company
            $user = $this->getUser();
            $userCompany = $user ? $user->getCompany() : null;

            // Ensure the user can only see their own submissions
            if ($user && $formEntry->getUser() !== $user) {
                throw $this->createAccessDeniedException('You cannot access this submission');
            }

            // Get the form template associated with this submission
            $formTemplate = $formEntry->getFormTemplate();

            // Check if the form is still active and the user's company has access
            if (($userCompany && $formTemplate->getStatus() !== Status::ACTIVE) || 
                ($userCompany && !$formTemplate->isAvailableForAllCompanies() && 
                !$formTemplate->isAvailableForCompany($userCompany))) {

                $this->logger->warning('[THANK_YOU_ACCESS_DENIED]', [
                    'user_id' => $user ? $user->getId() : 'anonymous',
                    'company_id' => $userCompany ? $userCompany->getId() : 'none',
                    'form_id' => $formTemplate->getId(),
                    'submission_id' => $formEntry->getId()
                ]);

                throw $this->createAccessDeniedException('You no longer have access to this form');
            }

            return $this->render('user_form/thank_you.html.twig', [
                'submission' => $formEntry,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/my-submissions', name: 'app_user_forms_my_submissions', methods: ['GET'])]
    public function mySubmissions(string $dominio): Response
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();
            // Ensure user is authenticated
            $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

            $user = $this->getUser();
            $userCompany = $user->getCompany();

            // Get all submissions for this user
            $allSubmissions = $entityManager
                ->getRepository(FormEntry::class)
                ->findBy(['user' => $user, 'status' => Status::ACTIVE], ['created_at' => 'DESC']);

            // Filter submissions to only include forms the user's company has access to
            $accessibleSubmissions = [];
            foreach ($allSubmissions as $submission) {
                $formTemplate = $submission->getFormTemplate();

                // Check if the form is still active and the user's company has access
                if ($formTemplate->getStatus() === Status::ACTIVE && 
                    ($formTemplate->isAvailableForAllCompanies() || 
                     !$userCompany || // If user has no company, show all submissions they've made
                     $formTemplate->isAvailableForCompany($userCompany))) {
                    $accessibleSubmissions[] = $submission;
                }
            }

            $this->logger->info('[USER_SUBMISSIONS_FILTERED]', [
                'user_id' => $user->getId(),
                'company_id' => $userCompany ? $userCompany->getId() : 'none',
                'total_submissions' => count($allSubmissions),
                'accessible_submissions' => count($accessibleSubmissions)
            ]);

            return $this->render('user_form/my_submissions.html.twig', [
                'submissions' => $accessibleSubmissions,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/my-submissions/{id}', name: 'app_user_forms_my_submission_show', methods: ['GET'])]
    public function showMySubmission(string $dominio, int $id): Response
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $formEntry = $entityManager->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.id = :id')
                ->andWhere('fe.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$formEntry) {
                throw $this->createNotFoundException('Form entry not found');
            }

            // Get current user and their company
            $user = $this->getUser();
            $userCompany = $user ? $user->getCompany() : null;

            // Ensure the user can only see their own submissions
            if ($formEntry->getUser() !== $user) {
                throw $this->createAccessDeniedException('You cannot access this submission');
            }

            // Get the form template associated with this submission
            $formTemplate = $formEntry->getFormTemplate();

            // Check if the form is still active and the user's company has access
            if (($formTemplate->getStatus() !== Status::ACTIVE) || 
                ($userCompany && !$formTemplate->isAvailableForAllCompanies() && 
                !$formTemplate->isAvailableForCompany($userCompany))) {

                $this->logger->warning('[SUBMISSION_ACCESS_DENIED]', [
                    'user_id' => $user->getId(),
                    'company_id' => $userCompany ? $userCompany->getId() : 'none',
                    'form_id' => $formTemplate->getId(),
                    'submission_id' => $formEntry->getId()
                ]);

                throw $this->createAccessDeniedException('You no longer have access to this form');
            }

            return $this->render('user_form/my_submission_show.html.twig', [
                'submission' => $formEntry,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    /**
     * Sanitiza valores de textarea para evitar problemas de base de datos
     */
    private function sanitizeTextareaValue(string $value): string
    {
        // Remover bytes nulos que pueden causar problemas en MySQL
        $value = str_replace("\0", '', $value);

        // Normalizar saltos de línea
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        // Limitar longitud para evitar errores de base de datos
        if (strlen($value) > 65535) { // Límite de TEXT en MySQL
            $value = substr($value, 0, 65535);
            $this->logger->warning('[FORM_TEXTAREA_TRUNCATED]', [
                'original_length' => strlen($value),
                'truncated_to' => 65535
            ]);
        }

        // Asegurar que sea UTF-8 válido
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            $this->logger->warning('[FORM_TEXTAREA_ENCODING_FIXED]');
        }

        return $value;
    }
}
