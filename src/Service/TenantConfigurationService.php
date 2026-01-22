<?php

namespace App\Service;



use App\Entity\Master\Tenant;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class TenantConfigurationService
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    )
    {
    }

    public function getCurrentModules(string $dominio): array
    {
        $this->tenantManager->setCurrentTenant('Master');
        $em = $this->tenantManager->getEntityManager();

        $tenant = $em->getRepository(Tenant::class)->findOneBy(['dominio' => $dominio]);

        if (!$tenant) {
            throw new NotFoundHttpException('Tenant not found');
        }

        $features = $tenant->getFeatures();


        return array_map(
            fn(string $status) => $status,
            $features
        );
    }
}