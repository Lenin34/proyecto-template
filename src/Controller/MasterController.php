<?php

namespace App\Controller;

use App\Entity\Master\MasterUser;
use App\Entity\Master\Tenant;
use App\Enum\Features;
use App\Enum\Status;
use App\Form\TenantType;
use App\Service\TenantManager;
use App\Service\TenantLogoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/Master', defaults: ['dominio' => 'Master'])]
#[IsGranted('ROLE_MASTER_USER')]
final class MasterController extends AbstractController
{

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantLogoService $tenantLogoService,
    )
    {
    }

    #[Route('/panel', name: 'app_master', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        // Verificar que el usuario autenticado sea un MasterUser
        $user = $this->getUser();
        if (!$user instanceof MasterUser) {
            throw $this->createAccessDeniedException('Solo usuarios Master pueden acceder a este panel.');
        }

        $this->tenantManager->clearAllEntityManagers();


        $em = $this->tenantManager->getEntityManager();

        $tenants = $em->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Master\Tenant', 't')
            ->getQuery()
            ->getResult();


        return $this->render('master/index.html.twig', [
            'tenants' => $tenants,
            'dominio' => $dominio,
            'features' => Features::values()
        ]);
    }

    #[Route('/{rtenant}/edit', name: 'app_master_edit', methods: ['POST', 'GET'])]
    #[IsGranted('ROLE_MASTER_ADMIN')]
    public function editApp(Tenant $rtenant, Request $request, string $dominio): Response
    {
        // Verificar que el usuario autenticado sea un MasterUser
        $user = $this->getUser();
        if (!$user instanceof MasterUser) {
            throw $this->createAccessDeniedException('Solo usuarios Master pueden editar tenants.');
        }

        $em = $this->tenantManager->getEntityManager();

        $tenant = $em->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Master\Tenant', 't')
            ->where('t.id = :id')
            ->setParameter('id', $rtenant->getId())
            ->getQuery()
            ->getOneOrNullResult();

        $form = $this->createForm(TenantType::class, $tenant, [
            'dominio' => $dominio,
            'currentTenant' => $tenant,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Procesar logo si se subió
            /** @var UploadedFile $logoFile */
            $logoFile = $form->get('logo')->getData();

            if ($logoFile) {
                $logoPath = $this->tenantLogoService->uploadTenantLogo($logoFile, $tenant->getDominio());
                if ($logoPath) {
                    $tenant->setLogo($logoPath);
                    $this->addFlash('success', 'Logo subido exitosamente');
                } else {
                    $this->addFlash('error', 'Error al subir el logo. Verifique el formato y tamaño del archivo.');
                }
            }

            // Procesar todos los features disponibles para mantener consistencia
            $selected = $request->request->all('features');
            $allFeatures = Features::values();
            $features = [];

            // Asignar '1' a marcados y '0' a desmarcados para todos los features
            foreach ($allFeatures as $feature) {
                $features[$feature] = isset($selected[$feature]) ? '1' : '0';
            }

            $tenant->setFeatures($features);

            $tenant->setCreatedAt(new \DateTimeImmutable());
            $tenant->setUpdatedAt(new \DateTimeImmutable());

            $em->flush();
            $this->addFlash('success', 'Tenant successfully updated');
            return $this->redirectToRoute('app_master', ['dominio' => $dominio]);
        }
        // Obtener información del logo actual
        $logoInfo = $this->tenantLogoService->getTenantLogoInfo($tenant->getDominio());

        return $this->render('master/show.html.twig', [
            'form' => $form,
            'dominio' => $dominio,
            'tenant' => $tenant,
            'features' => Features::values(),
            'logoInfo' => $logoInfo
        ]);
    }

    #[Route('/new', name: 'app_master_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MASTER_ADMIN')]
    public function newApp(Request $request, string $dominio): Response
    {
        // Verificar que el usuario autenticado sea un MasterUser
        $user = $this->getUser();
        if (!$user instanceof MasterUser) {
            throw $this->createAccessDeniedException('Solo usuarios Master pueden crear tenants.');
        }

        $tenant = new Tenant();
        $form = $this->createForm(TenantType::class, $tenant);
        $form->handleRequest($request);

        $em = $this->tenantManager->getEntityManager();

        if ($form->isSubmitted() && $form->isValid()) {

            $tenant->setStatus(Status::ACTIVE);

            // Procesar todos los features disponibles para mantener consistencia
            $selected = $request->request->all('features');
            $allFeatures = Features::values();
            $features = [];

            // Asignar '1' a marcados y '0' a desmarcados para todos los features
            foreach ($allFeatures as $feature) {
                $features[$feature] = isset($selected[$feature]) ? '1' : '0';
            }

            $tenant->setFeatures($features);

            $tenant->setCreatedAt(new \DateTimeImmutable());
            $tenant->setUpdatedAt(new \DateTimeImmutable());

            $em->persist($tenant);
            $em->flush();

            // Procesar logo después de crear el tenant (necesita existir en BD primero)
            /** @var UploadedFile $logoFile */
            $logoFile = $form->get('logo')->getData();

            if ($logoFile) {
                $logoPath = $this->tenantLogoService->uploadTenantLogo($logoFile, $tenant->getDominio());
                if ($logoPath) {
                    $tenant->setLogo($logoPath);
                    $em->flush();
                    $this->addFlash('success', 'Tenant y logo creados exitosamente');
                } else {
                    $this->addFlash('warning', 'Tenant creado pero error al subir el logo. Puede subirlo después editando el tenant.');
                }
            }

            $this->addFlash('success', 'Tenant successfully created');
            return $this->redirectToRoute('app_master', ['dominio' => $dominio]);
        }

        return $this->render('master/show.html.twig', [
            'form' => $form,
            'dominio' => $dominio,
            'features' => Features::values(),
            'logoInfo' => null // Para nuevo tenant no hay logo aún
        ]);
    }

    #[Route('/{rtenant}/delete', name: 'app_master_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MASTER_ADMIN')]
    public function deleteApp(Tenant $rtenant, Request $request, string $dominio): Response
    {
        // Verificar que el usuario autenticado sea un MasterUser
        $user = $this->getUser();
        if (!$user instanceof MasterUser) {
            throw $this->createAccessDeniedException('Solo usuarios Master pueden eliminar tenants.');
        }

        $em = $this->tenantManager->getEntityManager();

        $tenant = $em->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Master\Tenant', 't')
            ->where('t.id = :id')
            ->setParameter('id', $rtenant->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($this->isCsrfTokenValid('delete'.$tenant->getId(), $request->request->get('_token'))) {

            if ($tenant->getStatus() === Status::ACTIVE) {
                $tenant->setStatus(Status::INACTIVE);
            } else {
                $tenant->setStatus(Status::ACTIVE);
            }

            $em->flush();
        } else {
            $this->addFlash('error', 'Invalid CSRF token');
        }

        $this->addFlash('success', 'Tenant successfully deleted');
        return $this->redirectToRoute('app_master', ['dominio' => $dominio]);

    }

    #[Route('/{rtenant}/delete-logo', name: 'app_master_delete_logo', methods: ['POST'])]
    public function deleteTenantLogo(Tenant $rtenant, Request $request, string $dominio): Response
    {
        $em = $this->tenantManager->getEntityManager();

        $tenant = $em->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Master\Tenant', 't')
            ->where('t.id = :id')
            ->setParameter('id', $rtenant->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if (!$tenant) {
            $this->addFlash('error', 'Tenant not found');
            return $this->redirectToRoute('app_master', ['dominio' => $dominio]);
        }

        if ($this->isCsrfTokenValid('delete_logo'.$tenant->getId(), $request->request->get('_token'))) {
            $success = $this->tenantLogoService->deleteTenantLogo($tenant->getDominio());

            if ($success) {
                $this->addFlash('success', 'Logo eliminado exitosamente');
            } else {
                $this->addFlash('error', 'Error al eliminar el logo');
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token');
        }

        return $this->redirectToRoute('app_master_edit', [
            'dominio' => $dominio,
            'rtenant' => $tenant->getId()
        ]);
    }
}
