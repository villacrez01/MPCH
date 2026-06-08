<?php
define('BASE_URL','http://localhost/OTI/');
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;
$pdo = Database::connect();

// Simula get-permissions action
$userId = 8;
$stmt = $pdo->prepare("SELECT permissions FROM oti.user_profiles WHERE user_id = :id");
$stmt->execute(['id' => $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$perms = $row && $row['permissions'] ? json_decode($row['permissions'], true) : [];
echo json_encode(['permissions' => $perms], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Simula update-permissions action
$newPerms = ['tickets:view', 'tickets:edit', 'users:view'];
$permJson = json_encode($newPerms);
$stmt = $pdo->prepare("INSERT INTO oti.user_profiles (user_id, permissions) VALUES (:user_id, :permissions) ON CONFLICT (user_id) DO UPDATE SET permissions = EXCLUDED.permissions");
$stmt->execute(['user_id' => $userId, 'permissions' => $permJson]);
echo "UPDATE OK\n";

// Verificar después del update
$stmt = $pdo->prepare("SELECT permissions FROM oti.user_profiles WHERE user_id = :id");
$stmt->execute(['id' => $userId]);
$row2 = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode(['permissions' => json_decode($row2['permissions'], true)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
