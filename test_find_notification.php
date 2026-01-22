<?php

// Test script to verify if Doctrine can find notifications

require __DIR__ . '/vendor/autoload.php';

use App\Entity\App\Notification;
use App\Service\TenantManager;
use Symfony\Component\Dotenv\Dotenv;

// Load environment
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

// Bootstrap kernel
$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Get TenantManager
$tenantManager = $container->get(TenantManager::class);

// Set tenant
$tenantName = 'issemym';
echo "🔧 Setting tenant: {$tenantName}\n";
$tenantManager->setCurrentTenant($tenantName);

// Get EntityManager
$em = $tenantManager->getEntityManager();

// Verify connection
$connection = $em->getConnection();
$dbName = $connection->getDatabase();
echo "📡 Connected to DB: {$dbName}\n";

// Try to find notification ID 34
$notificationId = 34;
echo "\n🔍 Searching for notification ID: {$notificationId}\n";

$notification = $em->getRepository(Notification::class)->find($notificationId);

if ($notification) {
    echo "✅ Notification FOUND!\n";
    echo "   - ID: {$notification->getId()}\n";
    echo "   - Title: {$notification->getTitle()}\n";
    echo "   - Message: {$notification->getMessage()}\n";
    echo "   - Created: {$notification->getCreatedAt()->format('Y-m-d H:i:s')}\n";
    echo "   - Sent Date: " . ($notification->getSentDate() ? $notification->getSentDate()->format('Y-m-d H:i:s') : 'NULL') . "\n";
} else {
    echo "❌ Notification NOT FOUND!\n";
    
    // Try direct SQL query
    echo "\n🔍 Trying direct SQL query...\n";
    $sql = "SELECT * FROM Notification WHERE id = :id";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery(['id' => $notificationId]);
    $row = $result->fetchAssociative();
    
    if ($row) {
        echo "✅ Found via SQL:\n";
        print_r($row);
        
        echo "\n🔍 Checking Doctrine metadata...\n";
        $metadata = $em->getClassMetadata(Notification::class);
        echo "   - Table name: {$metadata->getTableName()}\n";
        echo "   - Primary key: " . implode(', ', $metadata->getIdentifier()) . "\n";
    } else {
        echo "❌ Not found via SQL either!\n";
    }
}

