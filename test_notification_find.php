<?php

require __DIR__ . '/vendor/autoload.php';

use App\Entity\App\Notification;
use App\Service\TenantManager;

// Bootstrap Symfony kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Get TenantManager
$tenantManager = $container->get(TenantManager::class);

echo "=== TEST: Notification Find Issue ===\n\n";

// Set tenant context
$tenantName = 'issemym';
echo "1. Setting tenant context: {$tenantName}\n";
$tenantManager->setCurrentTenant($tenantName);

// Get EntityManager
$em = $tenantManager->getEntityManager();
echo "2. Got EntityManager\n";

// Check connection
$conn = $em->getConnection();
$dbName = $conn->getDatabase();
echo "3. Connected to database: {$dbName}\n";

// Close and clear (like in the handler)
if ($conn->isConnected()) {
    $conn->close();
    echo "4. Closed connection\n";
}
$em->clear();
echo "5. Cleared EntityManager\n";

// Try to find notification
$notificationId = 28;
echo "\n6. Attempting to find Notification ID: {$notificationId}\n";

$notification = $em->getRepository(Notification::class)->find($notificationId);

if ($notification) {
    echo "✅ SUCCESS: Notification found!\n";
    echo "   ID: " . $notification->getId() . "\n";
    echo "   Title: " . $notification->getTitle() . "\n";
} else {
    echo "❌ FAILED: Notification NOT found\n";
    
    // Try direct SQL query
    echo "\n7. Trying direct SQL query...\n";
    $sql = "SELECT * FROM Notification WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->executeQuery([$notificationId]);
    $row = $result->fetchAssociative();
    
    if ($row) {
        echo "✅ SQL query found the record:\n";
        print_r($row);
    } else {
        echo "❌ SQL query also failed\n";
    }
}

echo "\n=== END TEST ===\n";
