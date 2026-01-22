<?php

namespace App\Controller;

use App\Entity\App\Beneficiary;
use App\Entity\App\Event;
use App\Entity\App\UnreadMessage;
use App\Enum\Status;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\TenantManager;
use App\Service\UserActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

#[Route('/{dominio}/dashboard')]
final class DashboardController extends AbstractController
{
    private TenantManager $tenantManager;
    private LoggerInterface $logger;
    private UserActivityService $userActivityService;

    public function __construct(
        TenantManager $tenantManager,
        LoggerInterface $logger,
        UserActivityService $userActivityService
    )
    {
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
        $this->userActivityService = $userActivityService;
    }
    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        string $dominio,
        Request $request
    ): Response {
        try {
            $this->logger->info("========== DASHBOARD CONTROLLER: index() called ==========");
            $this->logger->info("[DashboardController] Dominio: {dominio}", ['dominio' => $dominio]);
            $this->logger->info("[DashboardController] Request URI: {uri}", ['uri' => $request->getRequestUri()]);
            $this->logger->info("[DashboardController] Request Method: {method}", ['method' => $request->getMethod()]);

            // Configurar el tenant antes de cualquier operación
            $this->logger->info("[DashboardController] Tenant set: {tenant}", ['tenant' => $dominio]);

            // Verificar el usuario
            $user = $this->getUser();

            if ($user) {
                $this->logger->info("[DashboardController] User authenticated", [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ]);
            } else {
                $this->logger->info("[DashboardController] No user found");
            }

            if (!$user) {
                $this->logger->info("========== DASHBOARD CONTROLLER: Redirecting to LOGIN ==========");
                return $this->redirectToRoute('app_login', ['dominio' => $dominio]);
            }

            $this->logger->info("[DashboardController] User authenticated, loading dashboard data");

            try {
                // Obtener el EntityManager después de configurar el tenant
                $em = $this->tenantManager->getEntityManager();

                $this->logger->info('[DEBUG] Tenant activo: {tenant}', ['tenant' => $dominio]);
                $this->logger->info('[DEBUG] Conexión actual DB: {db}', ['db' => $em->getConnection()->getDatabase()]);

                // Verificar conexión ejecutando una query simple
                $conn = $em->getConnection();


                try {
                    $conn->executeQuery('SELECT 1');
                } catch (\Exception $e) {
                    throw new \RuntimeException('No se pudo establecer conexión a la base de datos: ' . $e->getMessage());
                }

                // Intentar cargar el usuario con SQL nativo primero
                $userId = $user->getId();
                $sql = 'SELECT id, email, status FROM User WHERE id = :id';
                $params = ['id' => $userId];
                $stmt = $conn->prepare($sql);

                $this->logger->info('[DB DEBUG] Ejecutando query en ' . $conn->getDatabase(), [
                    'sql' => $sql,
                    'params' => $params,
                ]);

                $stmt->bindValue('id', $userId);
                $result = $stmt->executeQuery()->fetchAssociative();

                $this->logger->info('[DB DEBUG] Resultado obtenido', ['result' => $result]);

                if (!$result) {
                    throw new \Exception("No se encontró el usuario con ID {$userId} en la base de datos");
                }

                // Cargar el usuario completo con Doctrine usando el EntityManager específico del tenant
                $this->logger->info('[DEBUG] Antes de find - EM DB: ' . $em->getConnection()->getDatabase());
                $this->logger->info('[DEBUG] EM object ID: ' . spl_object_id($em));

                // Usar directamente el EntityManager en lugar del repositorio para evitar problemas multi-tenant
                $user = $em->find(\App\Entity\App\User::class, $userId);
                $this->logger->info('[DEBUG] Después de find - EM DB: ' . $em->getConnection()->getDatabase());

                $this->logger->info('[DEBUG] Usuario cargado con Doctrine', [
                    'id' => $user ? $user->getId() : null,
                    'email' => $user ? $user->getEmail() : null,
                ]);

                if (!$user) {
                    throw new \Exception("No se pudo cargar el usuario con Doctrine");
                }
            } catch (\Exception $e) {
                $this->logger->error("[DashboardController] ERROR in user loading section", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => substr($e->getTraceAsString(), 0, 500)
                ]);
                $this->logger->error("========== DASHBOARD CONTROLLER: Redirecting to LOGIN due to error ==========");
                return $this->redirectToRoute('app_login', ['dominio' => $dominio]);
            }

            // EntityManager específico del tenant (sin usar getRepository para evitar problemas multi-tenant)
            $em = $this->tenantManager->getEntityManager();

            // Usuario actual
            $user = $this->getUser();
            $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

            // Consultas directas usando el EntityManager específico del tenant
            $companyCount = count($em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getResult());

            $agremiadosCount = $em->createQueryBuilder()
                ->select('COUNT(u.id)')
                ->from('App\Entity\App\User', 'u')
                ->where('u.role = :role AND u.status = :status')
                ->setParameter('role', 1)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getSingleScalarResult();

            // Conteo de usuarios registrados este mes
            $registeredThisMonthCount = $em->createQueryBuilder()
                ->select('COUNT(u.id)')
                ->from('App\Entity\App\User', 'u')
                ->innerJoin('u.role', 'r')
                ->where('u.status = :status')
                ->andWhere('u.created_at >= :thisMonth')
                ->andWhere('r.name = :roleName')
                ->setParameter('roleName', 'ROLE_USER')
                ->setParameter('status', Status::ACTIVE)
                ->setParameter('thisMonth', new \DateTime('first day of this month'))
                ->getQuery()
                ->getSingleScalarResult();

            if ($isAdmin) {
                $notificationCount = count($em->createQueryBuilder()
                    ->select('n')
                    ->from('App\Entity\App\Notification', 'n')
                    ->where('n.status = :status')
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getResult());

                $beneficiaryCount = count($em->createQueryBuilder()
                    ->select('b')
                    ->from('App\Entity\App\Beneficiary', 'b')
                    ->where('b.status = :status')
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getResult());

                $events = $em->createQueryBuilder()
                    ->select('e')
                    ->from('App\Entity\App\Event', 'e')
                    ->where('e.status = :status')
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getResult();


            } else {
                // Filtrar por regiones del usuario
                $userRegions = $user->getRegions();
                $regionIds = array_map(fn($region) => $region->getId(), $userRegions->toArray());

                // Notificaciones
                $notifications = $em->createQueryBuilder()
                    ->select('n')
                    ->from('App\Entity\App\Notification', 'n')
                    ->where('n.status = :status')
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getResult();
                $filteredNotifications = [];
                foreach ($notifications as $notification) {
                    foreach ($notification->getCompanies() as $company) {
                        $companyRegion = $company->getRegion();
                        if ($companyRegion && in_array($companyRegion->getId(), $regionIds)) {
                            $filteredNotifications[] = $notification;
                            break;
                        }
                    }
                }
                $notificationCount = count($filteredNotifications);

                // Beneficiarios
                $beneficiaries = $em->createQueryBuilder()
                    ->select('b')
                    ->from('App\Entity\App\Beneficiary', 'b')
                    ->where('b.status = :status')
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getResult();
                $filteredBeneficiaries = [];
                foreach ($beneficiaries as $beneficiary) {
                    $beneficiaryUser = $beneficiary->getUser();
                    if ($beneficiaryUser) {
                        foreach ($beneficiaryUser->getRegions() as $region) {
                            if (in_array($region->getId(), $regionIds)) {
                                $filteredBeneficiaries[] = $beneficiary;
                                break;
                            }
                        }
                    }
                }
                $beneficiaryCount = count($filteredBeneficiaries);

                // Eventos
                $allEvents = $em->createQueryBuilder()
                    ->select('e')
                    ->from('App\Entity\App\Event', 'e')
                    ->where('e.status = :status')
                    ->setParameter('status', Status::ACTIVE)
                    ->getQuery()
                    ->getResult();
                $events = [];
                foreach ($allEvents as $event) {
                    foreach ($event->getCompanies() as $company) {
                        $companyRegion = $company->getRegion();
                        if ($companyRegion && in_array($companyRegion->getId(), $regionIds)) {
                            $events[] = $event;
                            break;
                        }
                    }
                }
            }

            $this->userActivityService->logActivity($user, 'dashboard_view', 'User viewed dashboard', $request);

            $weeklyUsageData = $this->userActivityService->getWeeklyActivityData();
            $ageDistributionData = $this->generateAgeDistributionData($em);
            $educationDistributionData = $this->generateEducationDistributionData($em);

            $userRegions = $user->getRegions();
            $regionCompanies = [];
            foreach ($userRegions as $region) {
                foreach ($region->getCompanies() as $company) {
                    $regionCompanies[] = $company;
                }
            }

            $conversationsCount = count($em->createQueryBuilder()
                ->select('um')
                ->from('App\Entity\App\UnreadMessage', 'um')
                ->getQuery()
                ->getResult());

            // Get regions for filter dropdown
            $regions = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Region', 'r')
                ->where('r.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->orderBy('r.name', 'ASC')
                ->getQuery()
                ->getResult();

            $this->logger->info("[DashboardController] About to render dashboard template");
            $this->logger->info("[DashboardController] Template data prepared", [
                'notificationCount' => $notificationCount ?? 0,
                'beneficiaryCount' => $beneficiaryCount ?? 0,
                'eventsCount' => count($events ?? []),
                'companyCount' => $companyCount,
                'agremiadosCount' => $agremiadosCount,
                'conversationsCount' => $conversationsCount,
                'registeredThisMonthCount' => $registeredThisMonthCount,
                'regionsCount' => count($regions),
            ]);

            $response = $this->render('dashboard/index.html.twig', [
                'notificationCount' => $notificationCount ?? 0,
                'beneficiaryCount' => $beneficiaryCount ?? 0,
                'events' => $events ?? [],
                'weeklyUsageData' => $weeklyUsageData,
                'ageDistributionData' => $ageDistributionData,
                'companyCount' => $companyCount,
                'agremiadosCount' => $agremiadosCount,
                'conversationsCount' => $conversationsCount,
                'registeredThisMonthCount' => $registeredThisMonthCount,
                'educationDistributionData' => $educationDistributionData,
                'regions' => $regions,
            ]);

            $this->logger->info("[DashboardController] Dashboard template rendered successfully");
            $this->logger->info("========== DASHBOARD CONTROLLER: Returning response ==========");

            return $response;
        } catch (\Exception $e) {
            $this->logger->error("[DashboardController] EXCEPTION CAUGHT", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $this->createNotFoundException('Tenant not found: ' . $e->getMessage());
        }
    }

    #[Route('/filter-region', name: 'app_dashboard_filter_region', methods: ['POST'])]
    public function filterByRegion(string $dominio, Request $request): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $regionId = $request->request->get('region_id');

            // Si es 'all', no aplicar filtro por región
            if ($regionId === 'all') {
                $educationDistributionData = $this->generateEducationDistributionData($em);
                $ageDistributionData = $this->generateAgeDistributionData($em);
            } else {
                $educationDistributionData = $this->generateEducationDistributionData($em, $regionId);
                $ageDistributionData = $this->generateAgeDistributionData($em, $regionId);
            }

            return new JsonResponse([
                'success' => true,
                'educationDistribution' => $educationDistributionData,
                'ageDistribution' => $ageDistributionData
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al filtrar datos: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Generates age distribution data by gender from actual beneficiary data
     *
     * @param EntityManagerInterface $em The EntityManager for the current tenant
     * @return array Age distribution data by gender
     */
    private function generateAgeDistributionData($em, $regionId = null): array
    {
        // Define age ranges
        $ageRanges = [
            '78-85' => ['min' => 78, 'max' => 85],
            '68-75' => ['min' => 68, 'max' => 75],
            '58-68' => ['min' => 58, 'max' => 68],
            '48-58' => ['min' => 48, 'max' => 58],
            '38-48' => ['min' => 38, 'max' => 48],
            '28-38' => ['min' => 28, 'max' => 38],
            '18-28' => ['min' => 18, 'max' => 28]
        ];

        // Initialize counts for each age range and gender
        $menCounts = array_fill_keys(array_keys($ageRanges), 0);
        $womenCounts = array_fill_keys(array_keys($ageRanges), 0);

        // Get all active beneficiaries
        $queryBuilder = $em->createQueryBuilder()
            ->select('b')
            ->from('App\Entity\App\Beneficiary', 'b')
            ->where('b.status = :status')
            ->setParameter('status', Status::ACTIVE);

        // Filtrar por región si se especifica
        if ($regionId) {
            $queryBuilder->join('b.user', 'u')
                ->join('u.regions', 'r')
                ->andWhere('r.id = :regionId')
                ->setParameter('regionId', $regionId);
        }

        $beneficiaries = $queryBuilder->getQuery()->getResult();

        $now = new \DateTime();

        // Count beneficiaries by age range and gender
        foreach ($beneficiaries as $beneficiary) {
            $birthday = $beneficiary->getBirthday();
            $gender = $beneficiary->getGender();

            if (!$birthday) {
                continue;
            }

            // Calculate age
            $age = $birthday->diff($now)->y;

            // Determine which age range this beneficiary falls into
            foreach ($ageRanges as $range => $limits) {
                if ($age >= $limits['min'] && $age <= $limits['max']) {
                    // Increment the appropriate counter based on gender
                    if (strtolower($gender) === 'masculino' || strtolower($gender) === 'hombre' || strtolower($gender) === 'm') {
                        $menCounts[$range]++;
                    } elseif (strtolower($gender) === 'femenino' || strtolower($gender) === 'mujer' || strtolower($gender) === 'f') {
                        $womenCounts[$range]++;
                    }
                    break;
                }
            }
        }

        // Convert counts to the format expected by the chart
        return [
            'categories' => array_keys($ageRanges),
            'men' => array_values($menCounts),
            'women' => array_values($womenCounts)
        ];
    }

    /**
     * Generates education distribution data by gender from actual beneficiary data
     *
     * @param EntityManagerInterface $em The EntityManager for the current tenant
     * @return array Education distribution data by gender
     */
    private function generateEducationDistributionData($em, $regionId = null): array
    {
        // Define education levels in order from highest to lowest
        $educationLevels = ['MAESTRIA', 'POSGRADO', 'UNIVERSIDAD', 'PREPARATORIA', 'SECUNDARIA', 'PRIMARIA', 'PRESCOLAR'];

        // Initialize counts for each education level and gender
        $menCounts = array_fill_keys($educationLevels, 0);
        $womenCounts = array_fill_keys($educationLevels, 0);

        // Get all active beneficiaries with education data
        $queryBuilder = $em->createQueryBuilder()
            ->select('b')
            ->from('App\Entity\App\Beneficiary', 'b')
            ->where('b.status = :status')
            ->andWhere('b.education IS NOT NULL')
            ->setParameter('status', Status::ACTIVE);

        // Filtrar por región si se especifica
        if ($regionId) {
            $queryBuilder->join('b.user', 'u')
                ->join('u.regions', 'r')
                ->andWhere('r.id = :regionId')
                ->setParameter('regionId', $regionId);
        }

        $beneficiaries = $queryBuilder->getQuery()->getResult();

        // Count beneficiaries by education level and gender
        foreach ($beneficiaries as $beneficiary) {
            $education = strtoupper(trim($beneficiary->getEducation()));
            $gender = strtolower(trim($beneficiary->getGender()));

            // Normalize education values to match our categories
            $normalizedEducation = $this->normalizeEducationLevel($education);

            if ($normalizedEducation && in_array($normalizedEducation, $educationLevels)) {
                // Increment the appropriate counter based on gender
                if (in_array($gender, ['masculino', 'hombre', 'm', 'male'])) {
                    $menCounts[$normalizedEducation]++;
                } elseif (in_array($gender, ['femenino', 'mujer', 'f', 'female'])) {
                    $womenCounts[$normalizedEducation]++;
                }
            }
        }

        // Convert counts to the format expected by the chart
        return [
            'categories' => $educationLevels,
            'men' => array_values($menCounts),
            'women' => array_values($womenCounts)
        ];
    }

    /**
     * Normalize education level to match chart categories
     *
     * @param string $education Raw education value from database
     * @return string|null Normalized education level
     */
    private function normalizeEducationLevel(string $education): ?string
    {
        $education = strtoupper(trim($education));

        // Map various education formats to our standard categories
        $educationMap = [
            'MAESTRIA' => 'MAESTRIA',
            'MAESTRÍA' => 'MAESTRIA',
            'MASTER' => 'MAESTRIA',
            'POSGRADO' => 'POSGRADO',
            'POSTGRADO' => 'POSGRADO',
            'UNIVERSIDAD' => 'UNIVERSIDAD',
            'UNIVERSITARIO' => 'UNIVERSIDAD',
            'LICENCIATURA' => 'UNIVERSIDAD',
            'PREPARATORIA' => 'PREPARATORIA',
            'BACHILLERATO' => 'PREPARATORIA',
            'SECUNDARIA' => 'SECUNDARIA',
            'PRIMARIA' => 'PRIMARIA',
            'PRESCOLAR' => 'PRESCOLAR',
            'PREESCOLAR' => 'PRESCOLAR',
            'KINDER' => 'PRESCOLAR'
        ];

        return $educationMap[$education] ?? null;
    }
}
