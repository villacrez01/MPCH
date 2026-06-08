<?php
define('BASE_URL','http://localhost/OTI/');
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;
$pdo = Database::connect();
$sql = "SELECT u.id, u.nombre, u.apellidos, u.email, u.activo, u.es_admin, u.ultimo_acceso,
               up.dni, up.phone, up.location_id, up.position_id, up.permissions,
               l.name as location_name, l.type as location_type,
               p.name as position_name,
               r.nombre as role_name,
               COALESCE(e.equipos_count, 0) as equipos_count
        FROM admin.usuarios u
        LEFT JOIN oti.user_profiles up ON u.id = up.user_id
        LEFT JOIN oti.locations l ON up.location_id = l.id
        LEFT JOIN oti.positions p ON up.position_id = p.id
        LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = :slug LIMIT 1)
        LEFT JOIN admin.roles r ON ur.rol_id = r.id
        LEFT JOIN (SELECT assigned_user_id, COUNT(*) as equipos_count FROM oti.equipment WHERE is_deleted = false AND assigned_user_id IS NOT NULL GROUP BY assigned_user_id) e ON u.id = e.assigned_user_id
        WHERE 1=1
        ORDER BY u.nombre ASC LIMIT 1 OFFSET 0";
$stmt = $pdo->prepare($sql);
$stmt->execute(['slug' => 'oti']);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
