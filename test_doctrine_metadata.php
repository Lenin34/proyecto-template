<?php

require __DIR__ . '/vendor/autoload.php';

use App\Entity\App\Notification;
use App\Service\TenantManager;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$tenantManager = $container->get(TenantManager::class);
$tenantManager->setCurrentTenant('issemym');

$em = $tenantManager->getEntityManager();
$metadata = $em->getClassMetadata(Notification::class);

echo "📋 Doctrine Metadata para Notification:\n";
echo "   - Nombre de tabla: " . $metadata->getTableName() . "\n";
echo "   - Primary key: " . implode(', ', $metadata->getIdentifier()) . "\n";
echo "   - Columnas: " . implode(', ', $metadata->getColumnNames()) . "\n";

// Try to find notification 36
echo "\n🔍 Intentando encontrar notificación ID: 36\n";
$notification = $em->getRepository(Notification::class)->find(36);

if ($notification) {
    echo "✅ ENCONTRADA!\n";
    echo "   - ID: {$notification->getId()}\n";
    echo "   - Title: {$notification->getTitle()}\n";
} else {
    echo "❌ NO ENCONTRADA\n";
}

