<?php

namespace App\Controller;

use App\Entity\App\Region;
use App\Enum\Status;
use App\Form\RegionType;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{dominio}/region')]
final class RegionController extends AbstractController
{
    private TenantManager $tenantManager;
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(TenantManager $tenantManager, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->tenantManager = $tenantManager;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    #[Route('/', name: 'app_region_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        try {
            $region = new Region();
            $form = $this->createForm(RegionType::class, $region, [
                'action' => $this->generateUrl('app_region_new', ['dominio' => $dominio]),
                'method' => 'POST',
            ]);

            // Generar token CSRF específico para eliminación
            $csrfToken = $this->csrfTokenManager->getToken('delete_region')->getValue();

            return $this->render('region/index.html.twig', [
                'form' => $form->createView(),
                'csrf_token_delete' => $csrfToken,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/new', name: 'app_region_new', methods: ['GET', 'POST'])]
    public function new(string $dominio, Request $request): Response
    {
        try {
            $em = $this->tenantManager->getEntityManager();
            $region = new Region();
            $form = $this->createForm(RegionType::class, $region);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Verificar si ya existe una región activa con ese nombre usando consulta directa
                $existing = $em->createQueryBuilder()
                    ->select('r')
                    ->from('App\Entity\App\Region', 'r')
                    ->where('r.name = :name')
                    ->andWhere('r.status = :status')
                    ->setParameter('name', $region->getName())
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existing) {
                    $this->addFlash('error', 'Ya existe una región con ese nombre.');
                    return $this->redirectToRoute('app_region_new', ['dominio' => $dominio]);
                }

                $region->setStatus(Status::ACTIVE);
                $region->setCreatedAt(new \DateTimeImmutable());
                $region->setUpdatedAt(new \DateTimeImmutable());

                $em->persist($region);
                $em->flush();

                return $this->redirectToRoute('app_region_index', ['dominio' => $dominio], Response::HTTP_SEE_OTHER);
            }

            return $this->render('region/new.html.twig', [
                'region' => $region,
                'form' => $form,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/list', name: 'app_region_list', methods: ['GET'])]
    public function list(string $dominio): JsonResponse
    {
        try {
            // CRÍTICO: Establecer explícitamente el tenant desde el parámetro de ruta
            $this->tenantManager->setCurrentTenant($dominio);
            $em = $this->tenantManager->getEntityManager($dominio);
            
            // Debug: Verificar qué base de datos se está usando
            $connection = $em->getConnection();
            $actualDb = $connection->getDatabase();
            error_log("[RegionController] Tenant: $dominio, DB: $actualDb");

            $regions = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Region', 'r')
                ->where('r.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->orderBy('r.name', 'ASC')
                ->getQuery()
                ->getResult();

            $data = [];
            foreach ($regions as $region) {
                $data[] = [
                    'id' => $region->getId(),
                    'name' => $region->getName(),
                    'status' => $region->getStatus()->value,
                ];
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            error_log("[RegionController] Error: " . $e->getMessage());
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/{id}/companies', name: 'app_region_companies', methods: ['GET'])]
    public function getCompaniesByRegion(string $dominio, int $id): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $region = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Region', 'r')
                ->where('r.id = :id')
                ->andWhere('r.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$region) {
                return new JsonResponse(['error' => 'Region not found'], 404);
            }

            $companies = $region->getCompanies();

            $data = [];
            foreach ($companies as $company) {
                if ($company->getStatus() === Status::ACTIVE) {
                    $data[] = [
                        'id' => $company->getId(),
                        'name' => $company->getName(),
                    ];
                }
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/{id}/details', name: 'app_region_details', methods: ['GET'])]
    public function details(string $dominio, int $id): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['error' => 'Dominio no especificado'], 400);
        }

        try {
            $em = $this->tenantManager->getEntityManager();

            $region = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Region', 'r')
                ->where('r.id = :id')
                ->andWhere('r.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$region) {
                return new JsonResponse(['error' => 'Región no encontrada'], 404);
            }

            return new JsonResponse([
                'id' => $region->getId(),
                'name' => $region->getName(),
                'status' => $region->getStatus()->value
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/edit', name: 'app_region_edit', methods: ['POST'])]
    public function edit(string $dominio, Request $request, int $id): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Dominio no especificado'], 400);
        }
        try {
            $em = $this->tenantManager->getEntityManager();

            $region = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Region', 'r')
                ->where('r.id = :id')
                ->andWhere('r.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$region) {
                return new JsonResponse(['status' => 'error', 'message' => 'Región no encontrada'], 404);
            }

            $form = $this->createForm(RegionType::class, $region, [
                'csrf_protection' => true,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Verificar si otra región activa ya tiene ese nombre
                $existing = $em->createQueryBuilder()
                    ->select('r')
                    ->from('App\Entity\App\Region', 'r')
                    ->where('r.name = :name')
                    ->andWhere('r.status = :status')
                    ->setParameter('name', $region->getName())
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existing && $existing->getId() !== $region->getId()) {
                    return new JsonResponse(['status' => 'error', 'message' => 'Ya existe otra región activa con ese nombre.'], 400);
                }

                $region->setUpdatedAt(new \DateTimeImmutable());
                $em->flush();

                return new JsonResponse(['status' => 'success', 'message' => 'Región actualizada correctamente']);
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

    #[Route('/{id}/delete', name: 'app_region_delete', methods: ['POST'])]
    public function delete(string $dominio, Request $request, int $id): JsonResponse
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $region = $entityManager->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Region', 'r')
                ->where('r.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$region) {
                return new JsonResponse(['status' => 'error', 'message' => 'Región no encontrada'], 404);
            }

            if ($this->isCsrfTokenValid('delete_region', $request->request->get('_token'))) {
                $region->setStatus(Status::INACTIVE);
                $region->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->flush();
                return new JsonResponse(['status' => 'success', 'message' => 'Región eliminada correctamente']);
            } else {
                return new JsonResponse(['status' => 'error', 'message' => 'Token de seguridad inválido'], 403);
            }

        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/by-company/{id}', name: 'app_regions_by_company', methods: ['GET'])]
    public function getRegionsByCompany(string $dominio, string $id): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $company = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$company) {
                return new JsonResponse(['error' => 'Company not found'], 404);
            }

            $region = $company->getRegion();

            if (!$region) {
                return new JsonResponse(['error' => 'No region found for this company'], 404);
            }

            return new JsonResponse([
                'id' => $region->getId(),
                'name' => $region->getName(),
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }

    #[Route('/datatable', name: 'app_region_datatable', methods: ['GET'])]
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

        $columns = ['id', 'name', 'status'];
        $orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'id';

        $qb = $em->createQueryBuilder()
            ->select('e')
            ->from('App\Entity\App\Region', 'e')
            
            ->where('e.status = :status')
            ->setParameter('status', Status::ACTIVE);

        if (!empty($searchValue)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('e.name', ':search')
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

        $totalRecords = (int) $em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from('App\Entity\App\Region', 'e')
            ->where('e.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $data = [];
        foreach ($results as $item) {
            
            $data[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
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

    #[Route('/{id}', name: 'app_region_show', methods: ['GET'])]
    public function show(string $dominio, int $id): Response
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $region = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Region', 'r')
                ->where('r.id = :id')
                ->andWhere('r.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$region) {
                throw $this->createNotFoundException('Region not found');
            }

            return $this->render('region/show.html.twig', [
                'region' => $region,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant not found');
        }
    }


    }
