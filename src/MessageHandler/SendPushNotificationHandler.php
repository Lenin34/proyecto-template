<?php

namespace App\MessageHandler;

use App\Entity\App\Notification;
use App\Message\SendPushNotification;
use App\Service\ApplicationErrorService;
use App\Service\ExpoNotificationService;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendPushNotificationHandler
{
    private ExpoNotificationService $expoNotificationService;
    private ApplicationErrorService $applicationErrorService;
    private TenantManager $tenantManager;

    public function __construct(
        ExpoNotificationService $expoNotificationService,
        ApplicationErrorService $applicationErrorService,
        TenantManager $tenantManager
    ) {
        $this->expoNotificationService = $expoNotificationService;
        $this->applicationErrorService = $applicationErrorService;
        $this->tenantManager = $tenantManager;
    }

    public function __invoke(SendPushNotification $message)
    {
        $logFile = '/var/www/html/var/log/notification_worker.log';
        $notificationId = $message->getNotificationId();
        $tenantName = $message->getTenantName();

        file_put_contents($logFile, "\n▶️ [" . date('Y-m-d H:i:s') . "] Iniciando procesamiento asíncrono para notificación ID: {$notificationId}\n", FILE_APPEND);
        file_put_contents($logFile, "   Tenant: {$tenantName}\n", FILE_APPEND);
        file_put_contents($logFile, "   Device tokens: " . count($message->getDeviceTokens()) . "\n", FILE_APPEND);

        try {
            // Set tenancy context from message FIRST (best practice)
            file_put_contents($logFile, "🔧 [PASO 1] Estableciendo contexto de tenant: {$tenantName}\n", FILE_APPEND);
            $this->tenantManager->setCurrentTenant($tenantName);

            // Get Entity Manager BEFORE sending notification
            $em = $this->tenantManager->getEntityManager();

            // Verificar conexión
            try {
                $connParams = $em->getConnection()->getParams();
                $dbName = isset($connParams['dbname']) ? $connParams['dbname'] : 'unknown';
                file_put_contents($logFile, "   📡 Conectado a BD: {$dbName}\n", FILE_APPEND);
            } catch (\Exception $e) {
                file_put_contents($logFile, "   ⚠️ No se pudo obtener nombre de BD: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            // Verify notification exists BEFORE sending
            file_put_contents($logFile, "🔍 [PASO 2] Buscando notificación ID: {$notificationId} en BD del tenant\n", FILE_APPEND);

            // First try with SQL to verify it exists
            try {
                $sql = "SELECT id, title FROM Notification WHERE id = :id";
                $stmt = $em->getConnection()->prepare($sql);
                $result = $stmt->executeQuery(['id' => $notificationId]);
                $row = $result->fetchAssociative();

                if ($row) {
                    file_put_contents($logFile, "   ✅ SQL Query encontró: ID={$row['id']}, Title={$row['title']}\n", FILE_APPEND);
                } else {
                    file_put_contents($logFile, "   ❌ SQL Query NO encontró la notificación\n", FILE_APPEND);
                }
            } catch (\Exception $sqlEx) {
                file_put_contents($logFile, "   ⚠️ Error en SQL Query: " . $sqlEx->getMessage() . "\n", FILE_APPEND);
            }

            // Get metadata to see what table Doctrine is using
            $metadata = $em->getClassMetadata(Notification::class);
            $tableName = $metadata->getTableName();
            file_put_contents($logFile, "   📋 Doctrine está buscando en tabla: {$tableName}\n", FILE_APPEND);

            // Try with DQL instead of find()
            file_put_contents($logFile, "   🔍 Intentando con DQL...\n", FILE_APPEND);
            try {
                $dql = "SELECT n FROM App\Entity\App\Notification n WHERE n.id = :id";
                $query = $em->createQuery($dql);
                $query->setParameter('id', $notificationId);
                $notification = $query->getOneOrNullResult();

                if ($notification) {
                    file_put_contents($logFile, "   ✅ DQL encontró la notificación!\n", FILE_APPEND);
                } else {
                    file_put_contents($logFile, "   ❌ DQL NO encontró la notificación\n", FILE_APPEND);
                }
            } catch (\Exception $dqlEx) {
                file_put_contents($logFile, "   ❌ Error en DQL: " . $dqlEx->getMessage() . "\n", FILE_APPEND);
                $notification = null;
            }

            if (!$notification) {
                file_put_contents($logFile, "❌ [ERROR CRÍTICO] Doctrine NO encontró notificación ID: {$notificationId} en BD: {$dbName}\n", FILE_APPEND);
                file_put_contents($logFile, "   ℹ️ Tabla usada por Doctrine: {$tableName}\n", FILE_APPEND);

                // Don't send notification if entity doesn't exist
                return;
            }

            file_put_contents($logFile, "✅ [PASO 3] Notificación encontrada: '{$notification->getTitle()}'\n", FILE_APPEND);

            // Send the notification via Expo Service
            file_put_contents($logFile, "📤 [PASO 4] Enviando notificación push a " . count($message->getDeviceTokens()) . " dispositivos...\n", FILE_APPEND);
            $result = $this->expoNotificationService->sendExpoNotification(
                $message->getDeviceTokens(),
                $message->getTitle(),
                $message->getMessage()
            );

            if (!$result['success']) {
                file_put_contents($logFile, "❌ [PASO 5] Error API Expo: " . ($result['error'] ?? 'Desconocido') . "\n", FILE_APPEND);

                // Log application error
                $this->applicationErrorService->createError([
                    'code' => 'ASYNC-NOTIF-ERR',
                    'message' => 'Error enviando notificación asíncrona'
                ], [
                    'notification_id' => $notificationId,
                    'error' => $result['error'] ?? 'Desconocido'
                ]);
            } else {
                file_put_contents($logFile, "✅ [PASO 5] Notificación enviada exitosamente vía Expo API\n", FILE_APPEND);
                file_put_contents($logFile, "💾 [PASO 6] Actualizando sent_date en BD...\n", FILE_APPEND);

                try {
                    // Actualizar fecha
                    $notification->setSentDate(new \DateTimeImmutable());
                    $em->flush();

                    file_put_contents($logFile, "✅ [PASO 7] sent_date actualizado correctamente para ID: {$notificationId}\n", FILE_APPEND);
                    file_put_contents($logFile, "🎉 Procesamiento completado exitosamente\n", FILE_APPEND);
                } catch (\Exception $dbEx) {
                    file_put_contents($logFile, "❌ [ERROR] Al guardar sent_date en BD: " . $dbEx->getMessage() . "\n", FILE_APPEND);
                    file_put_contents($logFile, "   Stack trace: " . $dbEx->getTraceAsString() . "\n", FILE_APPEND);
                    // No relanzamos aquí para no reintentar el envío de PUSH (que ya fue exitoso)
                }
            }

        } catch (\Exception $e) {
            file_put_contents($logFile, "❌ [EXCEPCIÓN CRÍTICA] " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($logFile, "   Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);

             $this->applicationErrorService->createError([
                'code' => 'ASYNC-NOTIF-EXCEPTION',
                'message' => 'Excepción crítica en worker de notificaciones'
            ], [
                'notification_id' => $notificationId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to allow Messenger to retry if configured
            throw $e;
        }
    }
}
