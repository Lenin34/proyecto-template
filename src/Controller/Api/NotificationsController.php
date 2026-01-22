<?php

namespace App\Controller\Api;

use App\Entity\App\Notification;
use App\Enum\Status;
use App\Service\TenantManager;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class NotificationsController extends AbstractController
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Obtiene el número de notificaciones no leídas de un usuario
     */
    #[Route('/users/{userId}/notifications/unread-count', name: 'api_notification_unread_count', methods: ['GET'])]
    public function getUnreadCount(string $dominio, int $userId): JsonResponse
    {
        try {
            // CRÍTICO: Configurar el tenant ANTES de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $em = $this->tenantManager->getEntityManager();
            $connection = $em->getConnection();

            // Auto-crear tabla si no existe (solución proactiva para nuevos tenants)
            $this->ensureUserNotificationReadTableExists($connection);

            // Obtener todas las notificaciones activas
            $qb = $em->createQueryBuilder()
                ->select('COUNT(DISTINCT n.id)')
                ->from('App\Entity\App\Notification', 'n')
                ->leftJoin('n.regions', 'r')
                ->leftJoin('n.companies', 'c')
                ->leftJoin('c.users', 'u')
                ->leftJoin('r.users', 'ru')
                ->where('n.status = :status')
                ->andWhere('n.sent_date IS NOT NULL')
                ->setParameter('status', Status::ACTIVE);

            // El usuario debe estar en las empresas o regiones de la notificación
            // O la notificación debe ser global (sin regiones ni empresas)
            $qb->andWhere(
                $qb->expr()->orX(
                    'u.id = :userId',
                    'ru.id = :userId',
                    $qb->expr()->andX(
                        $qb->expr()->isNull('r.id'),
                        $qb->expr()->isNull('c.id')
                    )
                )
            )->setParameter('userId', $userId);

            // Excluir notificaciones ya leídas por este usuario
            $sqlRead = "SELECT notification_id FROM user_notification_read WHERE user_id = :userId";
            $readNotificationIds = $connection->fetchFirstColumn($sqlRead, ['userId' => $userId]);

            // Si hay notificaciones leídas, excluirlas de la consulta principal
            if (!empty($readNotificationIds)) {
                $qb->andWhere('n.id NOT IN (:readIds)')
                   ->setParameter('readIds', $readNotificationIds);
            }

            $count = (int) $qb->getQuery()->getSingleScalarResult();

            return new JsonResponse([
                'success' => true,
                'unread_count' => $count
            ]);

        } catch (\Exception $e) {
            error_log(sprintf(
                '[NotificationsController::getUnreadCount] Error - Dominio: %s, UserId: %d, Error: %s',
                $dominio,
                $userId,
                $e->getMessage()
            ));
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al obtener el contador de notificaciones: ' . $e->getMessage(),
                'unread_count' => 0
            ], 500);
        }
    }

    /**
     * Marca notificaciones específicas como leídas
     */
    #[Route('/users/{userId}/notifications/mark-read', name: 'api_notification_mark_read', methods: ['POST'])]
    public function markAsRead(string $dominio, int $userId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['notification_ids']) || !is_array($data['notification_ids'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'notification_ids es requerido y debe ser un array'
                ], 400);
            }

            $notificationIds = $data['notification_ids'];
            // CRÍTICO: Configurar el tenant ANTES de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $em = $this->tenantManager->getEntityManager();
            $connection = $em->getConnection();

            // Asegurar que la tabla existe
            $this->ensureUserNotificationReadTableExists($connection);

            $markedCount = 0;
            foreach ($notificationIds as $notificationId) {
                try {
                    $connection->insert('user_notification_read', [
                        'user_id' => $userId,
                        'notification_id' => $notificationId,
                        'read_at' => (new \DateTime())->format('Y-m-d H:i:s')
                    ]);
                    $markedCount++;
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }

            // Obtener el nuevo contador
            $unreadCountResponse = $this->getUnreadCount($dominio, $userId);
            $unreadCountData = json_decode($unreadCountResponse->getContent(), true);
            $unreadCount = $unreadCountData['unread_count'] ?? 0;

            return new JsonResponse([
                'success' => true,
                'marked_count' => $markedCount,
                'unread_count' => $unreadCount
            ]);

        } catch (\Exception $e) {
            error_log('Error marking notifications as read: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al marcar notificaciones como leídas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marca TODAS las notificaciones del usuario como leídas
     */
    #[Route('/users/{userId}/notifications/mark-all-read', name: 'api_notification_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(string $dominio, int $userId): JsonResponse
    {
        try {
            // CRÍTICO: Configurar el tenant ANTES de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $em = $this->tenantManager->getEntityManager();
            $connection = $em->getConnection();

            // Asegurar que la tabla existe
            $this->ensureUserNotificationReadTableExists($connection);

            $qb = $em->createQueryBuilder()
                ->select('DISTINCT n.id')
                ->from('App\Entity\App\Notification', 'n')
                ->leftJoin('n.regions', 'r')
                ->leftJoin('n.companies', 'c')
                ->leftJoin('c.users', 'u')
                ->leftJoin('r.users', 'ru')
                ->where('n.status = :status')
                ->andWhere('n.sent_date IS NOT NULL')
                ->setParameter('status', Status::ACTIVE);

            $qb->andWhere(
                $qb->expr()->orX(
                    'u.id = :userId',
                    'ru.id = :userId',
                    $qb->expr()->andX(
                        $qb->expr()->isNull('r.id'),
                        $qb->expr()->isNull('c.id')
                    )
                )
            )->setParameter('userId', $userId);

            // Obtener IDs de notificaciones leídas
            $sqlRead = "SELECT notification_id FROM user_notification_read WHERE user_id = :userId";
            $readNotificationIds = $connection->fetchFirstColumn($sqlRead, ['userId' => $userId]);

            if (!empty($readNotificationIds)) {
                $qb->andWhere('n.id NOT IN (:readIds)')
                   ->setParameter('readIds', $readNotificationIds);
            }

            $notificationIds = array_column($qb->getQuery()->getArrayResult(), 'id');

            $markedCount = 0;
            foreach ($notificationIds as $notificationId) {
                try {
                    $connection->insert('user_notification_read', [
                        'user_id' => $userId,
                        'notification_id' => $notificationId,
                        'read_at' => (new \DateTime())->format('Y-m-d H:i:s')
                    ]);
                    $markedCount++;
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }

            return new JsonResponse([
                'success' => true,
                'marked_count' => $markedCount,
                'unread_count' => 0
            ]);

        } catch (\Exception $e) {
            error_log('Error marking all notifications as read: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al marcar todas las notificaciones como leídas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene la lista de notificaciones de un usuario
     */
    /**
     * Obtiene la lista de notificaciones de un usuario
     */
    #[Route('/users/{userId}/notifications', name: 'api_user_notifications', methods: ['GET'])]
    public function getNotifications(string $dominio, int $userId, Request $request): JsonResponse
    {
        try {
            // CRÍTICO: Configurar el tenant ANTES de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $em = $this->tenantManager->getEntityManager();
            $connection = $em->getConnection();
            
            // Asegurar que la tabla existe
            $this->ensureUserNotificationReadTableExists($connection);
            
            $page = max(1, $request->query->getInt('page', 1));
            $limit = max(1, min(50, $request->query->getInt('pageSize', 20)));
            $offset = ($page - 1) * $limit;
            $filterRead = $request->query->get('is_read'); // 'true', 'false', '1', '0' o null

            // Obtener IDs de notificaciones leídas por el usuario primero
            $sqlRead = "SELECT notification_id FROM user_notification_read WHERE user_id = :userId";
            $readIds = $connection->fetchFirstColumn($sqlRead, ['userId' => $userId]);

            // Query principal
            $qb = $em->createQueryBuilder()
                ->select('DISTINCT n')
                ->from('App\Entity\App\Notification', 'n')
                ->leftJoin('n.regions', 'r')
                ->leftJoin('n.companies', 'c')
                ->leftJoin('c.users', 'u')
                ->leftJoin('r.users', 'ru')
                ->where('n.status = :status')
                ->andWhere('n.sent_date IS NOT NULL')
                ->setParameter('status', Status::ACTIVE)
                ->setParameter('userId', $userId);

            // Lógica de targeting
            $qb->andWhere(
                $qb->expr()->orX(
                    'u.id = :userId',
                    'ru.id = :userId',
                    $qb->expr()->andX(
                        $qb->expr()->isNull('r.id'),
                        $qb->expr()->isNull('c.id')
                    )
                )
            );

            // Filtro por estado de lectura
            if ($filterRead !== null && $filterRead !== '') {
                $isRead = ($filterRead === 'true' || $filterRead === '1');
                
                if ($isRead) {
                    // Solo leídas
                    if (!empty($readIds)) {
                        $qb->andWhere('n.id IN (:readIds)')
                           ->setParameter('readIds', $readIds);
                    } else {
                        // No ha leído ninguna, devolver vacío
                        $qb->andWhere('1 = 0');
                    }
                } else {
                    // Solo NO leídas
                    if (!empty($readIds)) {
                        $qb->andWhere('n.id NOT IN (:readIds)')
                           ->setParameter('readIds', $readIds);
                    }
                }
            }

            $qb->orderBy('n.sent_date', 'DESC');
            
            // Paginación
            $qb->setFirstResult($offset);
            $qb->setMaxResults($limit);
            
            $notifications = $qb->getQuery()->getResult();
            
            $data = [];
            foreach ($notifications as $notification) {
                // Determinar tipo e imagen basándose en coincidencia de título
                $type = 'general';
                $eventId = null;
                $benefitId = null;
                $image = null; // Inicializar como null

                $notificationTitle = $notification->getTitle();
                
                // Los títulos de notificaciones tienen el formato:
                // "Nuevo Evento: {titulo_real}" o "Nuevo Beneficio: {titulo_real}"
                // Extraer el título real para buscar en la base de datos
                
                $realTitle = null;
                $probableType = 'general';
                
                if (preg_match('/^Nuevo Evento:\s*(.+)$/i', $notificationTitle, $matches)) {
                    $realTitle = trim($matches[1]);
                    $probableType = 'event';
                } elseif (preg_match('/^Nuevo Beneficio:\s*(.+)$/i', $notificationTitle, $matches)) {
                    $realTitle = trim($matches[1]);
                    $probableType = 'benefit';
                } else {
                    // Si no coincide con ningún patrón, usar el título completo
                    $realTitle = $notificationTitle;
                }
                
                // Buscar según el tipo probable primero para optimizar
                if ($probableType === 'event' || $probableType === 'general') {
                    $event = $em->getRepository('App\Entity\App\Event')->findOneBy(['title' => $realTitle], ['created_at' => 'DESC']);
                    
                    if ($event) {
                        $type = 'event';
                        $eventId = $event->getId();
                        $image = $event->getImage();
                    }
                }
                
                // Si no encontró evento y el tipo probable es beneficio, o si no sabemos el tipo
                if (!$eventId && ($probableType === 'benefit' || $probableType === 'general')) {
                    $benefit = $em->getRepository('App\Entity\App\Benefit')->findOneBy(['title' => $realTitle], ['created_at' => 'DESC']);
                    if ($benefit) {
                        $type = 'benefit';
                        $benefitId = $benefit->getId();
                        $image = $benefit->getImage();
                    }
                }

                // Construir URL completa si existe imagen
                // NOTA: La base de datos ya guarda el path con el tipo incluido, ej: "benefit/file_123.jpg"
                // Por lo que solo necesitamos añadir '/uploads/' al principio
                $imageUrl = null;
                if ($image) {
                    // El campo $image ya contiene el path relativo completo (ej: "benefit/file_123.jpg" o "event/file_456.jpg")
                    $imageUrl = $request->getSchemeAndHttpHost() . '/uploads/' . $image;
                }

                $data[] = [
                    'id' => $notification->getId(),
                    'title' => $notification->getTitle(),
                    'message' => $notification->getMessage(),
                    'type' => $type,
                    'event_id' => $eventId,
                    'benefit_id' => $benefitId,
                    'image' => $imageUrl, // Nuevo campo
                    'created_at' => $notification->getCreatedAt()->format('c'),
                    'sent_date' => $notification->getSentDate() ? $notification->getSentDate()->format('c') : null,
                    'is_read' => in_array($notification->getId(), $readIds)
                ];
            }
            
            // Total para paginación (clonando antes de paginar pero manteniendo filtros)
            $countQb = clone $qb;
            $countQb->setFirstResult(null)->setMaxResults(null)->select('COUNT(DISTINCT n.id)');
            $total = (int) $countQb->getQuery()->getSingleScalarResult();
            
            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'pageSize' => $limit,
                'totalPages' => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            error_log('Error getting user notifications: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al obtener notificaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de lectura de una notificación
     */
    #[Route('/notifications/{id}/read-statistics', name: 'api_notification_read_statistics', methods: ['GET'])]
    public function getReadStatistics(string $dominio, int $id, Request $request): JsonResponse
    {
        try {
            // CRÍTICO: Configurar el tenant ANTES de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            $em = $this->tenantManager->getEntityManager();
            $connection = $em->getConnection();
            
            $page = max(1, $request->query->getInt('page', 1));
            $limit = max(1, min(100, $request->query->getInt('pageSize', 10)));
            $offset = ($page - 1) * $limit;

            // Contar total
            $countSql = "
                SELECT COUNT(*) 
                FROM user_notification_read unr
                WHERE unr.notification_id = :notificationId
            ";
            $total = (int) $connection->fetchOne($countSql, ['notificationId' => $id]);

            // Obtener datos
            // Asumimos tablas 'user' o 'User' y 'company' o 'Company' con case sensitivity dependiendo de config
            // En SQL puro, si MySQL está en Linux, es case sensitive por defecto para tablas.
            // Dado que las entidades son 'App\Entity\App\User', Doctrine suele usar la tabla como está definida.
            // Voy a usar un QueryBuilder y DQL para evitar problemas de nombres de tablas reales.
            
            // DQL es más seguro para nombres de tablas
            // No hay entidad UserNotificationRead mapeada, así que no puedo usar DQL fácilmente para ella si no es entidad.
            // Pero puedo usar SQL nativo con cuidado.
            // Revisando 'migration_user_notification_read.sql', la tabla es `user_notification_read`.
            // La tabla de usuarios es `User` (visto en pasos anteriores).
            // La tabla de empresas es `Company` (probablemente).
            
            $sql = "
                SELECT 
                    unr.user_id,
                    unr.read_at,
                    u.name as user_name,
                    u.last_name as user_last_name,
                    u.email as user_email, 
                    c.name as company_name
                FROM user_notification_read unr
                LEFT JOIN User u ON unr.user_id = u.id
                LEFT JOIN Company c ON u.company_id = c.id
                WHERE unr.notification_id = :notificationId
                ORDER BY unr.read_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $connection->prepare($sql);
            $stmt->bindValue('notificationId', $id);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
            $results = $stmt->executeQuery()->fetchAllAssociative();

            $data = array_map(function($row) {
                return [
                    'user_id' => $row['user_id'],
                    'user_name' => trim(($row['user_name'] ?? '') . ' ' . ($row['user_last_name'] ?? '')),
                    'user_email' => $row['user_email'],
                    'company_name' => $row['company_name'],
                    'read_at' => $row['read_at'],
                ];
            }, $results);

            return new JsonResponse([
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'pageSize' => $limit,
                'totalPages' => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            error_log('Error getting notification statistics: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al obtener estadísticas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asegura que la tabla user_notification_read exista en el tenant actual
     */
    private function ensureUserNotificationReadTableExists(Connection $connection): void
    {
        try {
            // Verificar si la tabla existe
            $schemaManager = $connection->createSchemaManager();
            if (!$schemaManager->tablesExist(['user_notification_read'])) {
                error_log("[NotificationsController] Creating table user_notification_read...");
                
                $sql = "
                    CREATE TABLE IF NOT EXISTS `user_notification_read` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NOT NULL,
                        `notification_id` INT NOT NULL,
                        `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_user_id` (`user_id`),
                        INDEX `idx_notification_id` (`notification_id`),
                        INDEX `idx_user_notification` (`user_id`, `notification_id`),
                        UNIQUE KEY `unique_user_notification` (`user_id`, `notification_id`),
                        CONSTRAINT `fk_user_notification_read_user_v2` 
                            FOREIGN KEY (`user_id`) 
                            REFERENCES `User` (`id`) 
                            ON DELETE CASCADE,
                        CONSTRAINT `fk_user_notification_read_notification_v2` 
                            FOREIGN KEY (`notification_id`) 
                            REFERENCES `Notification` (`id`) 
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                
                $connection->executeStatement($sql);
                error_log("[NotificationsController] Table user_notification_read created successfully.");
            }
        } catch (\Exception $e) {
            error_log("[NotificationsController] Error ensuring table exists: " . $e->getMessage());
            // No lanzamos excepción para no bloquear el flujo si ya existe o hay otro problema menor
        }
    }
}
