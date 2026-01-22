<?php

namespace App\Service;

use App\Entity\App\FormTemplate;
use App\Entity\App\FormTemplateField;
use App\Enum\Status;
use Doctrine\ORM\EntityManagerInterface;

class FormTemplateLibraryService
{
    private TenantManager $tenantManager;
    private TenantLoggerService $logger;

    public function __construct(
        TenantManager       $tenantManager,
        TenantLoggerService $logger
    )
    {
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
     * Obtiene todas las plantillas disponibles
     */
    public function getAvailableTemplates(): array
    {
        return [
            'contact' => [
                'name' => 'Formulario de Contacto',
                'description' => 'Formulario básico para contacto con nombre, email, teléfono y mensaje',
                'icon' => 'fas fa-envelope',
                'category' => 'Comunicación',
                'fields' => $this->getContactFormFields()
            ],
            'registration' => [
                'name' => 'Formulario de Registro',
                'description' => 'Formulario completo de registro de usuarios con datos personales',
                'icon' => 'fas fa-user-plus',
                'category' => 'Usuarios',
                'fields' => $this->getRegistrationFormFields()
            ],
            'survey' => [
                'name' => 'Encuesta de Satisfacción',
                'description' => 'Encuesta para medir satisfacción del cliente con escalas y comentarios',
                'icon' => 'fas fa-poll',
                'category' => 'Encuestas',
                'fields' => $this->getSurveyFormFields()
            ],
            'feedback' => [
                'name' => 'Formulario de Retroalimentación',
                'description' => 'Formulario para recopilar comentarios y sugerencias',
                'icon' => 'fas fa-comments',
                'category' => 'Comunicación',
                'fields' => $this->getFeedbackFormFields()
            ],
            'event_registration' => [
                'name' => 'Registro de Eventos',
                'description' => 'Formulario para registro en eventos con preferencias alimentarias',
                'icon' => 'fas fa-calendar-alt',
                'category' => 'Eventos',
                'fields' => $this->getEventRegistrationFormFields()
            ],
            'job_application' => [
                'name' => 'Solicitud de Empleo',
                'description' => 'Formulario completo para solicitudes de trabajo',
                'icon' => 'fas fa-briefcase',
                'category' => 'Recursos Humanos',
                'fields' => $this->getJobApplicationFormFields()
            ],
            'support_ticket' => [
                'name' => 'Ticket de Soporte',
                'description' => 'Formulario para reportar problemas técnicos',
                'icon' => 'fas fa-life-ring',
                'category' => 'Soporte',
                'fields' => $this->getSupportTicketFormFields()
            ],
            'newsletter' => [
                'name' => 'Suscripción a Newsletter',
                'description' => 'Formulario simple para suscripción a boletín',
                'icon' => 'fas fa-newspaper',
                'category' => 'Marketing',
                'fields' => $this->getNewsletterFormFields()
            ]
        ];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Crea un formulario desde una plantilla
     */
    public function createFromTemplate(string $templateKey, string $customName = null): FormTemplate
    {
        $templates = $this->getAvailableTemplates();

        if (!isset($templates[$templateKey])) {
            throw new \InvalidArgumentException('Plantilla no encontrada: ' . $templateKey);
        }

        $template = $templates[$templateKey];
        $entityManager = $this->tenantManager->getEntityManager();

        try {
            $entityManager->beginTransaction();

            // Crear el formulario
            $formTemplate = new FormTemplate();
            $formTemplate->setName($customName ?: $template['name']);
            $formTemplate->setDescription($template['description']);
            $formTemplate->setCreatedAt(new \DateTime());
            $formTemplate->setUpdatedAt(new \DateTime());
            $formTemplate->setStatus(Status::ACTIVE);

            $entityManager->persist($formTemplate);
            $entityManager->flush();

            // Crear los campos
            foreach ($template['fields'] as $position => $fieldData) {
                $field = new FormTemplateField();
                $field->setFormTemplate($formTemplate);
                $field->setLabel($fieldData['label']);
                $field->setName($fieldData['name']);
                $field->setType($fieldData['type']);
                $field->setIsRequired($fieldData['required'] ?? false);
                $field->setOptions($fieldData['options'] ?? null);
                $field->setHelp($fieldData['help'] ?? null);
                $field->setMultiple($fieldData['multiple'] ?? false);
                $field->setCols($fieldData['cols'] ?? null);
                $field->setTextareaCols($fieldData['textarea_cols'] ?? null);
                $field->setPosition($position + 1);
                $field->setStatus(Status::ACTIVE);

                $entityManager->persist($field);
            }

            $entityManager->flush();

            $this->logger->info('Form created from template', [
                'template_key' => $templateKey,
                'form_id' => $formTemplate->getId(),
                'form_name' => $formTemplate->getName()
            ]);

            $entityManager->commit();

            return $formTemplate;

        } catch (\Exception $e) {
            $entityManager->rollback();

            $this->logger->error('Error creating form from template', [
                'template_key' => $templateKey,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    /**
     * Obtiene plantillas agrupadas por categoría
     */
    public function getTemplatesByCategory(): array
    {
        $templates = $this->getAvailableTemplates();
        $grouped = [];

        foreach ($templates as $key => $template) {
            $category = $template['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$key] = $template;
        }

        return $grouped;
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    // Definiciones de campos para cada plantilla

    private function getContactFormFields(): array
    {
        return [
            [
                'label' => 'Nombre Completo',
                'name' => 'full_name',
                'type' => 'text',
                'required' => true,
                'help' => 'Ingrese su nombre y apellidos'
            ],
            [
                'label' => 'Correo Electrónico',
                'name' => 'email',
                'type' => 'text',
                'required' => true,
                'help' => 'Dirección de correo válida'
            ],
            [
                'label' => 'Teléfono',
                'name' => 'phone',
                'type' => 'text',
                'required' => false,
                'help' => 'Número de contacto (opcional)'
            ],
            [
                'label' => 'Asunto',
                'name' => 'subject',
                'type' => 'select',
                'required' => true,
                'options' => 'Consulta General,Soporte Técnico,Ventas,Otro',
                'help' => 'Seleccione el motivo de su contacto'
            ],
            [
                'label' => 'Mensaje',
                'name' => 'message',
                'type' => 'textarea',
                'required' => true,
                'textarea_cols' => '5',
                'help' => 'Describa su consulta o mensaje'
            ]
        ];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getRegistrationFormFields(): array
    {
        return [
            [
                'label' => 'Nombre',
                'name' => 'first_name',
                'type' => 'text',
                'required' => true,
                'cols' => 'col-md-6'
            ],
            [
                'label' => 'Apellidos',
                'name' => 'last_name',
                'type' => 'text',
                'required' => true,
                'cols' => 'col-md-6'
            ],
            [
                'label' => 'Correo Electrónico',
                'name' => 'email',
                'type' => 'text',
                'required' => true,
                'help' => 'Será usado para el acceso al sistema'
            ],
            [
                'label' => 'Fecha de Nacimiento',
                'name' => 'birth_date',
                'type' => 'date',
                'required' => true
            ],
            [
                'label' => 'Género',
                'name' => 'gender',
                'type' => 'radio',
                'required' => false,
                'options' => 'Masculino,Femenino,Otro,Prefiero no decir'
            ],
            [
                'label' => 'Teléfono',
                'name' => 'phone',
                'type' => 'text',
                'required' => true,
                'cols' => 'col-md-6'
            ],
            [
                'label' => 'País',
                'name' => 'country',
                'type' => 'select',
                'required' => true,
                'options' => 'México,Estados Unidos,España,Argentina,Colombia,Otro',
                'cols' => 'col-md-6'
            ],
            [
                'label' => 'Acepto términos y condiciones',
                'name' => 'accept_terms',
                'type' => 'checkbox',
                'required' => true,
                'options' => 'Acepto los términos y condiciones'
            ]
        ];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getSurveyFormFields(): array
    {
        return [
            [
                'label' => '¿Cómo calificaría nuestro servicio?',
                'name' => 'service_rating',
                'type' => 'radio',
                'required' => true,
                'options' => 'Excelente,Muy Bueno,Bueno,Regular,Malo'
            ],
            [
                'label' => '¿Qué tan probable es que nos recomiende?',
                'name' => 'recommendation_score',
                'type' => 'select',
                'required' => true,
                'options' => '10 - Muy probable,9,8,7,6,5,4,3,2,1,0 - Nada probable'
            ],
            [
                'label' => '¿Qué aspectos considera más importantes?',
                'name' => 'important_aspects',
                'type' => 'checkbox',
                'required' => false,
                'multiple' => true,
                'options' => 'Precio,Calidad,Atención al cliente,Rapidez,Facilidad de uso'
            ],
            [
                'label' => 'Comentarios adicionales',
                'name' => 'additional_comments',
                'type' => 'textarea',
                'required' => false,
                'textarea_cols' => '4',
                'help' => 'Sus comentarios nos ayudan a mejorar'
            ]
        ];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getFeedbackFormFields(): array
    {
        return [
            [
                'label' => 'Tipo de Retroalimentación',
                'name' => 'feedback_type',
                'type' => 'select',
                'required' => true,
                'options' => 'Sugerencia,Queja,Felicitación,Reporte de Error'
            ],
            [
                'label' => 'Área o Departamento',
                'name' => 'department',
                'type' => 'select',
                'required' => true,
                'options' => 'Ventas,Soporte,Desarrollo,Administración,General'
            ],
            [
                'label' => 'Prioridad',
                'name' => 'priority',
                'type' => 'radio',
                'required' => true,
                'options' => 'Alta,Media,Baja'
            ],
            [
                'label' => 'Descripción Detallada',
                'name' => 'description',
                'type' => 'textarea',
                'required' => true,
                'textarea_cols' => '6',
                'help' => 'Proporcione todos los detalles posibles'
            ],
            [
                'label' => 'Adjuntar Archivo',
                'name' => 'attachment',
                'type' => 'file',
                'required' => false,
                'help' => 'Capturas de pantalla, documentos, etc.'
            ]
        ];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getEventRegistrationFormFields(): array
    {
        return [
            [
                'label' => 'Nombre Completo',
                'name' => 'attendee_name',
                'type' => 'text',
                'required' => true
            ],
            [
                'label' => 'Email',
                'name' => 'attendee_email',
                'type' => 'text',
                'required' => true
            ],
            [
                'label' => 'Organización/Empresa',
                'name' => 'organization',
                'type' => 'text',
                'required' => false
            ],
            [
                'label' => 'Tipo de Entrada',
                'name' => 'ticket_type',
                'type' => 'radio',
                'required' => true,
                'options' => 'General,VIP,Estudiante,Prensa'
            ],
            [
                'label' => 'Sesiones de Interés',
                'name' => 'sessions',
                'type' => 'checkbox',
                'required' => false,
                'multiple' => true,
                'options' => 'Conferencia Principal,Talleres,Networking,Panel de Expertos'
            ],
            [
                'label' => 'Restricciones Alimentarias',
                'name' => 'dietary_restrictions',
                'type' => 'textarea',
                'required' => false,
                'textarea_cols' => '3',
                'help' => 'Alergias, preferencias vegetarianas, etc.'
            ]
        ];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getJobApplicationFormFields(): array
    {
        return [
            [
                'label' => 'Nombre Completo',
                'name' => 'applicant_name',
                'type' => 'text',
                'required' => true
            ],
            [
                'label' => 'Email',
                'name' => 'applicant_email',
                'type' => 'text',
                'required' => true
            ],
            [
                'label' => 'Teléfono',
                'name' => 'applicant_phone',
                'type' => 'text',
                'required' => true
            ],
            [
                'label' => 'Posición de Interés',
                'name' => 'position',
                'type' => 'select',
                'required' => true,
                'options' => 'Desarrollador Frontend,Desarrollador Backend,Diseñador UX/UI,Project Manager,Analista de Datos'
            ],
            [
                'label' => 'Años de Experiencia',
                'name' => 'experience_years',
                'type' => 'select',
                'required' => true,
                'options' => 'Sin experiencia,1-2 años,3-5 años,6-10 años,Más de 10 años'
            ],
            [
                'label' => 'CV/Currículum',
                'name' => 'resume',
                'type' => 'file',
                'required' => true,
                'help' => 'Formato PDF preferido'
            ],
            [
                'label' => 'Carta de Presentación',
                'name' => 'cover_letter',
                'type' => 'textarea',
                'required' => false,
                'textarea_cols' => '5',
                'help' => 'Cuéntanos por qué eres el candidato ideal'
            ]
        ];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getSupportTicketFormFields(): array
    {
        return [
            [
                'label' => 'Tipo de Problema',
                'name' => 'issue_type',
                'type' => 'select',
                'required' => true,
                'options' => 'Error de Sistema,Problema de Acceso,Solicitud de Función,Consulta General'
            ],
            [
                'label' => 'Prioridad',
                'name' => 'priority',
                'type' => 'radio',
                'required' => true,
                'options' => 'Crítica,Alta,Media,Baja'
            ],
            [
                'label' => 'Resumen del Problema',
                'name' => 'issue_summary',
                'type' => 'text',
                'required' => true,
                'help' => 'Descripción breve del problema'
            ],
            [
                'label' => 'Descripción Detallada',
                'name' => 'issue_description',
                'type' => 'textarea',
                'required' => true,
                'textarea_cols' => '5',
                'help' => 'Pasos para reproducir el problema, mensajes de error, etc.'
            ],
            [
                'label' => 'Capturas de Pantalla',
                'name' => 'screenshots',
                'type' => 'file',
                'required' => false,
                'multiple' => true,
                'help' => 'Imágenes que ayuden a entender el problema'
            ]
        ];
    }

    /**
     * Obtiene el EntityManager del tenant actual
     */
    private function getNewsletterFormFields(): array
    {
        return [
            [
                'label' => 'Nombre',
                'name' => 'subscriber_name',
                'type' => 'text',
                'required' => true,
                'cols' => 'col-md-6'
            ],
            [
                'label' => 'Email',
                'name' => 'subscriber_email',
                'type' => 'text',
                'required' => true,
                'cols' => 'col-md-6'
            ],
            [
                'label' => 'Intereses',
                'name' => 'interests',
                'type' => 'checkbox',
                'required' => false,
                'multiple' => true,
                'options' => 'Noticias,Ofertas Especiales,Eventos,Tutoriales,Actualizaciones de Producto'
            ],
            [
                'label' => 'Frecuencia de Envío',
                'name' => 'frequency',
                'type' => 'radio',
                'required' => true,
                'options' => 'Diario,Semanal,Quincenal,Mensual'
            ]
        ];
    }
}

    /**
     * Obtiene el EntityManager del tenant actual
     */
