<?php

namespace App\Controller;

use App\DTO\Form\FormFieldCreateRequest;
use App\DTO\Form\FormFieldUpdateRequest;
use App\DTO\Form\FormTemplateCreateRequest;
use App\DTO\Form\FormTemplateUpdateRequest;
use App\Entity\App\FormTemplate;
use App\Form\FormTemplateType;
use App\Service\FormExportService;
use App\Service\FormFieldService;
use App\Service\FormPreviewService;
use App\Service\FormTemplateLibraryService;
use App\Service\FormTemplateService;
use App\Service\TenantManager;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{dominio}/admin/forms')]
class FormController extends AbstractController
{
    private TenantManager $tenantManager;
    private ValidationService $validationService;
    private FormTemplateService $formTemplateService;
    private FormFieldService $formFieldService;
    private FormExportService $formExportService;
    private FormTemplateLibraryService $templateLibraryService;
    private FormPreviewService $formPreviewService;
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(
        TenantManager $tenantManager,
        ValidationService $validationService,
        FormTemplateService $formTemplateService,
        FormFieldService $formFieldService,
        FormExportService $formExportService,
        FormTemplateLibraryService $templateLibraryService,
        FormPreviewService $formPreviewService,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->tenantManager = $tenantManager;
        $this->validationService = $validationService;
        $this->formTemplateService = $formTemplateService;
        $this->formFieldService = $formFieldService;
        $this->formExportService = $formExportService;
        $this->templateLibraryService = $templateLibraryService;
        $this->formPreviewService = $formPreviewService;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    #[Route('/', name: 'app_forms_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        try {
            // Configurar explicitamente el tenant antes de cualquier operacion
            $this->tenantManager->setCurrentTenant($dominio);

            // Limpiar cache para obtener datos actualizados con contadores
            $this->formTemplateService->clearFormTemplatesCache();

            $formTemplates = $this->formTemplateService->getActiveFormTemplates();

            // Generar token CSRF especifico para eliminacion
            $csrfToken = $this->csrfTokenManager->getToken('delete_form')->getValue();

            // Crear formulario para el modal de creacion
            $formTemplate = new FormTemplate();
            $form = $this->createForm(FormTemplateType::class, $formTemplate, [
                'action' => $this->generateUrl('app_forms_new', ['dominio' => $dominio]),
                'method' => 'POST',
                'dominio' => $dominio,
            ]);

            // Crear formulario para el modal de edicion (vacio, se llenara via JS)
            $editFormTemplate = new FormTemplate();
            $editForm = $this->createForm(FormTemplateType::class, $editFormTemplate, [
                'action' => '',
                'method' => 'POST',
                'dominio' => $dominio,
            ]);

            return $this->render('form/index.html.twig', [
                'form_templates' => $formTemplates,
                'form' => $form->createView(),
                'editForm' => $editForm->createView(),
                'dominio' => $dominio,
                'csrf_token_delete' => $csrfToken,
            ]);
        } catch (NotFoundHttpException $e) {
            // Si el tenant no es valido, propagar la excepcion
            throw $e;
        } catch (\Exception $e) {
            return $this->render('form/index.html.twig', [
                'form_templates' => [],
                'dominio' => $dominio,
                'error' => 'Error al cargar los formularios'
            ]);
        }
    }

    #[Route('/new', name: 'app_forms_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $dominio): Response
    {
        try {
            // Configurar explicitamente el tenant antes de cualquier operacion
            $this->tenantManager->setCurrentTenant($dominio);
            $entityManager = $this->tenantManager->getEntityManager();

            $formTemplate = new FormTemplate();
            $form = $this->createForm(FormTemplateType::class, $formTemplate, [
                'dominio' => $dominio,
            ]);

            if ($request->isMethod('POST')) {
                // Si es AJAX/JSON, decodificar el contenido
                if ($request->getContentTypeFormat() === 'json') {
                    $data = json_decode($request->getContent(), true);
                    $request->request->replace($data);
                }

                // Validar datos usando DTO
                $validationResult = $this->validationService->createAndValidateDTO(
                    FormTemplateCreateRequest::class,
                    $request
                );

                if (!$validationResult['isValid']) {
                    if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                        return $this->json([
                            'status' => 'error',
                            'message' => 'Error de validacion',
                            'errors' => $this->validationService->getFormattedErrorsForView($validationResult['errors'])
                        ], 400);
                    }

                    return $this->render('form/new.html.twig', [
                        'errors' => $this->validationService->getFormattedErrorsForView($validationResult['errors']),
                        'form' => $form->createView(),
                        'dominio' => $dominio,
                    ]);
                }

                /** @var FormTemplateCreateRequest $dto */
                $dto = $validationResult['dto'];

                // Crear formulario usando el servicio
                $formTemplate = $this->formTemplateService->createFormTemplate($dto, $dominio);

                if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                    return $this->json([
                        'status' => 'success',
                        'message' => 'Formulario creado exitosamente.',
                        'id' => $formTemplate->getId()
                    ]);
                }

                $this->addFlash('success', 'Form template created successfully.');
                return $this->redirectToRoute('app_forms_edit', [
                    'id' => $formTemplate->getId(),
                    'dominio' => $dominio
                ]);
            }

            return $this->render('form/new.html.twig', [
                'form' => $form->createView(),
                'dominio' => $dominio,
            ]);
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                return $this->json(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
            }
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}', name: 'app_forms_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, string $dominio, int $id): Response
    {
        try {
            // Configurar explicitamente el tenant antes de cualquier operacion
            $this->tenantManager->setCurrentTenant($dominio);

            $formTemplate = $this->formTemplateService->getFormTemplateById($id);

            // Si se solicita JSON (para el modal de edicion), devolver datos estructurados
            if ($request->isXmlHttpRequest() || $request->query->get('format') === 'json') {
                $companyIds = [];
                foreach ($formTemplate->getCompanies() as $company) {
                    $companyIds[] = $company->getId();
                }

                return $this->json([
                    'id' => $formTemplate->getId(),
                    'name' => $formTemplate->getName(),
                    'description' => $formTemplate->getDescription(),
                    'companyIds' => $companyIds
                ]);
            }

            // Generar token CSRF para eliminación de campos
            $csrfToken = $this->csrfTokenManager->getToken('delete_field')->getValue();

            return $this->render('form/show.html.twig', [
                'form_template' => $formTemplate,
                'dominio' => $dominio,
                'csrf_token_delete' => $csrfToken, // Pasamos el token a la vista
            ]);
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Formulario no encontrado'], 404);
            }
            throw $this->createNotFoundException('Formulario no encontrado: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}/edit', name: 'app_forms_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, string $dominio, int $id): Response
    {
        try {
            // Configurar explicitamente el tenant antes de cualquier operacion
            $this->tenantManager->setCurrentTenant($dominio);

            $formTemplate = $this->formTemplateService->getFormTemplateById($id);

            $form = $this->createForm(FormTemplateType::class, $formTemplate, [
                'dominio' => $dominio,
            ]);

            if ($request->isMethod('POST')) {
                // Si es AJAX/JSON, decodificar el contenido
                if ($request->getContentTypeFormat() === 'json') {
                    $data = json_decode($request->getContent(), true);
                    $request->request->replace($data);
                }

                // Validar datos usando DTO
                $validationResult = $this->validationService->createAndValidateDTO(
                    FormTemplateUpdateRequest::class,
                    $request
                );

                if (!$validationResult['isValid']) {
                    if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                        return $this->json([
                            'status' => 'error',
                            'message' => 'Error de validacion',
                            'errors' => $this->validationService->getFormattedErrorsForView($validationResult['errors'])
                        ], 400);
                    }

                    return $this->render('form/edit.html.twig', [
                        'form_template' => $formTemplate,
                        'dominio' => $dominio,
                        'form' => $form->createView(),
                        'errors' => $this->validationService->getFormattedErrorsForView($validationResult['errors'])
                    ]);
                }

                /** @var FormTemplateUpdateRequest $dto */
                $dto = $validationResult['dto'];

                // Actualizar formulario usando el servicio
                $formTemplate = $this->formTemplateService->updateFormTemplate($id, $dto, $dominio);

                if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                    return $this->json([
                        'status' => 'success',
                        'message' => 'Formulario actualizado exitosamente.'
                    ]);
                }

                $this->addFlash('success', 'Form template updated successfully.');
                return $this->redirectToRoute('app_forms_show', [
                    'id' => $formTemplate->getId(),
                    'dominio' => $dominio
                ]);
            }

            return $this->render('form/edit.html.twig', [
                'form_template' => $formTemplate,
                'dominio' => $dominio,
                'form' => $form->createView(),
            ]);

        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                return $this->json(['status' => 'error', 'message' => 'Error al editar: ' . $e->getMessage()], 500);
            }
            throw $this->createNotFoundException('Error al editar formulario: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}/fields/new', name: 'app_forms_fields_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function newField(Request $request, string $dominio, int $id): Response
    {
        try {
            // Configurar explicitamente el tenant antes de cualquier operacion
            $this->tenantManager->setCurrentTenant($dominio);

            $formTemplate = $this->formTemplateService->getFormTemplateById($id);

            if ($request->isMethod('POST')) {
                // Validar datos usando DTO
                $validationResult = $this->validationService->createAndValidateDTO(
                    FormFieldCreateRequest::class,
                    $request
                );

                if (!$validationResult['isValid']) {
                    // Si es AJAX, devolver JSON con errores
                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'status' => 'error',
                            'message' => 'Error de validación',
                            'errors' => $this->validationService->getFormattedErrorsForView($validationResult['errors'])
                        ], 400);
                    }

                    return $this->render('form/field_new.html.twig', [
                        'form_template' => $formTemplate,
                        'errors' => $this->validationService->getFormattedErrorsForView($validationResult['errors'])
                    ]);
                }

                /** @var FormFieldCreateRequest $dto */
                $dto = $validationResult['dto'];

                // Crear campo usando el servicio
                $newField = $this->formFieldService->createField($formTemplate, $dto);

                // Si es AJAX, devolver JSON con datos del nuevo campo
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'status' => 'success',
                        'message' => 'Campo creado exitosamente.',
                        'field' => [
                            'id' => $newField->getId(),
                            'label' => $newField->getLabel(),
                            'name' => $newField->getName(),
                            'type' => $newField->getType(),
                            'isRequired' => $newField->getIsRequired(),
                            'position' => $newField->getPosition()
                        ]
                    ]);
                }

                $this->addFlash('success', 'Field added successfully.');
                return $this->redirectToRoute('app_forms_show', [
                    'id' => $formTemplate->getId(),
                    'dominio' => $dominio
                ]);
            }

            return $this->render('form/field_new.html.twig', [
                'form_template' => $formTemplate,
            ]);
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Error al crear campo: ' . $e->getMessage()
                ], 500);
            }
            throw $this->createNotFoundException('Error al crear campo: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}/fields/{fieldId}/edit', name: 'app_forms_fields_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+', 'fieldId' => '\\d+'])]
    public function editField(Request $request, string $dominio, int $id, int $fieldId): Response
    {
        try {
            // Configurar explicitamente el tenant antes de cualquier operacion
            $this->tenantManager->setCurrentTenant($dominio);

            $formTemplate = $this->formTemplateService->getFormTemplateById($id);
            $field = $this->formFieldService->getFieldById($fieldId, $formTemplate);

            if ($request->isMethod('POST')) {
                // Validar datos usando DTO
                $validationResult = $this->validationService->createAndValidateDTO(
                    FormFieldUpdateRequest::class,
                    $request
                );

                if (!$validationResult['isValid']) {
                    // Si es AJAX, devolver JSON con errores
                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'status' => 'error',
                            'message' => 'Error de validación',
                            'errors' => $this->validationService->getFormattedErrorsForView($validationResult['errors'])
                        ], 400);
                    }

                    return $this->render('form/field_edit.html.twig', [
                        'form_template' => $formTemplate,
                        'field' => $field,
                        'errors' => $this->validationService->getFormattedErrorsForView($validationResult['errors'])
                    ]);
                }

                /** @var FormFieldUpdateRequest $dto */
                $dto = $validationResult['dto'];

                // Actualizar campo usando el servicio
                $updatedField = $this->formFieldService->updateField($fieldId, $formTemplate, $dto);

                // Si es AJAX, devolver JSON con datos actualizados
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'status' => 'success',
                        'message' => 'Campo actualizado exitosamente.',
                        'field' => [
                            'id' => $updatedField->getId(),
                            'label' => $updatedField->getLabel(),
                            'name' => $updatedField->getName(),
                            'type' => $updatedField->getType(),
                            'isRequired' => $updatedField->getIsRequired(),
                            'position' => $updatedField->getPosition()
                        ]
                    ]);
                }

                $this->addFlash('success', 'Field updated successfully.');
                return $this->redirectToRoute('app_forms_show', [
                    'id' => $formTemplate->getId(),
                    'dominio' => $dominio
                ]);
            }

            return $this->render('form/field_edit.html.twig', [
                'form_template' => $formTemplate,
                'field' => $field,
            ]);
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Error al editar campo: ' . $e->getMessage()
                ], 500);
            }
            throw $this->createNotFoundException('Error al editar campo: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}/fields/{fieldId}/details', name: 'app_forms_fields_details', methods: ['GET'], requirements: ['id' => '\\d+', 'fieldId' => '\\d+'])]
    public function fieldDetails(string $dominio, int $id, int $fieldId): Response
    {
        try {
            // Configurar explicitamente el tenant antes de cualquier operacion
            $this->tenantManager->setCurrentTenant($dominio);

            $formTemplate = $this->formTemplateService->getFormTemplateById($id);
            $field = $this->formFieldService->getFieldById($fieldId, $formTemplate);

            return $this->json([
                'id' => $field->getId(),
                'label' => $field->getLabel(),
                'name' => $field->getName(),
                'type' => $field->getType(),
                'isRequired' => $field->getIsRequired(),
                'help' => $field->getHelp(),
                'options' => $field->getOptions(),
                'multiple' => $field->getMultiple(),
                'cols' => $field->getCols(),
                'textareaCols' => $field->getTextareaCols(),
                'position' => $field->getPosition()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Campo no encontrado: ' . $e->getMessage()
            ], 404);
        }
    }

    #[Route('/{id}/delete', name: 'app_forms_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, string $dominio, int $id): Response
    {
        try {
            // Configurar explicitamente el tenant antes de cualquier operacion
            $this->tenantManager->setCurrentTenant($dominio);

            if ($this->isCsrfTokenValid('delete_form', $request->request->get('_token'))) {
                $this->formTemplateService->deleteFormTemplate($id);
                
                // Si es AJAX, devolver JSON con datos de éxito
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'status' => 'success',
                        'message' => 'Formulario eliminado exitosamente.'
                    ]);
                }
                
                $this->addFlash('success', 'Form template deleted successfully.');
            } else {
                // Token CSRF inválido
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Token de seguridad inválido.'
                    ], 403);
                }
                
                $this->addFlash('error', 'Token de seguridad inválido.');
            }

            return $this->redirectToRoute('app_forms_index', [
                'dominio' => $dominio
            ]);
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Error al eliminar el formulario: ' . $e->getMessage()
                ], 500);
            }
            
            $this->addFlash('error', 'Error al eliminar el formulario: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_index', [
                'dominio' => $dominio
            ]);
        }
    }

    #[Route('/{id}/fields/{fieldId}/delete', name: 'app_forms_fields_delete', methods: ['POST'], requirements: ['id' => '\d+', 'fieldId' => '\d+'])]
    public function deleteField(Request $request, string $dominio, int $id, int $fieldId): Response
    {
        try {
            $formTemplate = $this->formTemplateService->getFormTemplateById($id);

            $submittedToken = $request->request->get('_token');
            $tokenId = 'delete_field'; // Usar un token fijo
            $isValid = $this->isCsrfTokenValid($tokenId, $submittedToken);

            if ($isValid) {
                $this->formFieldService->deleteField($fieldId, $formTemplate);

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'status' => 'success',
                        'message' => 'Campo eliminado exitosamente.'
                    ]);
                }

                $this->addFlash('success', 'Field deleted successfully.');
            } else {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Token de seguridad inválido.'
                    ], 403);
                }

                $this->addFlash('error', 'Token de seguridad inválido.');
            }

            return $this->redirectToRoute('app_forms_edit', [
                'id' => $formTemplate->getId(),
                'dominio' => $dominio
            ]);
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Error al eliminar el campo: ' . $e->getMessage()
                ], 500);
            }
            
            $this->addFlash('error', 'Error al eliminar el campo: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_edit', [
                'id' => $id,
                'dominio' => $dominio
            ]);
        }
    }

    #[Route('/{id}/submissions', name: 'app_forms_submissions', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function submissions(string $dominio, int $id): Response
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $formTemplate = $this->formTemplateService->getFormTemplateById($id);

            // Obtener todas las respuestas del formulario usando consulta directa
            $submissions = $em->createQueryBuilder()
                ->select('fe, u')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->leftJoin('fe.user', 'u')
                ->where('fe.formTemplate = :formTemplate')
                ->andWhere('fe.status = :status')
                ->setParameter('formTemplate', $formTemplate)
                ->setParameter('status', \App\Enum\Status::ACTIVE)
                ->orderBy('fe.created_at', 'DESC')
                ->getQuery()
                ->getResult();

            return $this->render('form/submissions.html.twig', [
                'form_template' => $formTemplate,
                'submissions' => $submissions,
                'dominio' => $dominio,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Error al cargar envíos: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/submissions/{id}', name: 'app_forms_submission_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showSubmission(string $dominio, int $id): Response
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            // Obtener la respuesta con todos sus valores cargados usando consulta directa
            $submission = $em->createQueryBuilder()
                ->select('fe, fev, ftf, u')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->leftJoin('fe.formEntryValues', 'fev')
                ->leftJoin('fev.formTemplateField', 'ftf')
                ->leftJoin('fe.user', 'u')
                ->where('fe.id = :id')
                ->andWhere('fe.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', \App\Enum\Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$submission) {
                throw $this->createNotFoundException('Respuesta no encontrada.');
            }

            return $this->render('form/submission_show.html.twig', [
                'submission' => $submission,
                'dominio' => $dominio,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Error al cargar envío: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}/export/json', name: 'app_forms_export_json', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function exportJson(string $dominio, int $id): Response
    {
        try {
            $exportData = $this->formExportService->exportToJson($id);

            $response = new Response(json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', 'attachment; filename="formulario_' . $id . '.json"');

            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al exportar formulario: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_show', ['id' => $id, 'dominio' => $dominio]);
        }
    }

    #[Route('/{id}/export/excel', name: 'app_forms_export_excel', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function exportExcel(string $dominio, int $id): Response
    {
        try {
            return $this->formExportService->exportToExcel($id);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al exportar formulario: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_show', ['id' => $id, 'dominio' => $dominio]);
        }
    }

    #[Route('/export/multiple', name: 'app_forms_export_multiple', methods: ['POST'])]
    public function exportMultiple(Request $request, string $dominio): Response
    {
        try {
            $formIds = $request->request->get('form_ids', []);

            if (empty($formIds)) {
                $this->addFlash('error', 'Debe seleccionar al menos un formulario para exportar.');
                return $this->redirectToRoute('app_forms_index', ['dominio' => $dominio]);
            }

            return $this->formExportService->exportMultipleToZip($formIds);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al exportar formularios: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_index', ['dominio' => $dominio]);
        }
    }

    #[Route('/templates', name: 'app_forms_templates', methods: ['GET'])]
    public function templates(string $dominio): Response
    {
        try {
            $templatesByCategory = $this->templateLibraryService->getTemplatesByCategory();

            return $this->render('form/templates.html.twig', [
                'templates_by_category' => $templatesByCategory,
                'dominio' => $dominio
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al cargar plantillas: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_index', ['dominio' => $dominio]);
        }
    }

    #[Route('/templates/{templateKey}/create', name: 'app_forms_create_from_template', methods: ['GET', 'POST'])]
    public function createFromTemplate(Request $request, string $dominio, string $templateKey): Response
    {
        try {
            $templates = $this->templateLibraryService->getAvailableTemplates();

            if (!isset($templates[$templateKey])) {
                throw new \InvalidArgumentException('Plantilla no encontrada');
            }

            $template = $templates[$templateKey];

            if ($request->isMethod('POST')) {
                $customName = $request->request->get('custom_name');

                if (empty($customName)) {
                    $this->addFlash('error', 'El nombre del formulario es obligatorio.');
                    return $this->render('form/create_from_template.html.twig', [
                        'template' => $template,
                        'template_key' => $templateKey,
                        'dominio' => $dominio
                    ]);
                }

                $formTemplate = $this->templateLibraryService->createFromTemplate($templateKey, $customName);

                $this->addFlash('success', 'Formulario creado exitosamente desde la plantilla.');
                return $this->redirectToRoute('app_forms_edit', [
                    'id' => $formTemplate->getId(),
                    'dominio' => $dominio
                ]);
            }

            return $this->render('form/create_from_template.html.twig', [
                'template' => $template,
                'template_key' => $templateKey,
                'dominio' => $dominio
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al crear formulario desde plantilla: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_templates', ['dominio' => $dominio]);
        }
    }

    #[Route('/templates/{templateKey}/preview', name: 'app_forms_template_preview', methods: ['GET'])]
    public function previewTemplate(string $dominio, string $templateKey): Response
    {
        try {
            $templates = $this->templateLibraryService->getAvailableTemplates();

            if (!isset($templates[$templateKey])) {
                throw new \InvalidArgumentException('Plantilla no encontrada');
            }

            $template = $templates[$templateKey];

            return $this->render('form/template_preview.html.twig', [
                'template' => $template,
                'template_key' => $templateKey,
                'dominio' => $dominio
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al cargar vista previa: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_templates', ['dominio' => $dominio]);
        }
    }

    #[Route('/{id}/preview', name: 'app_forms_preview', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function preview(Request $request, string $dominio, int $id): Response
    {
        try {

            if ($request->isMethod('POST')) {
                // Validar datos del formulario de vista previa
                $formData = $request->request->all();
                $validation = $this->formPreviewService->validateFormData($id, $formData);

                return $this->json([
                    'success' => $validation['is_valid'],
                    'errors' => $validation['errors'],
                    'message' => $validation['is_valid']
                        ? 'Formulario válido! (Esta es solo una vista previa)'
                        : 'Por favor corrija los errores indicados.'
                ]);
            }

            $previewData = $this->formPreviewService->generatePreviewHtml($id);

            return $this->render('form/preview.html.twig', [
                'preview_data' => $previewData,
                'dominio' => $dominio
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al cargar vista previa: ' . $e->getMessage());
            return $this->redirectToRoute('app_forms_show', ['id' => $id, 'dominio' => $dominio]);
        }
    }

    #[Route('/{id}/preview/data', name: 'app_forms_preview_data', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function previewData(string $dominio, int $id): Response
    {
        try {
            $previewData = $this->formPreviewService->generatePreviewHtml($id);

            return $this->json($previewData);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/validate', name: 'app_forms_validate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function validateForm(Request $request, string $dominio, int $id): Response
    {
        try {
            $formData = json_decode($request->getContent(), true);

            $validation = $this->formPreviewService->validateFormData($id, $formData);

            return $this->json($validation);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/sweetalert-demo', name: 'app_forms_sweetalert_demo', methods: ['GET'])]
    public function sweetalertDemo(string $dominio): Response
    {
        return $this->render('form/sweetalert_demo.html.twig', [
            'dominio' => $dominio
        ]);
    }

    /**
     * Extrae y procesa los IDs de empresas desde el request
     */
    private function getCompanyIdsFromRequest(Request $request): array
    {
        $companyIds = $request->request->get('companyIds', []);

        if (is_string($companyIds)) {
            $companyIds = explode(',', $companyIds);
        }

        if (!is_array($companyIds)) {
            return [];
        }

        // Filtrar y convertir a enteros
        return array_filter(array_map('intval', $companyIds), function($id) {
            return $id > 0;
        });
    }
}
