<?php

namespace App\Controller\Api;

use App\Entity\App\FormEntry;
use App\Entity\App\FormEntryValue;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api/form-entries')]
class FormEntryController extends AbstractController
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    #[Route('/{id}/details', name: 'api_form_entry_details', methods: ['GET'])]
    public function getFormEntryDetails(string $dominio, int $id): JsonResponse
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $formEntry = $entityManager->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$formEntry) {
                return new JsonResponse(['error' => 'Formulario no encontrado'], 404);
            }

            // Obtener todos los valores del formulario usando consulta directa
            $formValues = $entityManager->createQueryBuilder()
                ->select('fev')
                ->from('App\Entity\App\FormEntryValue', 'fev')
                ->where('fev.formEntry = :formEntry')
                ->setParameter('formEntry', $formEntry)
                ->getQuery()
                ->getResult();

            $formDetails = [
                'id' => $formEntry->getId(),
                'template' => [
                    'id' => $formEntry->getFormTemplate()->getId(),
                    'name' => $formEntry->getFormTemplate()->getName(),
                    'description' => $formEntry->getFormTemplate()->getDescription(),
                ],
                'user' => [
                    'id' => $formEntry->getUser()->getId(),
                    'name' => $formEntry->getUser()->getName() . ' ' . $formEntry->getUser()->getLastName(),
                    'email' => $formEntry->getUser()->getEmail(),
                ],
                'created_at' => $formEntry->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $formEntry->getUpdatedAt()->format('Y-m-d H:i:s'),
                'status' => $formEntry->getStatus()->value,
                'fields' => []
            ];

            // Organizar los valores por campo
            foreach ($formValues as $value) {
                $field = $value->getFormTemplateField();
                $formDetails['fields'][] = [
                    'field_id' => $field->getId(),
                    'field_name' => $field->getName(),
                    'field_label' => $field->getLabel(),
                    'field_type' => $field->getType(),
                    'field_required' => $field->getIsRequired(),
                    'field_options' => $field->getOptions(),
                    'value' => $value->getValue(),
                    'sort_order' => $field->getSortOrder()
                ];
            }

            // Ordenar campos por sort_order
            usort($formDetails['fields'], function($a, $b) {
                return $a['sort_order'] <=> $b['sort_order'];
            });

            return new JsonResponse($formDetails);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al obtener detalles: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/user/{userId}', name: 'api_user_form_entries', methods: ['GET'])]
    public function getUserFormEntries(string $dominio, int $userId): JsonResponse
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $formEntries = $entityManager->createQueryBuilder()
                ->select('fe', 'ft', 'u')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->leftJoin('fe.formTemplate', 'ft')
                ->leftJoin('fe.user', 'u')
                ->where('u.id = :userId')
                ->setParameter('userId', $userId)
                ->orderBy('fe.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            $entries = [];
            foreach ($formEntries as $entry) {
                $entries[] = [
                    'id' => $entry->getId(),
                    'template_name' => $entry->getFormTemplate()->getName(),
                    'template_description' => $entry->getFormTemplate()->getDescription(),
                    'created_at' => $entry->getCreatedAt()->format('Y-m-d H:i:s'),
                    'status' => $entry->getStatus()->value,
                ];
            }

            return new JsonResponse([
                'user_id' => $userId,
                'total_entries' => count($entries),
                'entries' => $entries
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al obtener formularios: ' . $e->getMessage()], 500);
        }
    }
}
