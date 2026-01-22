<?php

namespace App\Controller;

use App\Entity\App\User;
use App\Enum\Status;
use App\Form\UserAdminEditType;
use App\Form\UserAdminType;
use App\Service\ImageUploadService;
use App\Service\RegionAccessService;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{dominio}/admin/user')]
final class UserAdminController extends AbstractController
{
    private TenantManager $tenantManager;
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(TenantManager $tenantManager, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->tenantManager = $tenantManager;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    #[Route('', name: 'app_user_admin_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        try {
            // Configurar explícitamente el tenant antes de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $em = $this->tenantManager->getEntityManager();
            
            $user = new User();
            $form = $this->createForm(UserAdminType::class, $user, [
                'action' => $this->generateUrl('app_user_admin_new', ['dominio' => $dominio]),
                'method' => 'POST',
                'dominio' => $dominio,
            ]);

            // Generar token CSRF específico para eliminación
            $csrfToken = $this->csrfTokenManager->getToken('delete_user_admin')->getValue();

            // Crear formulario de edición vacío (se usará como template para el modal)
            $editUser = new User();
            $editForm = $this->createForm(UserAdminEditType::class, $editUser, [
                'action' => '', // La acción se establecerá mediante JavaScript
                'method' => 'POST',
                'dominio' => $dominio,
            ]);

            return $this->render('user/admin/index.html.twig', [
                'form' => $form->createView(),
                'editForm' => $editForm->createView(),
                'dominio' => $dominio,
                'csrf_token_delete' => $csrfToken,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    // ... (datatable and new methods remain unchanged) ...

    #[Route('/datatable', name: 'app_user_admin_datatable', methods: ['GET'])]
    public function datatable(string $dominio, Request $request): JsonResponse
    {
        if (empty($dominio)) {
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }

        // Configurar explícitamente el tenant antes de obtener el EntityManager
        $this->tenantManager->setCurrentTenant($dominio);
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

        $columns = ['id', 'email', 'name', 'phone_number', 'role.name', 'status', 'created_at'];
        $orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'id';

        $qb = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->leftJoin('u.role', 'r')
            ->where('u.status = :status')
            ->andWhere('r.name IN (:roles)')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('roles', ['ROLE_ADMIN', 'ROLE_LIDER']);

        // Total Records
        $totalRecordsQb = clone $qb;
        $totalRecords = (int) $totalRecordsQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        if (!empty($searchValue)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('u.email', ':search'),
                    $qb->expr()->like('u.name', ':search'),
                    $qb->expr()->like('u.last_name', ':search'),
                    $qb->expr()->like('u.phone_number', ':search'),
                    $qb->expr()->like('r.name', ':search')
                )
            )->setParameter('search', '%' . $searchValue . '%');
        }

        $countQb = clone $qb;
        $totalFiltered = (int) $countQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        // Fix ordering
        if (strpos($orderBy, '.') !== false) {
            [$alias, $field] = explode('.', $orderBy);
            $qb->orderBy($alias . '.' . $field, $orderDir);
        } else {
            $qb->orderBy('u.' . $orderBy, $orderDir);
        }

        $qb->setFirstResult($start)->setMaxResults($length);

        $results = $qb->getQuery()->getResult();

        $data = [];
        foreach ($results as $user) {
            $regions = [];
            foreach ($user->getRegions() as $region) {
                $regions[] = $region->getName();
            }

            $data[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName() . ' ' . $user->getLastName(),
                'phone' => $user->getPhoneNumber(),
                'role' => $user->getRole() ? $user->getRole()->getName() : '',
                'regions' => implode(', ', $regions),
                'status' => $user->getStatus() ? $user->getStatus()->value : '',
                'created_at' => $user->getCreatedAt() ? $user->getCreatedAt()->format('d/m/Y') : '',
            ];
        }

        return new JsonResponse([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    // ... (new method remains unchanged) ...

    #[Route('/new', name: 'app_user_admin_new', methods: ['GET', 'POST'])]
    public function new(string $dominio, Request $request, UserPasswordHasherInterface $passwordHasher, ImageUploadService $imageUploadService): Response
    {
        try {
            // Configurar explícitamente el tenant antes de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $entityManager = $this->tenantManager->getEntityManager();
            
            $user = new User();
            $form = $this->createForm(UserAdminType::class, $user, [
                'dominio' => $dominio,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $user->setStatus(Status::ACTIVE);
                $user->setCreatedAt(new \DateTimeImmutable());
                $user->setUpdatedAt(new \DateTimeImmutable());

                // Password
                $plainPassword = $form->get('password')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                // Photo
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    $photoPath = $imageUploadService->uploadImage($photoFile, 'profile');
                    $user->setPhoto($photoPath);
                }

                $entityManager->persist($user);
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Usuario creado correctamente']);
                }

                return $this->redirectToRoute('app_user_admin_index', ['dominio' => $dominio]);
            }
            
            if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
                 $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                return new JsonResponse(['status' => 'error', 'message' => 'Error de validación', 'errors' => $errors], 400);
            }

            // Si no es AJAX y es GET (o POST fallido sin AJAX), redirigir al index
            return $this->redirectToRoute('app_user_admin_index', ['dominio' => $dominio]);

        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                 return new JsonResponse(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
            }
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}/details', name: 'app_user_admin_details', methods: ['GET'])]
    public function details(string $dominio, int $id): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['error' => 'Dominio no especificado'], 400);
        }

        try {
            // Configurar explícitamente el tenant antes de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $em = $this->tenantManager->getEntityManager();
            $user = $em->find(User::class, $id);

            if (!$user) {
                return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
            }

            $regions = [];
            foreach ($user->getRegions() as $region) {
                $regions[] = [
                    'id' => $region->getId(),
                    'name' => $region->getName()
                ];
            }

            return new JsonResponse([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'last_name' => $user->getLastName(),
                'phone' => $user->getPhoneNumber(),
                'photo' => $user->getPhoto(),
                'status' => $user->getStatus()->value,
                'created_at' => $user->getCreatedAt() ? $user->getCreatedAt()->format('d/m/Y H:i') : null,
                'role' => $user->getRole() ? [
                    'id' => $user->getRole()->getId(),
                    'name' => $user->getRole()->getName()
                ] : null,
                'regions' => $regions,
                'regionIds' => array_column($regions, 'id'),
                'companyId' => $user->getCompany() ? $user->getCompany()->getId() : null,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/edit', name: 'app_user_admin_edit', methods: ['POST'])]
    public function edit(string $dominio, Request $request, int $id, UserPasswordHasherInterface $passwordHasher, ImageUploadService $imageUploadService): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Dominio no especificado'], 400);
        }
        try {
            // Configurar explícitamente el tenant antes de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $entityManager = $this->tenantManager->getEntityManager();

            $user = $entityManager->find(User::class, $id);
            if (!$user) {
                return new JsonResponse(['status' => 'error', 'message' => 'Usuario no encontrado'], 404);
            }

            $form = $this->createForm(UserAdminEditType::class, $user, [
                'csrf_protection' => true,
                'dominio' => $dominio,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Manejar el password si se proporcionó
                $plainPassword = $form->get('password')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                // Manejar la foto de perfil si se subió
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    try {
                        $photoPath = $imageUploadService->uploadImage($photoFile, 'profile', $user->getId());
                        $user->setPhoto($photoPath);
                    } catch (\Exception $e) {
                        return new JsonResponse(['status' => 'error', 'message' => 'Error al subir la imagen: ' . $e->getMessage()], 500);
                    }
                }

                // Handle Regions manually if needed, but FormType should handle it if configured correctly.
                // However, since we are using EntityType in the form, it expects entities managed by the EM.
                // The form submission handles the binding.

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

    #[Route('/{id}/delete', name: 'app_user_admin_delete', methods: ['POST'])]
    public function delete(string $dominio, Request $request, int $id): JsonResponse
    {
        try {
            // Configurar explícitamente el tenant antes de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $entityManager = $this->tenantManager->getEntityManager();

            $user = $entityManager->find(User::class, $id);
            if (!$user) {
                return new JsonResponse(['status' => 'error', 'message' => 'Usuario no encontrado'], 404);
            }

            // Use the specific token for admin deletion
            if ($this->isCsrfTokenValid('delete_user_admin', $request->request->get('_token'))) {
                $user->setStatus(Status::INACTIVE);
                $entityManager->flush();
                return new JsonResponse(['status' => 'success', 'message' => 'Usuario eliminado correctamente']);
            } else {
                return new JsonResponse(['status' => 'error', 'message' => 'Token de seguridad inválido'], 403);
            }

        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/edit-complete', name: 'app_user_admin_edit_complete', methods: ['GET', 'POST'])]
    public function editComplete(string $dominio, Request $request, int $id, UserPasswordHasherInterface $passwordHasher, ImageUploadService $imageUploadService): Response
    {
        try {
            // Configurar explícitamente el tenant antes de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $entityManager = $this->tenantManager->getEntityManager();

            $user = $entityManager->find(User::class, $id);
            if (!$user) {
                throw $this->createNotFoundException('Usuario no encontrado');
            }

            $form = $this->createForm(UserAdminEditType::class, $user, [
                'dominio' => $dominio,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Manejar el password si se proporcionó
                $plainPassword = $form->get('password')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                // Manejar la foto de perfil si se subió
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    try {
                        $photoPath = $imageUploadService->uploadImage($photoFile, 'profile', $user->getId());
                        $user->setPhoto($photoPath);
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Error al subir la imagen: ' . $e->getMessage());
                    }
                }

                $entityManager->flush();

                $this->addFlash('success', 'Usuario actualizado correctamente.');
                return $this->redirectToRoute('app_user_admin_edit_complete', ['dominio' => $dominio, 'id' => $user->getId()]);
            }

            // Obtener formularios contestados por el usuario usando consulta directa
            $formEntries = $entityManager->createQueryBuilder()
                ->select('fe')
                ->from('App\Entity\App\FormEntry', 'fe')
                ->where('fe.user = :user')
                ->setParameter('user', $user)
                ->orderBy('fe.created_at', 'DESC')
                ->getQuery()
                ->getResult();

            return $this->render('user/admin/edit_complete.html.twig', [
                'user' => $user,
                'form' => $form,
                'dominio' => $dominio,
                'form_entries' => $formEntries
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    }
