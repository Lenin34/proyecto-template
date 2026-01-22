<?php

namespace App\Service;

use App\Entity\App\Region;
use App\Enum\Status;
use Doctrine\Common\Collections\Collection;

class NotificationAudienceService
{
    /**
     * Determina la audiencia de usuarios para una notificación basada en la región y las empresas seleccionadas.
     * 
     * @param Region|null $region La región del evento/beneficio
     * @param Collection $selectedCompanies Las empresas vinculadas específicamente (si las hay)
     * @return array<int, \App\Entity\App\User> Array de usuarios únicos indexados por ID
     */
    public function getAudienceUsers(?Region $region, Collection $selectedCompanies): array
    {
        $users = [];

        // 1. Si hay empresas seleccionadas explícitamente, la audiencia se limita a ellas.
        if (!$selectedCompanies->isEmpty()) {
            foreach ($selectedCompanies as $company) {
                // Opcional: Verificar que la empresa esté activa
                if (method_exists($company, 'getStatus') && $company->getStatus() !== Status::ACTIVE) {
                    continue;
                }

                foreach ($company->getUsers() as $user) {
                    if ($this->isValidUser($user)) {
                        $users[$user->getId()] = $user;
                    }
                }
            }
            return $users;
        }

        // 2. Si NO hay empresas seleccionadas, es un evento GLOBAL para la REGIÓN.
        if ($region) {
            // A. Incluir empleados de todas las empresas de la región
            foreach ($region->getCompanies() as $company) {
                if (method_exists($company, 'getStatus') && $company->getStatus() !== Status::ACTIVE) {
                    continue;
                }

                foreach ($company->getUsers() as $user) {
                    if ($this->isValidUser($user)) {
                        $users[$user->getId()] = $user;
                    }
                }
            }

            // B. Incluir usuarios asignados directamente a la región (ej. Admins, Multi-región)
            foreach ($region->getUsers() as $user) {
                if ($this->isValidUser($user)) {
                    $users[$user->getId()] = $user;
                }
            }
        }

        return $users;
    }

    private function isValidUser($user): bool
    {
        return $user->getStatus() === Status::ACTIVE;
    }
}
