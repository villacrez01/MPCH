<?php
define('BASE_URL','http://localhost/OTI/');
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;
$pdo = Database::connect();
$userId = 8;

// Contar notificaciones primero
$stmt = $pdo->prepare("SELECT COUNT(*) FROM oti.notifications WHERE user_id = :uid AND is_read = false");
$stmt->execute(['uid' => $userId]);
echo "No leídos: " . $stmt->fetchColumn() . "\n";

// Traer limitadas
$stmt = $pdo->prepare("SELECT n.*, t.code as ticket_code, t.title as ticket_title FROM oti.notifications n LEFT JOIN oti.tickets t ON n.ticket_id = t.id WHERE n.user_id = :user_id ORDER BY n.created_at DESC LIMIT 20");
$stmt->execute(['user_id' => $userId]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total obtenido: " . count($notifs) . "\n";
echo json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
