<?php

namespace App\Security;

use App\Entity\Master\MasterUser;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Psr\Log\LoggerInterface;

/**
 * Provider para usuarios del tenant Master
 */
final class MasterUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private TenantManager $tenantManager;
    private ?LoggerInterface $logger;

    public function __construct(
        TenantManager $tenantManager,
        ?LoggerInterface $logger = null
    ) {
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
    }

    public function getUserIdentifier(UserInterface $user): string
    {
        if (!$user instanceof MasterUser) {
            throw new \InvalidArgumentException('User must be an instance of MasterUser');
        }
        return $user->getEmail();
    }

    public function loadUserByUsername(string $email): UserInterface
    {
        return $this->loadUserByIdentifier($email);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            // Asegurarse de que estamos usando el EntityManager de Master
            $this->tenantManager->setCurrentTenant('Master');
            $entityManager = $this->tenantManager->getEntityManager();

            if ($this->logger) {
                $this->logger->info('MasterUserProvider: Loading user', [
                    'email' => $identifier,
                    'tenant' => $this->tenantManager->getCurrentTenant(),
                    'database' => $entityManager->getConnection()->getDatabase()
                ]);
            }

            // Buscar el usuario Master activo
            $user = $entityManager->createQueryBuilder()
                ->select('mu')
                ->from(MasterUser::class, 'mu')
                ->where('mu.email = :email')
                ->andWhere('mu.status = :status')
                ->setParameter('email', $identifier)
                ->setParameter('status', Status::ACTIVE)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$user) {
                if ($this->logger) {
                    $this->logger->warning('MasterUserProvider: User not found', [
                        'email' => $identifier
                    ]);
                }
                throw new UserNotFoundException(sprintf('Master user "%s" not found or inactive.', $identifier));
            }

            if ($this->logger) {
                $this->logger->info('MasterUserProvider: User loaded successfully', [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles()
                ]);
            }

            return $user;
        } catch (UserNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('MasterUserProvider: Error loading user', [
                    'email' => $identifier,
                    'error' => $e->getMessage()
                ]);
            }
            throw new UserNotFoundException('Error loading master user: ' . $e->getMessage());
        }
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof MasterUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        try {
            $email = $user->getEmail();
            if (!$email) {
                throw new UserNotFoundException('No email associated with the user.');
            }

            return $this->loadUserByIdentifier($email);
        } catch (UserNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('MasterUserProvider: Error refreshing user', [
                    'error' => $e->getMessage()
                ]);
            }
            throw new UserNotFoundException('Error refreshing master user: ' . $e->getMessage());
        }
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof MasterUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $user->setUpdatedAt(new \DateTime());
        
        $this->tenantManager->setCurrentTenant('Master');
        $this->tenantManager->getEntityManager()->flush();
    }

    public function supportsClass(string $class): bool
    {
        return MasterUser::class === $class || is_subclass_of($class, MasterUser::class);
    }
}

