<?php
/**
 * API de Búsqueda Global
 * Sistema OTI - Búsqueda Cmd+K / Ctrl+K
 */

error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
session_write_close();

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;

$pdo = Database::connect();
$query = $_GET['q'] ?? '';
$results = [];

if (strlen($query) >= 2) {
    try {
        $searchTerm = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';

        $ticketsStmt = $pdo->prepare("
            SELECT t.id, t.code, t.title, t.description, ts.name as status_name,
                   tp.name as priority_name, u.nombre, u.apellidos,
                   t.created_at, t.status_id, t.priority_id
            FROM oti.tickets t
            LEFT JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            LEFT JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            LEFT JOIN admin.usuarios u ON t.user_id = u.id
            WHERE (t.code LIKE ? ESCAPE '\\' OR t.title LIKE ? ESCAPE '\\' OR t.description LIKE ? ESCAPE '\\')
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $ticketsStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $tickets = $ticketsStmt->fetchAll();

        foreach ($tickets as $ticket) {
            $statusClass = match($ticket['status_id'] ?? 1) {
                1 => 'status-open',
                2 => 'status-progress',
                3 => 'status-resolved',
                4 => 'status-closed',
                default => 'status-open'
            };

            $results[] = [
                'type' => 'ticket',
                'category' => 'Tickets',
                'title' => $ticket['code'] . ' - ' . $ticket['title'],
                'meta' => ($ticket['nombre'] ?? '') . ' ' . ($ticket['apellidos'] ?? ''),
                'icon' => 'M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z',
                'iconClass' => 'icon-ticket',
                'badge' => $ticket['status_name'] ?? '',
                'badgeClass' => $statusClass,
                'url' => '/OTI/admin/tickets?view=' . $ticket['id']
            ];
        }

        $usersStmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.apellidos, u.email, r.name as role_name
            FROM admin.usuarios u
            LEFT JOIN admin.roles r ON u.role_id = r.id
            WHERE (u.nombre LIKE ? ESCAPE '\\' OR u.apellidos LIKE ? ESCAPE '\\' OR u.email LIKE ? ESCAPE '\\')
            LIMIT 5
        ");
        $usersStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $users = $usersStmt->fetchAll();

        foreach ($users as $user) {
            $results[] = [
                'type' => 'user',
                'category' => 'Usuarios',
                'title' => ($user['nombre'] ?? '') . ' ' . ($user['apellidos'] ?? ''),
                'meta' => ($user['email'] ?? '') . ' - ' . ($user['role_name'] ?? ''),
                'icon' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
                'iconClass' => 'icon-user',
                'badge' => $user['role_name'] ?? '',
                'badgeClass' => 'badge-role',
                'url' => '/OTI/admin/users?id=' . $user['id']
            ];
        }

        $equipStmt = $pdo->prepare("
            SELECT e.id, e.name, e.serial_number, et.name as type_name, e.status
            FROM oti.equipment e
            LEFT JOIN oti.equipment_types et ON e.type_id = et.id
            WHERE (e.name LIKE ? ESCAPE '\\' OR e.serial_number LIKE ? ESCAPE '\\')
            LIMIT 5
        ");
        $equipStmt->execute([$searchTerm, $searchTerm]);
        $equipos = $equipStmt->fetchAll();

        foreach ($equipos as $equipo) {
            $results[] = [
                'type' => 'equipment',
                'category' => 'Equipos',
                'title' => $equipo['name'] ?? '',
                'meta' => ($equipo['serial_number'] ?? '') . ' - ' . ($equipo['type_name'] ?? ''),
                'icon' => 'M20 18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z',
                'iconClass' => 'icon-equipment',
                'badge' => $equipo['status'] ?? '',
                'badgeClass' => 'badge-status',
                'url' => '/OTI/admin/equipment?id=' . $equipo['id']
            ];
        }

    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['error' => 'Error en la búsqueda']);
        exit;
    }
}

echo json_encode(['results' => $results, 'query' => $query, 'count' => count($results)]);