<?php

namespace App\Controller;

use App\Entity\App\Company;
use App\Enum\Status;
use App\Form\CompanyType;
use App\Service\RegionAccessService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{dominio}/company')]
final class CompanyController extends AbstractController
{
    private TenantManager $tenantManager;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private RegionAccessService $regionAccessService;

    public function __construct(
        TenantManager $tenantManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        RegionAccessService $regionAccessService
    ) {
        $this->tenantManager = $tenantManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->regionAccessService = $regionAccessService;
    }

    #[Route('/', name: 'app_company_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        try {
            $em = $this->tenantManager->getEntityManager();
            $company = new Company();
            $form = $this->createForm(CompanyType::class, $company, [
                'action' => $this->generateUrl('app_company_new', ['dominio' => $dominio]),
                'method' => 'POST',
            ]);

            // Get active regions for the modal form
            $regions = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Region', 'r')
                ->where('r.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->orderBy('r.name', 'ASC')
                ->getQuery()
                ->getResult();

            // Generar token CSRF específico para eliminación
            $csrfToken = $this->csrfTokenManager->getToken('delete_company')->getValue();

            return $this->render('company/index.html.twig', [
                'form' => $form->createView(),
                'regions' => $regions,
                'csrf_token_delete' => $csrfToken,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/new', name: 'app_company_new', methods: ['GET', 'POST'])]
    public function new(string $dominio, Request $request): Response
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $company = new Company();
            $form = $this->createForm(CompanyType::class, $company);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $company->setStatus(Status::ACTIVE);
                $company->setCreatedAt(new \DateTimeImmutable());
                $company->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->persist($company);
                $entityManager->flush();

                return $this->redirectToRoute('app_company_index', ['dominio' => $dominio], Response::HTTP_SEE_OTHER);
            }

            return $this->render('company/new.html.twig', [
                'company' => $company,
                'form' => $form,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/datatable', name: 'app_company_datatable', methods: ['GET'])]
    public function datatable(string $dominio, Request $request): JsonResponse
    {
        if (empty($dominio)) {
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }

        $em = $this->tenantManager->getEntityManager();

        // DataTables parameters
        $draw = (int) $request->query->get('draw', 1);
        $start = (int) $request->query->get('start', 0);
        $length = (int) $request->query->get('length', 25);
        
        $search = $request->query->all('search');
        $searchValue = isset($search['value']) ? $search['value'] : '';
        
        $order = $request->query->all('order');
        $orderColumn = isset($order[0]['column']) ? (int) $order[0]['column'] : 0;
        $orderDir = isset($order[0]['dir']) ? $order[0]['dir'] : 'asc';

        // DataTables columns in the UI are: [0] Actions, [1] Nombre, [2] Región
        $columns = [
            0 => null,          // Actions (not orderable)
            1 => 'name',        // Nombre
            2 => 'region.name', // Región
        ];
        $orderBy = isset($columns[$orderColumn]) && $columns[$orderColumn] ? $columns[$orderColumn] : 'name';

        $qb = $em->createQueryBuilder()
            ->select('e')
            ->from('App\Entity\App\Company', 'e')
            ->leftJoin('e.region', 'r')
            ->where('e.status = :status')
            ->setParameter('status', Status::ACTIVE);

        // Security Logic: Admin ve todo, Lider solo sus regiones
        $this->regionAccessService->applyRegionFilterDirect($qb, 'e', 'region');

        // Total Records (Accessible by user)
        $totalRecordsQb = clone $qb;
        $totalRecords = (int) $totalRecordsQb->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        if (!empty($searchValue)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('e.name', ':search'),
                    $qb->expr()->like('r.name', ':search')
                )
            )->setParameter('search', '%' . $searchValue . '%');
        }

        $countQb = clone $qb;
        $totalFiltered = (int) $countQb->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        // Fix ordering for joined fields
        if (strpos($orderBy, '.') !== false) {
            [$alias, $field] = explode('.', $orderBy);
            $qb->orderBy($alias . '.' . $field, $orderDir);
        } else {
            $qb->orderBy('e.' . $orderBy, $orderDir);
        }

        $qb->setFirstResult($start)->setMaxResults($length);

        $results = $qb->getQuery()->getResult();

        $data = [];
        foreach ($results as $item) {
            $data[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'region' => $item->getRegion() ? $item->getRegion()->getName() : '',
                'status' => $item->getStatus() ? $item->getStatus()->value : '',
            ];
        }

        return new JsonResponse([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    #[Route('/{id}/details', name: 'app_company_details', methods: ['GET'])]
    public function details(string $dominio, int $id): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['error' => 'Dominio no especificado'], 400);
        }

        try {
            $em = $this->tenantManager->getEntityManager();

            $company = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.id = :id')
                ->andWhere('c.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$company) {
                return new JsonResponse(['error' => 'Empresa no encontrada'], 404);
            }

            return new JsonResponse([
                'id' => $company->getId(),
                'name' => $company->getName(),
                'regionId' => $company->getRegion() ? $company->getRegion()->getId() : null,
                'regionName' => $company->getRegion() ? $company->getRegion()->getName() : null,
                'status' => $company->getStatus()->value
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/edit', name: 'app_company_edit', methods: ['GET', 'POST'])]
    public function edit(string $dominio, Request $request, int $id): Response
    {
        if (empty($dominio)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Dominio no especificado'], 400);
        }

        // Redirect GET requests to index
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_company_index', ['dominio' => $dominio]);
        }

        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $company = $entityManager->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.id = :id')
                ->andWhere('c.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$company) {
                return new JsonResponse(['status' => 'error', 'message' => 'Empresa no encontrada'], 404);
            }

            $form = $this->createForm(CompanyType::class, $company, [
                'csrf_protection' => true, // Ensure CSRF is enabled
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $company->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                return new JsonResponse(['status' => 'success', 'message' => 'Empresa actualizada correctamente']);
            }

            // Get form errors
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return new JsonResponse(['status' => 'error', 'message' => 'Error de validación', 'errors' => $errors], 400);

        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/delete', name: 'app_company_delete', methods: ['POST'])]
    public function delete(string $dominio, Request $request, int $id): JsonResponse
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $company = $entityManager->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.id = :id')
                ->andWhere('c.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$company) {
                return new JsonResponse(['status' => 'error', 'message' => 'Empresa no encontrada'], 404);
            }

            if ($this->isCsrfTokenValid('delete_company', $request->request->get('_token'))) {
                $company->setStatus(Status::INACTIVE);
                $entityManager->flush();
                return new JsonResponse(['status' => 'success', 'message' => 'Empresa eliminada correctamente']);
            } else {
                return new JsonResponse(['status' => 'error', 'message' => 'Token de seguridad inválido'], 403);
            }

        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/list', name: 'app_company_list', methods: ['GET'])]
    public function list(string $dominio): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['error' => 'Dominio no especificado'], 400);
        }

        try {
            $em = $this->tenantManager->getEntityManager();

            $companies = $em->createQueryBuilder()
                ->select('c', 'r')
                ->from('App\Entity\App\Company', 'c')
                ->leftJoin('c.region', 'r')
                ->where('c.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->orderBy('r.name', 'ASC')
                ->addOrderBy('c.name', 'ASC')
                ->getQuery()
                ->getResult();

            // Group companies by region
            $groupedData = [];
            foreach ($companies as $company) {
                $region = $company->getRegion();
                $regionId = $region ? $region->getId() : 0;
                $regionName = $region ? $region->getName() : 'Sin Región';

                if (!isset($groupedData[$regionId])) {
                    $groupedData[$regionId] = [
                        'region_id' => $regionId,
                        'region_name' => $regionName,
                        'companies' => []
                    ];
                }

                $groupedData[$regionId]['companies'][] = [
                    'id' => $company->getId(),
                    'name' => $company->getName(),
                ];
            }

            // Convert to indexed array
            return new JsonResponse(array_values($groupedData));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

}

