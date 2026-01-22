#!/usr/bin/env php
<?php

// Script para probar la creación de notificaciones y verificar que se guardan correctamente

require __DIR__ . '/vendor/autoload.php';

use App\Entity\App\Notification;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

// Bootstrap Symfony kernel
$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$tenantManager = $container->get(TenantManager::class);

// Set tenant
$tenantName = 'issemym';
echo "🔧 Estableciendo tenant: {$tenantName}\n";
$tenantManager->setCurrentTenant($tenantName);

// Get EntityManager
$em = $tenantManager->getEntityManager();

// Verify connection
$connection = $em->getConnection();
$dbName = $connection->getDatabase();
echo "📡 Conectado a BD: {$dbName}\n";

// Create test notification
$notification = new Notification();
$notification->setTitle('TEST: Notificación de Prueba');
$notification->setMessage('Esta es una notificación de prueba para verificar el sistema');
$notification->setCreatedAt(new \DateTimeImmutable());
$notification->setUpdatedAt(new \DateTimeImmutable());
$notification->setStatus(Status::ACTIVE);

echo "💾 Guardando notificación...\n";
$em->persist($notification);
$em->flush();

$notificationId = $notification->getId();
echo "✅ Notificación creada con ID: {$notificationId}\n";

// Verify it was saved
$em->clear();
$savedNotification = $em->getRepository(Notification::class)->find($notificationId);

if ($savedNotification) {
    echo "✅ Verificación exitosa: Notificación encontrada en BD\n";
    echo "   - ID: {$savedNotification->getId()}\n";
    echo "   - Título: {$savedNotification->getTitle()}\n";
    echo "   - Mensaje: {$savedNotification->getMessage()}\n";
    echo "   - sent_date: " . ($savedNotification->getSentDate() ? $savedNotification->getSentDate()->format('Y-m-d H:i:s') : 'NULL') . "\n";
} else {
    echo "❌ ERROR: Notificación NO encontrada en BD después de guardar\n";
    exit(1);
}

echo "\n🎯 Prueba completada exitosamente\n";

