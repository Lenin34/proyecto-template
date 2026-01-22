<?php

namespace App\Controller;

use App\Entity\App\Beneficiary;
use App\Entity\App\Company;
use App\Entity\App\Region;
use App\Entity\App\Role;
use App\Entity\App\User;
use App\Enum\Status;
use App\Form\UserType;
use App\Service\ImageUploadService;
use App\Service\TenantManager;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{dominio}/user')]
final class UserController extends AbstractController
{
    private ImageUploadService $imageUploadService;
    private TenantManager $tenantManager;
    private UserPasswordHasherInterface $passwordHasher;
    private LoggerInterface $logger;
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(
        ImageUploadService $imageUploadService, 
        TenantManager $tenantManager, 
        UserPasswordHasherInterface $passwordHasher,
        LoggerInterface $logger,
        CsrfTokenManagerInterface $csrfTokenManager
    )
    {
        $this->imageUploadService = $imageUploadService;
        $this->tenantManager = $tenantManager;
        $this->passwordHasher = $passwordHasher;
        $this->logger = $logger;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        if (empty($dominio)) {
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }

        $em = $this->tenantManager->getEntityManager();

        // Get active companies for the modal form
        $companies = $em->createQueryBuilder()
            ->select('c')
            ->from('App\Entity\App\Company', 'c')
            ->where('c.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Get all roles for the modal form
        $roles = $em->createQueryBuilder()
            ->select('ro')
            ->from('App\Entity\App\Role', 'ro')
            ->orderBy('ro.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Generar token CSRF específico para eliminación
        $csrfToken = $this->csrfTokenManager->getToken('delete_user')->getValue();

        return $this->render('user/index.html.twig', [
            'dominio' => $dominio,
            'companies' => $companies,
            'roles' => $roles,
            'csrf_token_delete' => $csrfToken, // Pasamos el token explícito
        ]);
    }

    #[Route('/datatable', name: 'app_user_datatable', methods: ['GET'])]
    public function datatable(string $dominio, Request $request): JsonResponse
    {
        if (empty($dominio)) {
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }

        $em = $this->tenantManager->getEntityManager();

        // Get ROLE_USER
        $userRole = $em->createQueryBuilder()
            ->select('r')
            ->from('App\Entity\App\Role', 'r')
            ->where('r.name = :name')
            ->setParameter('name', 'ROLE_USER')
            ->getQuery()
            ->getOneOrNullResult();

        // DataTables parameters - safe access
        $draw = (int) $request->query->get('draw', 1);
        $start = (int) $request->query->get('start', 0);
        $length = (int) $request->query->get('length', 25);
        
        // Search parameter
        $search = $request->query->all('search');
        $searchValue = isset($search['value']) ? $search['value'] : '';
        
        // Order parameter
        $order = $request->query->all('order');
        $orderColumn = isset($order[0]['column']) ? (int) $order[0]['column'] : 0;
        $orderDir = isset($order[0]['dir']) ? $order[0]['dir'] : 'asc';

        // Column mapping for ordering
        // [0] Actions, [1] name, [2] company, [3] region, [4] birthday, [5] phone, [6] email, [7] employee_number, [8] curp, [9] gender, [10] education
        $columns = [
            0 => null,           // Actions (not orderable)
            1 => 'name',         // Nombre
            2 => 'c.name',       // Empresa
            3 => 'r.name',       // Región
            4 => 'birthday',     // Fecha Nac.
            5 => 'phone_number', // Teléfono
            6 => 'email',        // Email
            7 => 'employee_number', // N° Empleado
            8 => 'curp',         // CURP
            9 => 'gender',       // Género
            10 => 'education',   // Educación
        ];
        $orderBy = isset($columns[$orderColumn]) && $columns[$orderColumn] ? $columns[$orderColumn] : 'name';

        // Base query with eager loading (including Region via Company)
        $qb = $em->createQueryBuilder()
            ->select('u', 'c', 'r')
            ->from('App\Entity\App\User', 'u')
            ->leftJoin('u.company', 'c')
            ->leftJoin('c.region', 'r')
            ->where('u.status = :status')
            ->andWhere('u.role = :role')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('role', $userRole);

        // Apply search filter
        if (!empty($searchValue)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('u.name', ':search'),
                    $qb->expr()->like('u.last_name', ':search'),
                    $qb->expr()->like('u.email', ':search'),
                    $qb->expr()->like('u.phone_number', ':search'),
                    $qb->expr()->like('u.curp', ':search'),
                    $qb->expr()->like('c.name', ':search'),
                    $qb->expr()->like('r.name', ':search')
                )
            )->setParameter('search', '%' . $searchValue . '%');
        }

        // Count total filtered records
        $countQb = clone $qb;
        $totalFiltered = (int) $countQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        // Apply ordering
        if (strpos($orderBy, '.') !== false) {
            [$alias, $field] = explode('.', $orderBy);
            $qb->orderBy($alias . '.' . $field, $orderDir);
        } else {
            $qb->orderBy('u.' . $orderBy, $orderDir);
        }

        // Apply pagination
        $qb->setFirstResult($start)->setMaxResults($length);

        $users = $qb->getQuery()->getResult();

        // Count total records (without filter)
        $totalRecords = (int) $em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from('App\Entity\App\User', 'u')
            ->where('u.status = :status')
            ->andWhere('u.role = :role')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('role', $userRole)
            ->getQuery()
            ->getSingleScalarResult();

        // Format data for DataTables
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'name' => $user->getName() . ' ' . $user->getLastName(),
                'company' => $user->getCompany() ? $user->getCompany()->getName() : '',
                'region' => ($user->getCompany() && $user->getCompany()->getRegion()) ? $user->getCompany()->getRegion()->getName() : 'Sin región',
                'birthday' => $user->getBirthday() ? $user->getBirthday()->format('d/m/Y') : 'N/A',
                'phone_number' => $user->getPhoneNumber() ?? '',
                'email' => $user->getEmail() ?? '',
                'employee_number' => $user->getEmployeeNumber() ?? '',
                'curp' => $user->getCurp() ?? '',
                'gender' => $user->getGender() ?? '',
                'education' => $user->getEducation() ?? '',
            ];
        }

        return new JsonResponse([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    #[Route('/companies', name: 'app_user_companies', methods: ['GET'])]
    public function companies(string $dominio): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['error' => 'Dominio no especificado'], 400);
        }

        try {
            $em = $this->tenantManager->getEntityManager();
            
            $companies = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->orderBy('c.name', 'ASC')
                ->getQuery()
                ->getResult();

            $data = [];
            foreach ($companies as $company) {
                $data[] = [
                    'id' => $company->getId(),
                    'name' => $company->getName(),
                ];
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(string $dominio, Request $request): Response
    {

        if (empty($dominio)) {
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }

        $entityManager = $this->tenantManager->getEntityManager();
        $user = new User();
        $user->setStatus(Status::INACTIVE);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $form = $this->createForm(UserType::class, $user, ['dominio' => $dominio]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Manejar el password si se proporcionó
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Handle selected company from modal form (user[company])
            $selectedCompanyId = $request->request->all('user')['company'] ?? null;
            if ($selectedCompanyId) {
                $company = $entityManager->createQueryBuilder()
                    ->select('c')
                    ->from('App\Entity\App\Company', 'c')
                    ->where('c.id = :id')
                    ->setParameter('id', $selectedCompanyId)
                    ->getQuery()
                    ->getOneOrNullResult();
                if ($company) {
                    $user->setCompany($company);
                }
            }

            // Asignar ROLE_USER automáticamente (no se permite seleccionar rol desde el modal)
            $roleUser = $entityManager->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Role', 'r')
                ->where('r.name = :name')
                ->setParameter('name', 'ROLE_USER')
                ->getQuery()
                ->getOneOrNullResult();
            if ($roleUser) {
                $user->setRole($roleUser);
            }

            // Persist user first to get the ID for photo upload
            $user->setStatus(Status::ACTIVE);
            $user->setVerified(false);
            $entityManager->persist($user);
            $entityManager->flush();

            // Manejar la foto de perfil si se subió (después de tener el ID)
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                try {
                    $photoPath = $this->imageUploadService->uploadImage($photoFile, 'profile', $user->getId());
                    $user->setPhoto($photoPath);
                    $entityManager->flush();
                } catch (\Exception $e) {
                    // Log error but continue
                }
            }

            // Respuesta JSON para peticiones AJAX
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return new JsonResponse(['status' => 'success', 'message' => 'Usuario creado correctamente']);
            }

            return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
        }

        // Si el formulario no es válido, devolver errores en JSON
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return new JsonResponse(['status' => 'error', 'message' => 'Error de validación', 'errors' => $errors], 400);
            }
        }

        // Para peticiones GET, redirigir al index (el modal está ahí)
        return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
    }

    #[Route('/{id}/details', name: 'app_user_details', methods: ['GET'])]
    public function details(string $dominio, int $id): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['error' => 'Dominio no especificado'], 400);
        }

        try {
            $em = $this->tenantManager->getEntityManager();

            $user = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.id = :id')
                ->andWhere('u.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$user) {
                return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
            }

            // Get beneficiaries
            $beneficiaries = $em->createQueryBuilder()
                ->select('b')
                ->from('App\Entity\App\Beneficiary', 'b')
                ->where('b.user = :user')
                ->andWhere('b.status = :status')
                ->setParameter('user', $user->getId())
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getResult();

            $beneficiariesData = [];
            foreach ($beneficiaries as $b) {
                $beneficiariesData[] = [
                    'id' => $b->getId(),
                    'name' => $b->getName(),
                    'lastName' => $b->getLastName(),
                    'kinship' => $b->getKinship(),
                    'birthday' => $b->getBirthday() ? $b->getBirthday()->format('d/m/Y') : null,
                    'photo' => $b->getPhoto(),
                ];
            }

            $regionsData = [];
            $regionsIds = [];
            foreach ($user->getRegions() as $r) {
                $regionsData[] = $r->getName();
                $regionsIds[] = $r->getId();
            }

            return new JsonResponse([
                'id' => $user->getId(),
                'name' => $user->getName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhoneNumber(),
                'curp' => $user->getCurp(),
                'birthday' => $user->getBirthday() ? $user->getBirthday()->format('Y-m-d') : null,
                'gender' => $user->getGender(),
                'education' => $user->getEducation(),
                'employeeNumber' => $user->getEmployeeNumber(),
                'company' => $user->getCompany() ? $user->getCompany()->getName() : null,
                'companyId' => $user->getCompany() ? $user->getCompany()->getId() : null,
                'roleId' => $user->getRole() ? $user->getRole()->getId() : null,
                'photo' => $user->getPhoto(),
                'regions' => $regionsData,
                'regionsIds' => $regionsIds,
                'beneficiaries' => $beneficiariesData
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    public function show(string $dominio, int $id): Response
    {
        if (empty($dominio)) {
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }
        try {
            $em = $this->tenantManager->getEntityManager();

            $user = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.id = :id')
                ->andWhere('u.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$user) {
                throw $this->createNotFoundException('User not found');
            }
            $em = $this->tenantManager->getEntityManager();

            $beneficiaries = $em->createQueryBuilder()
                ->select('b')
                ->from('App\Entity\App\Beneficiary', 'b')
                ->where('b.user = :user')
                ->andWhere('b.status = :status')
                ->setParameter('user', $user->getId())
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getResult();

            return $this->render('user/show.html.twig', [
                'user' => $user,
                'dominio' => $dominio,
                'beneficiaries' => $beneficiaries,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['POST'])]
    public function edit(string $dominio, Request $request, int $id): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Dominio no especificado'], 400);
        }
        try {
            $em = $this->tenantManager->getEntityManager();

            $user = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.id = :id')
                ->andWhere('u.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$user) {
                return new JsonResponse(['status' => 'error', 'message' => 'Usuario no encontrado'], 404);
            }
            $entityManager = $this->tenantManager->getEntityManager();

            $form = $this->createForm(UserType::class, $user, [
                'dominio' => $dominio,
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Manejar el password si se proporcionó
                $plainPassword = $form->get('password')->getData();
                if ($plainPassword) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                // Handle selected company from form
                $selectedCompanyId = $request->request->get('selected_company');
                if ($selectedCompanyId) {
                    $company = $entityManager->createQueryBuilder()
                        ->select('c')
                        ->from('App\Entity\App\Company', 'c')
                        ->where('c.id = :id')
                        ->setParameter('id', $selectedCompanyId)
                        ->getQuery()
                        ->getOneOrNullResult();
                    if ($company) {
                        $user->setCompany($company);
                    }
                }

                // Asegurar que el Role esté manejado por el EntityManager
                if ($user->getRole()) {
                    $role = $entityManager->find('App\Entity\App\Role', $user->getRole()->getId());
                    if ($role) {
                        $user->setRole($role);
                    }
                }

                // Manejar la foto de perfil si se subió
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    try {
                        $photoPath = $this->imageUploadService->uploadImage($photoFile, 'profile', $user->getId());
                        $user->setPhoto($photoPath);
                    } catch (\Exception $e) {
                         return new JsonResponse(['status' => 'error', 'message' => 'Error al subir la imagen: ' . $e->getMessage()], 400);
                    }
                }

                $entityManager->flush();

                return new JsonResponse(['status' => 'success', 'message' => 'Usuario actualizado correctamente']);
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

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(string $dominio, Request $request, int $id): Response
    {
        $this->logger->info('=== DELETE USER METHOD CALLED ===');
        $this->logger->info('Dominio: ' . $dominio);
        $this->logger->info('User ID: ' . $id);
        $this->logger->info('Request Method: ' . $request->getMethod());
        $this->logger->info('Request URI: ' . $request->getRequestUri());
        
        $em = $this->tenantManager->getEntityManager();

        if (empty($dominio)) {
            $this->logger->error('ERROR: Dominio vacío');
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }

        try {
            $this->logger->info('Buscando usuario con ID: ' . $id);
            
            $user = $em->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\App\User', 'u')
                ->where('u.id = :id')
                ->andWhere('u.status = :status')
                ->setParameter('id', $id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$user) {
                $this->logger->error('ERROR: Usuario no encontrado con ID: ' . $id);
                throw $this->createNotFoundException('User not found');
            }
            
            // Validar token CSRF
            $submittedToken = $request->request->get('_token');
            
            if (!$this->isCsrfTokenValid('delete_user', $submittedToken)) {
                $this->logger->error('ERROR: Token CSRF inválido para eliminación de usuario');
                $this->addFlash('error', 'Token de seguridad inválido, por favor recarga la página');
                return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
            }
            
            $this->logger->info('✓ Token CSRF válido. Eliminando usuario...');
            
            $user->setStatus(Status::INACTIVE);
            $em->flush();

            $this->addFlash('success', 'Usuario eliminado correctamente');
            return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
        } catch (\Exception $e) {
            $this->logger->error('Error eliminando usuario: ' . $e->getMessage());
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/download-template', name: 'app_user_download_template', priority: 10)]
    public function downloadTemplate(): StreamedResponse
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                ['NOMBRE', 'APELLIDOS', 'EMPRESA', 'FECHA DE NACIMIENTO', 'TELÉFONO', 'CORREO ELECTRÓNICO', 'N° DE EMPLEADO', 'CURP', 'GENERO', 'EDUCACIÓN', 'REGIÓN']
            ], null, 'A1');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            $response = new StreamedResponse(function () use ($writer) {
                $writer->save('php://output');
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment;filename="plantilla_usuarios.xlsx"');

            return $response;
        } catch (\Exception $e) {
            // Log the error
            error_log('Error generating Excel template: ' . $e->getMessage());

            // Create a simple CSV file as fallback
            $response = new StreamedResponse(function () {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['NOMBRE', 'APELLIDOS', 'EMPRESA', 'FECHA DE NACIMIENTO', 'TELÉFONO', 'CORREO ELECTRÓNICO', 'N° DE EMPLEADO', 'CURP', 'GENERO', 'EDUCACIÓN']);
                fclose($output);
            });

            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment;filename="plantilla_usuarios.csv"');

            return $response;
        }
    }

    #[Route('/bulk-upload', name: 'app_user_bulk_upload', methods: ['POST'], priority: 10)]
    public function bulkUpload(Request $request): RedirectResponse
    {
        $dominio = $request->attributes->get('dominio');
        if (empty($dominio)) {
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }
        try {
            $em = $this->tenantManager->getEntityManager();
            // Los repositorios se reemplazarán por consultas directas cuando se usen
        } catch (\Exception $e) {
            $this->addFlash('error', 'Tenant no encontrado o inválido: ' . $dominio);
            return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
        }

        try {
            $file = $request->files->get('excel_file');
            if (!$file) {
                $this->addFlash('error', 'No se ha subido ningún archivo.');
                return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $allRows = $sheet->toArray();
            $headerRow = $allRows[0] ?? [];

            $rows = [$headerRow];
            $rowMapping = [0];
            foreach (array_slice($allRows, 1) as $index => $row) {
                if (array_filter($row, fn($cell) => !empty(trim((string)$cell)))) {
                    $rows[] = $row;
                    $rowMapping[] = $index + 1;
                }
            }

            if (count($rows) <= 1) {
                $this->addFlash('error', 'El archivo no contiene datos para importar.');
                return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
            }

            $defaultCompany = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $roleUser = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Role', 'r')
                ->where('r.name = :name')
                ->setParameter('name', 'ROLE_USER')
                ->getQuery()
                ->getOneOrNullResult();

            if (!$roleUser) {
                $roleUser = $em->find('App\Entity\App\Role', 1);
            }
            if (!$roleUser) {
                $this->addFlash('error', 'No se encontró el rol necesario para los usuarios.');
                return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
            }

            $usersAdded = 0;
            $errors = [];
            $regionCache = [];
            $companyCache = [];

            foreach (array_slice($rows, 1) as $index => $row) {
                $originalRowNumber = $rowMapping[$index + 1] + 1;
                $rowNumber = $originalRowNumber + 1;

                if (count($row) < 10) {
                    $errors[] = "Fila $rowNumber: No tiene suficientes columnas.";
                    continue;
                }

                [$nombre, $apellidos, $empresa, $fechaNacimiento, $telefono, $email, $numEmpleado, $curp, $genero, $educacion, $regionUploaded] = $row;

                if (empty(trim((string)$nombre)) || empty(trim((string)$apellidos))) {
                    $errors[] = "Fila $rowNumber: Datos insuficientes. Se requiere al menos nombre y apellidos.";
                    continue;
                }

                // --- Región con caché ---
                $regionKey = strtolower(trim($regionUploaded));
                if (!isset($regionCache[$regionKey])) {
                    $region = $em->createQueryBuilder()
                        ->select('r')
                        ->from('App\Entity\App\Region', 'r')
                        ->where('r.name = :name')
                        ->andWhere('r.status = :status')
                        ->setParameter('name', $regionUploaded)
                        ->setParameter('status', Status::ACTIVE)
                        ->getQuery()
                        ->getOneOrNullResult();
                    if (!$region) {
                        $region = new Region();
                        $region->setName($regionUploaded);
                        $region->setStatus(Status::ACTIVE);
                        $region->setCreatedAt(new \DateTime());
                        $region->setUpdatedAt(new \DateTime());
                        $em->persist($region);
                    }
                    $regionCache[$regionKey] = $region;
                }
                $region = $regionCache[$regionKey];

                $empresaKey = strtolower(trim($empresa));
                if (!isset($companyCache[$empresaKey])) {
                    $company = null;
                    if (!empty($empresa)) {
                        $company = $em->createQueryBuilder()
                            ->select('c')
                            ->from('App\Entity\App\Company', 'c')
                            ->where('c.name = :name')
                            ->setParameter('name', $empresa)
                            ->getQuery()
                            ->getOneOrNullResult();
                        if (!$company) {
                            $company = new Company();
                            $company->setName($empresa);
                            $company->setStatus(Status::ACTIVE);
                            $company->setRegion($region);
                            $company->setCreatedAt(new \DateTime());
                            $company->setUpdatedAt(new \DateTime());
                            $em->persist($company);
                        }
                    }

                    // Si sigue siendo null, usamos la por defecto (por fallback)
                    if (!$company) {
                        $company = $defaultCompany;
                    }

                    $companyCache[$empresaKey] = $company;
                }
                $company = $companyCache[$empresaKey];


                if (!$company) {
                    $errors[] = "Fila $rowNumber: No se encontró la empresa '$empresa' y no hay empresa por defecto.";
                    continue;
                }

                try {
                    $user = new User();
                    $user->setName(trim((string)$nombre));
                    $user->setLastName(trim((string)$apellidos));

                    $emailValue = trim((string)($email ?? ''));
                    if ($emailValue) {
                        $existingUser = $em->createQueryBuilder()
                            ->select('u')
                            ->from('App\Entity\App\User', 'u')
                            ->where('u.email = :email')
                            ->setParameter('email', $emailValue)
                            ->getQuery()
                            ->getOneOrNullResult();
                        if ($existingUser) {
                            $errors[] = "Fila $rowNumber: Ya existe un usuario con el correo '$emailValue'.";
                            continue;
                        }
                        $user->setEmail($emailValue);
                    } else {
                        $user->setEmail('user_' . uniqid() . '@placeholder.com');
                    }

                    $user->setPhoneNumber(trim((string)($telefono ?? '')));
                    $user->setCurp(trim((string)($curp ?? '')));
                    $user->setEmployeeNumber(trim((string)($numEmpleado ?? '')));
                    $user->setGender(trim((string)($genero ?? '')));
                    $user->setEducation(trim((string)($educacion ?? '')));
                    $user->setCompany($company);
                    $user->addRegion($region);
                    $user->setRole($roleUser);

                    if (!empty($fechaNacimiento)) {
                        try {
                            $fecha = \DateTime::createFromFormat('Y-m-d', $fechaNacimiento)
                                ?: \DateTime::createFromFormat('d/m/Y', $fechaNacimiento)
                                    ?: new \DateTime($fechaNacimiento);
                            $user->setBirthday($fecha);
                        } catch (\Exception $e) {
                            $errors[] = "Fila $rowNumber: Formato de fecha inválido.";
                        }
                    }

                    $user->setStatus(Status::ACTIVE);
                    $user->setCreatedAt(new \DateTimeImmutable());
                    $user->setUpdatedAt(new \DateTimeImmutable());

                    $em->persist($user);
                    $usersAdded++;
                } catch (\Exception $e) {
                    $errors[] = "Fila $rowNumber: Error al crear el usuario: " . $e->getMessage();
                }
            }

            if ($usersAdded > 0) {
                $em->flush();
                $this->addFlash('success', "Se han importado $usersAdded usuarios correctamente.");
            } else {
                $this->addFlash('warning', "No se importaron usuarios. Verifique los datos del archivo.");
            }

            if (!empty($errors)) {
                $this->addFlash('warning', "Se encontraron algunos errores durante la importación:");
                foreach ($errors as $error) {
                    $this->addFlash('warning', $error);
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al procesar el archivo: ' . $e->getMessage());
            error_log('Error al procesar el archivo: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return $this->redirectToRoute('app_user_index', ['dominio' => $dominio]);
    }

    #[Route('/check-duplicates', name: 'app_user_check_duplicates', methods: ['POST'], priority: 10)]
    public function checkDuplicates(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $emails = $data['emails'] ?? [];

        if (empty($emails)) {
            return new JsonResponse(['duplicates' => []]);
        }

        $em = $this->tenantManager->getEntityManager();

        // Buscar emails existentes
        $qb = $em->createQueryBuilder();
        $existingEmails = $qb->select('u.email')
            ->from('App\Entity\App\User', 'u')
            ->where($qb->expr()->in('u.email', ':emails'))
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getSingleColumnResult();

        return new JsonResponse(['duplicates' => $existingEmails]);
    }

    /**
     * Endpoint AJAX para importar usuarios desde datos JSON pre-validados
     * Este endpoint recibe los datos ya filtrados desde el frontend
     */
    #[Route('/bulk-upload-ajax', name: 'app_user_bulk_upload_ajax', methods: ['POST'], priority: 10)]
    public function bulkUploadAjax(Request $request): JsonResponse
    {
        $dominio = $request->attributes->get('dominio');
        if (empty($dominio)) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'DOMAIN_NOT_FOUND',
                'message' => 'Dominio no especificado en la ruta.'
            ], 400);
        }

        try {
            $em = $this->tenantManager->getEntityManager();
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error_code' => 'TENANT_ERROR',
                'message' => 'Tenant no encontrado o inválido: ' . $dominio
            ], 400);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $users = $data['users'] ?? [];

            if (empty($users)) {
                return new JsonResponse([
                    'success' => false,
                    'error_code' => 'NO_DATA',
                    'message' => 'No se recibieron datos de usuarios para importar.'
                ], 400);
            }

            // Obtener empresa por defecto
            $defaultCompany = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            // Obtener rol de usuario
            $roleUser = $em->createQueryBuilder()
                ->select('r')
                ->from('App\Entity\App\Role', 'r')
                ->where('r.name = :name')
                ->setParameter('name', 'ROLE_USER')
                ->getQuery()
                ->getOneOrNullResult();

            if (!$roleUser) {
                $roleUser = $em->find('App\Entity\App\Role', 1);
            }

            if (!$roleUser) {
                return new JsonResponse([
                    'success' => false,
                    'error_code' => 'ROLE_NOT_FOUND',
                    'message' => 'No se encontró el rol necesario para los usuarios.'
                ], 400);
            }

            $usersAdded = 0;
            $errors = [];
            $regionCache = [];
            $companyCache = [];

            foreach ($users as $index => $userData) {
                $rowNumber = $userData['_rowNumber'] ?? ($index + 2);

                $nombre = trim($userData['nombre'] ?? '');
                $apellidos = trim($userData['apellidos'] ?? '');
                $empresa = trim($userData['empresa'] ?? '');
                $fechaNacimiento = trim($userData['fechaNacimiento'] ?? '');
                $telefono = trim($userData['telefono'] ?? '');
                $email = trim($userData['email'] ?? '');
                $numEmpleado = trim($userData['numEmpleado'] ?? '');
                $curp = trim($userData['curp'] ?? '');
                $genero = trim($userData['genero'] ?? '');
                $educacion = trim($userData['educacion'] ?? '');
                $regionUploaded = trim($userData['region'] ?? '');

                // Validación básica (doble verificación)
                if (empty($nombre) || empty($apellidos)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Datos insuficientes. Se requiere al menos nombre y apellidos.'
                    ];
                    continue;
                }

                // --- Región con caché ---
                $regionKey = strtolower($regionUploaded);
                if (!isset($regionCache[$regionKey])) {
                    $region = null;
                    if (!empty($regionUploaded)) {
                        $region = $em->createQueryBuilder()
                            ->select('r')
                            ->from('App\Entity\App\Region', 'r')
                            ->where('r.name = :name')
                            ->andWhere('r.status = :status')
                            ->setParameter('name', $regionUploaded)
                            ->setParameter('status', Status::ACTIVE)
                            ->getQuery()
                            ->getOneOrNullResult();

                        if (!$region) {
                            $region = new Region();
                            $region->setName($regionUploaded);
                            $region->setStatus(Status::ACTIVE);
                            $region->setCreatedAt(new \DateTime());
                            $region->setUpdatedAt(new \DateTime());
                            $em->persist($region);
                        }
                    }
                    $regionCache[$regionKey] = $region;
                }
                $region = $regionCache[$regionKey];

                // --- Empresa con caché ---
                $empresaKey = strtolower($empresa);
                if (!isset($companyCache[$empresaKey])) {
                    $company = null;
                    if (!empty($empresa)) {
                        $company = $em->createQueryBuilder()
                            ->select('c')
                            ->from('App\Entity\App\Company', 'c')
                            ->where('c.name = :name')
                            ->setParameter('name', $empresa)
                            ->getQuery()
                            ->getOneOrNullResult();

                        if (!$company && $region) {
                            $company = new Company();
                            $company->setName($empresa);
                            $company->setStatus(Status::ACTIVE);
                            $company->setRegion($region);
                            $company->setCreatedAt(new \DateTime());
                            $company->setUpdatedAt(new \DateTime());
                            $em->persist($company);
                        }
                    }

                    if (!$company) {
                        $company = $defaultCompany;
                    }

                    $companyCache[$empresaKey] = $company;
                }
                $company = $companyCache[$empresaKey];

                if (!$company) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => "No se encontró la empresa '$empresa' y no hay empresa por defecto."
                    ];
                    continue;
                }

                try {
                    $user = new User();
                    $user->setName($nombre);
                    $user->setLastName($apellidos);

                    // Solo asignar email si viene en los datos del Excel
                    if (!empty($email)) {
                        // Verificar duplicado de email
                        $existingUser = $em->createQueryBuilder()
                            ->select('u')
                            ->from('App\Entity\App\User', 'u')
                            ->where('u.email = :email')
                            ->setParameter('email', $email)
                            ->getQuery()
                            ->getOneOrNullResult();

                        if ($existingUser) {
                            $errors[] = [
                                'row' => $rowNumber,
                                'message' => "Ya existe un usuario con el correo '$email'."
                            ];
                            continue;
                        }
                        $user->setEmail($email);
                    }
                    // Si no hay email, se deja como null (no se genera placeholder)

                    $user->setPhoneNumber($telefono);
                    $user->setCurp($curp);
                    $user->setEmployeeNumber($numEmpleado);
                    $user->setGender($genero);
                    $user->setEducation($educacion);
                    $user->setCompany($company);

                    if ($region) {
                        $user->addRegion($region);
                    }

                    $user->setRole($roleUser);

                    if (!empty($fechaNacimiento)) {
                        try {
                            $fecha = \DateTime::createFromFormat('Y-m-d', $fechaNacimiento)
                                ?: \DateTime::createFromFormat('d/m/Y', $fechaNacimiento)
                                    ?: new \DateTime($fechaNacimiento);
                            $user->setBirthday($fecha);
                        } catch (\Exception $e) {
                            // Ignorar error de fecha, continuar sin ella
                        }
                    }

                    $user->setStatus(Status::ACTIVE);
                    $user->setCreatedAt(new \DateTimeImmutable());
                    $user->setUpdatedAt(new \DateTimeImmutable());

                    $em->persist($user);
                    $usersAdded++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Error al crear el usuario: ' . $e->getMessage()
                    ];
                }
            }

            if ($usersAdded > 0) {
                $em->flush();
            }

            return new JsonResponse([
                'success' => true,
                'usersAdded' => $usersAdded,
                'totalReceived' => count($users),
                'errors' => $errors,
                'message' => $usersAdded > 0
                    ? "Se han importado $usersAdded usuario(s) correctamente."
                    : 'No se importaron usuarios.'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error en bulk upload AJAX: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'success' => false,
                'error_code' => 'PROCESSING_ERROR',
                'message' => 'Error al procesar los datos: ' . $e->getMessage()
            ], 500);
        }
    }

}
