<?php
namespace App\Security;

use App\Entity\App\User;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

final class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private TenantManager $tenantManager;
    private $logger;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
        $this->logger = $tenantManager->logger ?? null;
    }

    public function getUserIdentifier(UserInterface $user): string
    {
        return $user->getEmail();
    }

    public function loadUserByUsername(string $email): UserInterface
    {
        return $this->loadUserByIdentifier($email);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();
            $status = Status::ACTIVE;

            // Intento directo usando el EntityManager con DQL para cargar las relaciones necesarias
            $queryBuilder = $entityManager->createQueryBuilder();
            $queryBuilder
                ->select('u')
                ->from(User::class, 'u')
                ->where('u.email = :email')
                ->andWhere('u.status = :status')
                ->setParameter('email', $identifier)
                ->setParameter('status', $status->value)
                ->setMaxResults(1);

            $user = $queryBuilder->getQuery()->getOneOrNullResult();

            if ($user) {
                return $user;
            }

            if ($this->logger) {
                $this->logger->debug('Buscando usuario', [
                    'email' => $identifier,
                    'status' => $status->value,
                    'tenant' => $this->tenantManager->getCurrentTenant(),
                    'database' => $entityManager->getConnection()->getDatabase()
                ]);
            }

            // Primero verificar si el usuario existe con SQL nativo
            try {
                $conn = $entityManager->getConnection();
                $sql = 'SELECT id, email, status FROM user WHERE email = :email';
                $stmt = $conn->prepare($sql);

                if ($this->logger) {
                    $this->logger->debug('Ejecutando query nativa', [
                        'sql' => $sql,
                        'params' => ['email' => $identifier],
                        'tenant' => $this->tenantManager->getCurrentTenant(),
                        'database' => $entityManager->getConnection()->getDatabase()
                    ]);
                }

                $result = $stmt->executeQuery(['email' => $identifier])->fetchAssociative();

                if ($this->logger) {
                    $this->logger->debug('Resultado SQL', [
                        'result' => $result ?: 'Usuario no encontrado'
                    ]);
                }

                if (!$result) {
                    throw new UserNotFoundException(sprintf('Usuario no encontrado con email "%s"', $identifier));
                }
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error('Error en SQL Query', ['error' => $e->getMessage()]);
                }
                throw new UserNotFoundException('Error al buscar usuario: ' . $e->getMessage());
            }

            // Verificar el estado del usuario
            if ($result['status'] !== $status->value) {
                if ($this->logger) {
                    $this->logger->debug('Estado incorrecto', [
                        'esperado' => $status->value,
                        'encontrado' => $result['status']
                    ]);
                }
                throw new UserNotFoundException('Usuario inactivo');
            }

            // Cargar el usuario completo con Doctrine
            $user = $this->findOneUserBy(['email' => $identifier]);

            if (!$user) {
                throw new UserNotFoundException(sprintf('No se pudo cargar el usuario con email "%s"', $identifier));
            }

            if ($this->logger) {
                $this->logger->debug('Usuario cargado exitosamente', [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole() ? $user->getRole()->getName() : 'sin rol'
                ]);
            }

            return $user;
        } catch (UserNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Error en loadUserByIdentifier', ['error' => $e->getMessage()]);
            }
            throw new UserNotFoundException('Error al cargar usuario: ' . $e->getMessage());
        }
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($this->logger) {
            $this->logger->info('[REFRESH USER] START', [
                'user_class' => get_class($user),
                'user_id' => method_exists($user, 'getId') ? $user->getId() : 'N/A',
                'user_email' => method_exists($user, 'getEmail') ? $user->getEmail() : 'N/A',
                'current_tenant' => $this->tenantManager->getCurrentTenant(),
            ]);
        }
        
        if (!$user instanceof User) {
            if ($this->logger) {
                $this->logger->error('[REFRESH USER] FAILED - Invalid user class', [
                    'class' => get_class($user)
                ]);
            }
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        try {
            // Intentar recargar el usuario usando su email
            $email = $user->getEmail();
            
            if ($this->logger) {
                $this->logger->info('[REFRESH USER] Reloading user', [
                    'email' => $email,
                    'user_id' => $user->getId(),
                    'tenant' => $this->tenantManager->getCurrentTenant(),
                ]);
            }
            
            if (!$email) {
                if ($this->logger) {
                    $this->logger->error('[REFRESH USER] FAILED - No email');
                }
                throw new UserNotFoundException('No hay email asociado al usuario.');
            }

            $refreshedUser = $this->loadUserByIdentifier($email);
            
            if ($this->logger) {
                $this->logger->info('[REFRESH USER] SUCCESS', [
                    'refreshed_user_id' => $refreshedUser->getId(),
                    'refreshed_user_email' => $refreshedUser->getEmail(),
                    'roles' => $refreshedUser->getRoles(),
                ]);
            }
            
            return $refreshedUser;
        } catch (UserNotFoundException $e) {
            if ($this->logger) {
                $this->logger->error('[REFRESH USER] FAILED - User not found', [
                    'error' => $e->getMessage(),
                    'email' => $email ?? 'N/A',
                ]);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('[REFRESH USER] FAILED - Exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            throw new UserNotFoundException('Error al recargar usuario: ' . $e->getMessage());
        }
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->tenantManager->getEntityManager()->flush();
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    private function findOneUserBy(array $options): ?User
    {
        $entityManager = $this->tenantManager->getEntityManager();

        try {
            if ($this->logger) {
                $this->logger->debug('findOneUserBy', ['options' => $options]);
            }

            $queryBuilder = $entityManager->createQueryBuilder()
                ->select('u', 'r')
                ->from(User::class, 'u')
                ->leftJoin('u.role', 'r');

            if (isset($options['email'])) {
                $queryBuilder->andWhere('u.email = :email')
                    ->setParameter('email', $options['email']);
            }

            if (isset($options['id'])) {
                $queryBuilder->andWhere('u.id = :id')
                    ->setParameter('id', $options['id']);
            }

            if (isset($options['status'])) {
                $queryBuilder->andWhere('u.status = :status')
                    ->setParameter('status', $options['status']);
            }

            $queryBuilder->setMaxResults(1);

            $query = $queryBuilder->getQuery();
            if ($this->logger) {
                $this->logger->debug('Query DQL siendo ejecutada', [
                    'dql' => $query->getDQL(),
                    'options' => $options
                ]);
            }

            try {
                $user = $query->getOneOrNullResult();

                if ($this->logger) {
                    $this->logger->debug('Usuario encontrado', [
                        'found' => $user ? true : false,
                        'id' => $user ? $user->getId() : null,
                        'email' => $user ? $user->getEmail() : null
                    ]);
                }

                return $user;
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error('Query execution failed', ['error' => $e->getMessage()]);
                }
                return null;
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('findOneUserBy error', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
}
