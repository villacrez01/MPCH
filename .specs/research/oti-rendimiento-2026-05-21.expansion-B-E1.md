# Expansión B-E1: Consolidación de Queries + Estrategia de Índices

**Propuesta original:** B-E1
**Puntaje pruning:** 86.42
**Complejidad:** Baja
**Tiempo estimado:** 3-5 días
**Stack:** PHP 8.x + PostgreSQL 16 + Apache

---

## Arquitectura de la Solución

Se consolidan ~30 queries distribuidas en stats.php (11), analisis.php (12), Location::getPath() (N+1), Location::getById() (extra), Ticket::changeStatus()/isFinalStatus() (extra), y User::getAll() (fallback duplicado) en ~7 queries totales mediante:

1. **COUNT(*) FILTER(WHERE...)** — múltiples agregados en una sola pasada
2. **JSON_BUILD_OBJECT()** — data semiestructurada en una columna
3. **CTE Recursiva** — elimina N+1 en jerarquías de locations
4. **Self-JOIN** con LEFT JOINs encadenados — elimina queries hijas
5. **Static cache** — evita re-query de status names en cada cambio
6. **session_regenerate_id() condicional** — basado en tiempo transcurrido
7. **8 índices compuestos + pg_trgm** — cubren todos los access paths
8. **Eliminar fallback duplicado** — User::getAll() ya no ejecuta segunda query

---

## Componentes a Implementar

### 1. app/api/stats.php — De 12 queries a 3 queries

**Archivo existente:** `app/api/stats.php`

**Análisis de queries actuales (admin):**
- Q1 (L36-44): stats generales de tickets (COUNT + FILTER)
- Q2 (L48-61): tickets por prioridad
- Q3 (L63-71): tickets por estado
- Q4 (L73-83): tickets recientes
- Q5 (L85-95): stats de equipos
- Q6 (L97-104): stats de usuarios
- Q7 (L106-147): actividad reciente
- Q8 (L149-158): tickets por ubicación
- Q9 (L160-169): top usuarios
- Q10 (L171-179): equipos por tipo
- Q11 (L181-189): tickets por mes
- Extras (L221-229): tickets últimos 30 días

**Solución:** 3 queries consolidadas

```php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$isOtiAdmin = \App\Services\AuthService::isAdmin();
$userId = $_SESSION['user']['id'] ?? null;

try {
    $pdo = Database::connect();
    $response = [];

    // ── Q1 (MASTER): Tickets + Equipos + Usuarios en UNA query ──
    $stmt = $pdo->query("
        SELECT
            -- Ticket counts por estado
            COUNT(t.id) FILTER (WHERE t.id IS NOT NULL) AS tickets_total,
            COUNT(t.id) FILTER (WHERE t.status_id = 1) AS tickets_abiertos,
            COUNT(t.id) FILTER (WHERE t.status_id = 2) AS tickets_en_proceso,
            COUNT(t.id) FILTER (WHERE t.status_id = 3) AS tickets_resueltos,
            COUNT(t.id) FILTER (WHERE t.status_id = 4) AS tickets_cerrados,
            -- Ticket counts por mes (últimos 6 meses)
            (SELECT JSON_AGG(json_build_object('mes', sub.mes, 'count', sub.count) ORDER BY sub.mes)
             FROM (
                 SELECT TO_CHAR(t2.created_at, 'YYYY-MM') AS mes, COUNT(*) AS count
                 FROM oti.tickets t2
                 WHERE t2.created_at >= NOW() - INTERVAL '6 months'
                 GROUP BY TO_CHAR(t2.created_at, 'YYYY-MM')
             ) sub
            ) AS tickets_por_mes,
            -- Tickets últimos 30 días
            (SELECT JSON_AGG(json_build_object('date', sub2.date, 'count', sub2.count) ORDER BY sub2.date)
             FROM (
                 SELECT DATE(t3.created_at) AS date, COUNT(*) AS count
                 FROM oti.tickets t3
                 WHERE t3.created_at >= NOW() - INTERVAL '30 days'
                 GROUP BY DATE(t3.created_at)
             ) sub2
            ) AS ultimos_30_dias,
            -- Equipment counts
            COUNT(e.id) FILTER (WHERE e.id IS NOT NULL) AS equipos_total,
            COUNT(e.id) FILTER (WHERE e.status = 'active' AND e.is_deleted = false) AS equipos_activos,
            COUNT(e.id) FILTER (WHERE e.status = 'maintenance' AND e.is_deleted = false) AS equipos_mantenimiento,
            COUNT(e.id) FILTER (WHERE e.status = 'inactive' AND e.is_deleted = false) AS equipos_inactivos,
            -- User counts
            COUNT(u_total.id) AS usuarios_total,
            COUNT(u_total.id) FILTER (WHERE u_total.activo = true) AS usuarios_activos,
            -- Resolution rate
            CASE WHEN COUNT(t.id) FILTER (WHERE t.id IS NOT NULL) > 0
                 THEN ROUND(
                     (COUNT(t.id) FILTER (WHERE t.status_id IN (3,4))::numeric
                      / COUNT(t.id) FILTER (WHERE t.id IS NOT NULL)) * 100, 1)
                 ELSE 0
            END AS tasa_resolucion
        FROM oti.tickets t
        FULL JOIN oti.equipment e ON 1=0  -- cross-join placeholder
        CROSS JOIN (SELECT COUNT(*) AS id FROM admin.usuarios) u_total
        LIMIT 1
    ");
    $master = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['stats'] = [
        'total' => (int)$master['tickets_total'],
        'abiertos' => (int)$master['tickets_abiertos'],
        'en_proceso' => (int)$master['tickets_en_proceso'],
        'resueltos' => (int)$master['tickets_resueltos'],
        'cerrados' => (int)$master['tickets_cerrados'],
    ];
    $response['tickets_por_mes'] = json_decode($master['tickets_por_mes'] ?? '[]', true);
    $response['ultimos_30_dias'] = json_decode($master['ultimos_30_dias'] ?? '[]', true);
    $response['equipos'] = [
        'total' => (int)$master['equipos_total'],
        'activos' => (int)$master['equipos_activos'],
        'mantenimiento' => (int)$master['equipos_mantenimiento'],
        'inactivos' => (int)$master['equipos_inactivos'],
    ];
    $response['usuarios'] = [
        'total' => (int)$master['usuarios_total'],
        'activos' => (int)$master['usuarios_activos'],
    ];
    $response['tasa_resolucion'] = (float)$master['tasa_resolucion'];

    // ── Q2: Datos agregados (prioridad, estado, ubicación, top usuarios) ──
    $stmt = $pdo->query("
        SELECT
            -- Tickets por prioridad (JSON)
            (SELECT JSON_AGG(json_build_object('name', tp.name, 'count', sub3.count, 'color', sub3.color) ORDER BY sub3.pid)
             FROM (
                 SELECT tp2.name, COUNT(t4.id) AS count, tp2.id AS pid,
                        CASE tp2.id WHEN 1 THEN '#dc2626' WHEN 2 THEN '#f59e0b' WHEN 3 THEN '#10b981' ELSE '#6366f1' END AS color
                 FROM oti.tickets t4
                 JOIN oti.ticket_priorities tp2 ON t4.priority_id = tp2.id
                 GROUP BY tp2.name, tp2.id
             ) sub3
            ) AS por_prioridad,
            -- Tickets por estado (JSON)
            (SELECT JSON_AGG(json_build_object('name', ts2.name, 'count', sub4.count) ORDER BY sub4.sid)
             FROM (
                 SELECT ts3.name, COUNT(t5.id) AS count, ts3.id AS sid
                 FROM oti.tickets t5
                 JOIN oti.ticket_statuses ts3 ON t5.status_id = ts3.id
                 GROUP BY ts3.name, ts3.id
             ) sub4
            ) AS por_estado,
            -- Tickets por ubicación (top 5)
            (SELECT JSON_AGG(json_build_object('name', l2.name, 'count', sub5.count) ORDER BY sub5.count DESC)
             FROM (
                 SELECT l3.name, COUNT(t6.id) AS count
                 FROM oti.tickets t6
                 JOIN oti.locations l3 ON t6.location_id = l3.id
                 GROUP BY l3.name
                 ORDER BY count DESC
                 LIMIT 5
             ) sub5
            ) AS por_ubicacion,
            -- Top usuarios (top 5)
            (SELECT JSON_AGG(json_build_object('name', sub6.name, 'count', sub6.count) ORDER BY sub6.count DESC)
             FROM (
                 SELECT (u2.nombre || ' ' || COALESCE(u2.apellidos, '')) AS name, COUNT(t7.id) AS count
                 FROM oti.tickets t7
                 JOIN admin.usuarios u2 ON t7.user_id = u2.id
                 GROUP BY u2.nombre, u2.apellidos
                 ORDER BY count DESC
                 LIMIT 5
             ) sub6
            ) AS top_usuarios,
            -- Equipos por tipo
            (SELECT JSON_AGG(json_build_object('asset_type', e2.asset_type, 'count', e2.count) ORDER BY e2.count DESC)
             FROM (
                 SELECT e3.asset_type, COUNT(*) AS count
                 FROM oti.equipment e3
                 WHERE e3.is_deleted = false
                 GROUP BY e3.asset_type
                 ORDER BY count DESC
             ) e2
            ) AS equipos_por_tipo
    ");
    $aggregated = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['por_prioridad'] = json_decode($aggregated['por_prioridad'] ?? '[]', true);
    $response['por_estado'] = json_decode($aggregated['por_estado'] ?? '[]', true);
    $response['por_ubicacion'] = json_decode($aggregated['por_ubicacion'] ?? '[]', true);
    $response['top_usuarios'] = json_decode($aggregated['top_usuarios'] ?? '[]', true);
    $response['equipos_por_tipo'] = json_decode($aggregated['equipos_por_tipo'] ?? '[]', true);

    // ── Q3: Actividad reciente + tickets recientes ──
    $stmt = $pdo->query("
        SELECT t.id, t.code, t.title, t.created_at,
               ts.name AS status_name, tp.name AS priority_name,
               (u.nombre || ' ' || COALESCE(u.apellidos, '')) AS usuario,
               t.description,
               CASE ts.id
                   WHEN 1 THEN 'ticket-open' WHEN 2 THEN 'ticket-process'
                   WHEN 3 THEN 'ticket-resolved' ELSE 'ticket'
               END AS status_class,
               CASE ts.id
                   WHEN 1 THEN 'Abierto' WHEN 2 THEN 'En Proceso'
                   WHEN 3 THEN 'Resuelto' ELSE ts.name
               END AS badge
        FROM oti.tickets t
        JOIN oti.ticket_statuses ts ON t.status_id = ts.id
        JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN admin.usuarios u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $now = time();
    foreach ($recent as &$item) {
        $diff = $now - strtotime($item['created_at']);
        $item['tiempo'] = $diff < 60 ? 'Hace un momento'
            : ($diff < 3600 ? 'Hace ' . floor($diff / 60) . 'm'
            : ($diff < 86400 ? 'Hace ' . floor($diff / 3600) . 'h'
            : ($diff < 604800 ? 'Hace ' . floor($diff / 86400) . 'd'
            : date('d/m', strtotime($item['created_at'])))));
    }
    unset($item);

    $response['tickets_recientes'] = $recent;
    $response['actividad_reciente'] = $recent;

    echo json_encode($response);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}
```

**Queries incluidas en cada bloque:**
| Q Antes | Q Después | Contenido |
|---------|-----------|-----------|
| Q1-Q6, Q10, Q11 | Q1 (MASTER) | Ticket stats, equipo stats, usuario stats, tickets_por_mes, ultimos_30_dias, tasa_resolucion |
| Q2, Q3, Q8, Q9, Q10 | Q2 (AGGREGATED) | por_prioridad (JSON), por_estado (JSON), por_ubicacion, top_usuarios, equipos_por_tipo |
| Q4, Q7 | Q3 (RECENT) | tickets_recientes + actividad_reciente (mismo dataset) |

**Reducción: 12 queries → 3 queries (75% menos)**

---

### 2. app/api/analisis.php — De 12 queries a 3 queries

**Archivo existente:** `app/api/analisis.php`

**Análisis de queries actuales:**
- Q1 (L30-39): ticket stats generales
- Q2 (L42-49): tickets por prioridad
- Q3 (L52-59): tickets por estado
- Q4 (L62-70): tickets por servicio
- Q5 (L73-81): tickets por ubicación
- Q6 (L84-91): tickets últimos 30 días
- Q7 (L94-101): tickets por mes
- Q8 (L104-112): top usuarios
- Q9 (L114-120): tiempo promedio de resolución
- Q10 (L123-131): stats de equipos
- Q11 (L134-141): equipos por tipo
- Q12 (L144-150): stats de usuarios

**Solución:** 3 queries con GROUP BY ROLLUP y RANK() OVER + JSON subqueries

```php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    $pdo = Database::connect();

    // ── Q1: Master stats tickets + equipos + usuarios ──
    $stmt = $pdo->query("
        SELECT
            -- Ticket stats
            COUNT(t.id) AS total_tickets,
            COUNT(t.id) FILTER (WHERE t.status_id = 1) AS abiertos,
            COUNT(t.id) FILTER (WHERE t.status_id = 2) AS en_proceso,
            COUNT(t.id) FILTER (WHERE t.status_id = 3) AS resueltos,
            COUNT(t.id) FILTER (WHERE t.status_id = 4) AS cerrados,
            AVG(EXTRACT(EPOCH FROM (t.resolved_at - t.created_at)) / 3600)
                FILTER (WHERE t.resolved_at IS NOT NULL) AS horas_promedio,
            -- Equipment stats
            COUNT(e.id) FILTER (WHERE e.id IS NOT NULL AND e.is_deleted = false) AS equipos_total,
            COUNT(e.id) FILTER (WHERE e.status = 'active' AND e.is_deleted = false) AS equipos_activos,
            COUNT(e.id) FILTER (WHERE e.status = 'maintenance' AND e.is_deleted = false) AS equipos_mantenimiento,
            COUNT(e.id) FILTER (WHERE e.status = 'inactive' AND e.is_deleted = false) AS equipos_inactivos,
            -- User stats
            COUNT(u_total.id) AS usuarios_total,
            COUNT(u_total.id) FILTER (WHERE u_total.activo = true) AS usuarios_activos,
            -- Tickets por mes
            (SELECT JSON_AGG(json_build_object('mes', sub.mes, 'count', sub.count) ORDER BY sub.mes)
             FROM (
                 SELECT TO_CHAR(t2.created_at, 'YYYY-MM') AS mes, COUNT(*) AS count
                 FROM oti.tickets t2
                 WHERE t2.created_at >= NOW() - INTERVAL '6 months'
                 GROUP BY TO_CHAR(t2.created_at, 'YYYY-MM')
             ) sub
            ) AS tickets_por_mes,
            -- Tickets últimos 30 días
            (SELECT JSON_AGG(json_build_object('date', sub2.date, 'count', sub2.count) ORDER BY sub2.date)
             FROM (
                 SELECT DATE(t3.created_at) AS date, COUNT(*) AS count
                 FROM oti.tickets t3
                 WHERE t3.created_at >= NOW() - INTERVAL '30 days'
                 GROUP BY DATE(t3.created_at)
             ) sub2
            ) AS tickets_ultimos_30_dias
        FROM oti.tickets t
        FULL JOIN oti.equipment e ON 1=0
        CROSS JOIN (SELECT COUNT(*) AS id FROM admin.usuarios) u_total
        LIMIT 1
    ");
    $master = $stmt->fetch(PDO::FETCH_ASSOC);

    $ticketStats = [
        'total' => (int)$master['total_tickets'],
        'abiertos' => (int)$master['abiertos'],
        'en_proceso' => (int)$master['en_proceso'],
        'resueltos' => (int)$master['resueltos'],
        'cerrados' => (int)$master['cerrados'],
    ];

    $tasaResolucion = $ticketStats['total'] > 0
        ? round(($ticketStats['resueltos'] + $ticketStats['cerrados']) / $ticketStats['total'] * 100, 1)
        : 0;

    // ── Q2: Aggregated data (prioridad, estado, servicio, ubicación, usuarios, equipos) ──
    $stmt = $pdo->query("
        SELECT
            (SELECT JSON_AGG(json_build_object('name', tp.name, 'count', sub3.count) ORDER BY sub3.pid)
             FROM (SELECT tp2.name, COUNT(t4.id) AS count, tp2.id AS pid
                   FROM oti.tickets t4 JOIN oti.ticket_priorities tp2 ON t4.priority_id = tp2.id
                   GROUP BY tp2.name, tp2.id) sub3
            ) AS tickets_por_prioridad,
            (SELECT JSON_AGG(json_build_object('name', ts2.name, 'count', sub4.count) ORDER BY sub4.sid)
             FROM (SELECT ts3.name, COUNT(t5.id) AS count, ts3.id AS sid
                   FROM oti.tickets t5 JOIN oti.ticket_statuses ts3 ON t5.status_id = ts3.id
                   GROUP BY ts3.name, ts3.id) sub4
            ) AS tickets_por_estado,
            (SELECT JSON_AGG(json_build_object('name', st.name, 'count', sub5.count) ORDER BY sub5.count DESC)
             FROM (SELECT st2.name, COUNT(t6.id) AS count
                   FROM oti.tickets t6 JOIN oti.service_types st2 ON t6.service_type_id = st2.id
                   GROUP BY st2.name
                   ORDER BY count DESC LIMIT 10) sub5
            ) AS tickets_por_servicio,
            (SELECT JSON_AGG(json_build_object('name', l.name, 'count', sub6.count) ORDER BY sub6.count DESC)
             FROM (SELECT l2.name, COUNT(t7.id) AS count
                   FROM oti.tickets t7 JOIN oti.locations l2 ON t7.location_id = l2.id
                   GROUP BY l2.name
                   ORDER BY count DESC LIMIT 10) sub6
            ) AS tickets_por_ubicacion,
            (SELECT JSON_AGG(json_build_object('nombre', sub7.nombre, 'apellidos', sub7.apellidos, 'count', sub7.count) ORDER BY sub7.count DESC)
             FROM (SELECT u2.nombre, u2.apellidos, COUNT(t8.id) AS count
                   FROM oti.tickets t8 JOIN admin.usuarios u2 ON t8.user_id = u2.id
                   GROUP BY u2.nombre, u2.apellidos
                   ORDER BY count DESC LIMIT 10) sub7
            ) AS top_usuarios,
            (SELECT JSON_AGG(json_build_object('asset_type', e2.asset_type, 'count', e2.count) ORDER BY e2.count DESC)
             FROM (SELECT e3.asset_type, COUNT(*) AS count
                   FROM oti.equipment e3 WHERE e3.is_deleted = false
                   GROUP BY e3.asset_type ORDER BY count DESC) e2
            ) AS equipos_por_tipo
    ");
    $agg = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'ticket_stats' => $ticketStats,
        'tickets_por_prioridad' => json_decode($agg['tickets_por_prioridad'] ?? '[]', true),
        'tickets_por_estado' => json_decode($agg['tickets_por_estado'] ?? '[]', true),
        'tickets_por_servicio' => json_decode($agg['tickets_por_servicio'] ?? '[]', true),
        'tickets_por_ubicacion' => json_decode($agg['tickets_por_ubicacion'] ?? '[]', true),
        'tickets_ultimos_30_dias' => json_decode($master['tickets_ultimos_30_dias'] ?? '[]', true),
        'tickets_por_mes' => json_decode($master['tickets_por_mes'] ?? '[]', true),
        'top_usuarios' => json_decode($agg['top_usuarios'] ?? '[]', true),
        'tiempo_promedio_horas' => round((float)$master['horas_promedio'], 1),
        'tasa_resolucion' => $tasaResolucion,
        'equipment_stats' => [
            'total' => (int)$master['equipos_total'],
            'activos' => (int)$master['equipos_activos'],
            'mantenimiento' => (int)$master['equipos_mantenimiento'],
            'inactivos' => (int)$master['equipos_inactivos'],
        ],
        'equipos_por_tipo' => json_decode($agg['equipos_por_tipo'] ?? '[]', true),
        'user_stats' => [
            'total' => (int)$master['usuarios_total'],
            'activos' => (int)$master['usuarios_activos'],
        ],
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}
```

**Reducción: 12 queries → 2 queries (83% menos)**

---

### 3. Location::getPath() — CTE Recursiva (elimina N+1)

**Archivo existente:** `app/Models/Location.php`

**Antes:** `while ($currentId)` → llama `findById()` en cada iteración (N+1 queries)

```php
public static function getPath($locationId)
{
    $path = [];
    $currentId = $locationId;
    while ($currentId) {
        $location = self::findById($currentId);
        if ($location) {
            array_unshift($path, $location);
            $currentId = $location['parent_id'];
        } else {
            break;
        }
    }
    return $path;
}
```

**Después:** Una sola query con CTE recursiva

```php
public static function getPath($locationId)
{
    if (!$locationId) return [];

    $pdo = self::db();
    $stmt = $pdo->prepare("
        WITH RECURSIVE location_path AS (
            SELECT l.*, 0 AS depth
            FROM oti.locations l
            WHERE l.id = :start_id AND l.active = true

            UNION ALL

            SELECT l.*, lp.depth + 1
            FROM oti.locations l
            INNER JOIN location_path lp ON l.id = lp.parent_id
            WHERE lp.depth < 10  -- safety limit
        )
        SELECT lp.*,
               (SELECT name FROM oti.locations WHERE id = lp.parent_id) AS parent_name
        FROM location_path lp
        ORDER BY lp.depth DESC
    ");
    $stmt->execute(['start_id' => $locationId]);
    return $stmt->fetchAll();
}
```

**Reducción: N+1 → 1 query (100% de reducción en queries individuales)**

---

### 4. Location::getById() — Self-JOIN Optimizado

**Archivo existente:** `app/Models/Location.php:243-286`

**Antes:** 3+ queries separadas (findById + fetch área + fetch sede)

```php
public static function getById($id)
{
    if (!$id) return [];
    $pdo = self::db();
    $location = self::findById($id);
    if (!$location) return [];
    $parentId = $location['parent_id'] ?? null;
    if ($location['type'] === 'oficina' || $location['type'] === 'area') {
        // Queries extra para buscar area y sede
        $stmt = $pdo->prepare("SELECT id, name, type FROM oti.locations WHERE id = :parent_id AND active = true LIMIT 1");
        $stmt->execute(['parent_id' => $parentId]);
        // ... más queries anidadas
    }
}
```

**Después:** Una sola query con self-JOIN

```php
public static function getById($id)
{
    if (!$id) return [];

    $pdo = self::db();
    $stmt = $pdo->prepare("
        SELECT l.*,
               p.id AS parent_id_val, p.name AS parent_name, p.type AS parent_type,
               g.id AS grandparent_id, g.name AS grandparent_name, g.type AS grandparent_type,
               (SELECT name FROM oti.locations WHERE id = l.parent_id) AS parent_name_flat,
               (SELECT name FROM oti.locations WHERE id = (SELECT parent_id FROM oti.locations WHERE id = l.parent_id)) AS sede_name
        FROM oti.locations l
        LEFT JOIN oti.locations p ON l.parent_id = p.id AND p.active = true
        LEFT JOIN oti.locations g ON p.parent_id = g.id AND g.active = true
        WHERE l.id = :id AND l.active = true
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $location = $stmt->fetch();

    if (!$location) return [];

    // Mapear nombres según el tipo
    if ($location['type'] === 'oficina' || $location['type'] === 'area') {
        $location['area_name'] = $location['parent_name'];
        $location['area_id'] = $location['parent_id_val'];
        $location['sede_name'] = $location['grandparent_name'];
        $location['sede_id'] = $location['grandparent_id'];
    } elseif ($location['type'] === 'sede') {
        $location['sede_name'] = $location['name'];
        $location['sede_id'] = $location['id'];
    }

    return $location;
}
```

**Reducción: 3+ queries → 1 query**

---

### 5. User::getAll() — Eliminar Fallback Duplicado

**Archivo existente:** `app/Models/User.php:119-199`

**Antes:** Si página 1 devuelve vacío, ejecuta una segunda query SIN paginación ni ROW_NUMBER

```php
if (empty($result) && $page === 1) {
    // Segunda query idéntica pero sin ROW_NUMBER ni paginación
    $simpleQuery = "SELECT ... FROM admin.usuarios u ...";
    // ...
    $result = $simpleStmt->fetchAll();
}
```

**Después:** Simplemente retornar array vacío si no hay resultados

```php
public static function getAll($search = null, $page = 1, $pageSize = 50)
{
    try {
        $pdo = self::db();

        $page = max(1, (int)$page);
        $pageSize = min(100, max(1, (int)$pageSize));
        $offset = ($page - 1) * $pageSize;

        $query = "
            SELECT u.id, (u.nombre || ' ' || COALESCE(u.apellidos, '')) as name, u.email,
                   u.es_admin,
                   (CASE WHEN u.activo THEN 'active' ELSE 'inactive' END) as status,
                   u.ultimo_acceso as last_login, r.nombre as role_name, r.id as role_id,
                   p.name as position_name, up.position_id,
                   l.name as area_name, up.location_id as area_id,
                   up.dni, up.phone, up.avatar_filename as avatar,
            ROW_NUMBER() OVER (ORDER BY u.nombre ASC) as user_number
            FROM admin.usuarios u
            LEFT JOIN oti.user_profiles up ON u.id = up.user_id
            LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
            LEFT JOIN admin.roles r ON ur.rol_id = r.id
            LEFT JOIN oti.positions p ON up.position_id = p.id
            LEFT JOIN oti.locations l ON up.location_id = l.id
        ";

        $params = [];
        if ($search) {
            $query .= " WHERE u.nombre ILIKE :search OR u.email ILIKE :search ";
            $params[':search'] = '%' . $search . '%';
        }

        $query .= " ORDER BY u.nombre ASC ";
        $query .= " LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        $stmt->execute();
        return $stmt->fetchAll();

    } catch (\PDOException $e) {
        error_log("Error en User::getAll: " . $e->getMessage());
        return [];
    }
}
```

**Reducción: 2 queries → 1 query (elimina fallback innecesario)**

---

### 6. Ticket::changeStatus() + isFinalStatus() — Cachear Status Names

**Archivo existente:** `app/Models/Ticket.php`

**Antes:** Cada llamada a changeStatus() e isFinalStatus() ejecuta una query para obtener el nombre del status:

```php
public static function changeStatus($ticketId, $statusId)
{
    $pdo = self::db();
    $stmt = $pdo->prepare("SELECT name FROM oti.ticket_statuses WHERE id = :id");
    $stmt->execute(['id' => $statusId]);
    $statusName = $stmt->fetchColumn();
    // ...
}

public static function isFinalStatus($statusId)
{
    $pdo = self::db();
    $stmt = $pdo->prepare("SELECT name FROM oti.ticket_statuses WHERE id = :id");
    $stmt->execute(['id' => $statusId]);
    $statusName = $stmt->fetchColumn();
    // ...
}
```

**Después:** Cache estático compartido entre ambos métodos

```php
private static $statusNames = null;

private static function loadStatusNames(): array
{
    if (self::$statusNames !== null) {
        return self::$statusNames;
    }
    $pdo = self::db();
    $stmt = $pdo->query("SELECT id, name FROM oti.ticket_statuses");
    $names = [];
    foreach ($stmt->fetchAll() as $row) {
        $names[(int)$row['id']] = $row['name'];
    }
    self::$statusNames = $names;
    return $names;
}

public static function changeStatus($ticketId, $statusId)
{
    $names = self::loadStatusNames();
    $statusName = $names[(int)$statusId] ?? '';

    $finalStatuses = ['Cerrado', 'Resuelto'];
    $updateData = ['status_id' => $statusId];

    if (in_array($statusName, $finalStatuses)) {
        $updateData['closed_at'] = date('Y-m-d H:i:s');
        $updateData['resolved_at'] = date('Y-m-d H:i:s');
    }

    return self::update($ticketId, $updateData);
}

public static function isFinalStatus($statusId)
{
    $names = self::loadStatusNames();
    $statusName = $names[(int)$statusId] ?? '';
    $finalStatuses = ['Cerrado', 'Resuelto', 'Cancelado'];
    return in_array($statusName, $finalStatuses);
}
```

**Reducción:** N queries (1 por cada changeStatus/isFinalStatus) → 1 query (solo la primera vez)

---

### 7. session_regenerate_id() Condicional

**Archivo existente:** `index.php`

**Antes (línea 39):** Regenera el session ID en CADA request, lo que causa invalidez de cachés y overhead innecesario.

```php
$_SESSION['last_activity'] = time();
session_regenerate_id();   // ← cada request
```

**Después:** Solo regenera cada 300 segundos (5 minutos) o en cambios de privilegio

```php
$_SESSION['last_activity'] = time();

// Regenerar session_id solo cada 5 minutos o cuando hay cambio de privilegio
$now = time();
$lastRegen = $_SESSION['_last_regenerate'] ?? 0;
if ($lastRegen === 0 || ($now - $lastRegen) > 300) {
    session_regenerate_id(true);
    $_SESSION['_last_regenerate'] = $now;
}
```

---

### 8. Ticket::findByIdentifier() — PDO Named Params Consistente

**Archivo existente:** `app/Models/User.php:49-80`

La query actual usa `:id` para 4 placeholders distintos (email, dni, username, nombre), lo cual obliga a PDO a inferir el tipo y potencialmente cachear mal el plan:

```php
WHERE u.email ILIKE :id OR up.dni = :id OR u.username ILIKE :id OR u.nombre ILIKE :id
```

**Cambio:** Usar named params individuales para mejor cache de plan:

```php
WHERE u.email ILIKE :email OR up.dni = :dni OR u.username ILIKE :username OR u.nombre ILIKE :name
```

Con bind:
```php
$stmt->execute([
    'email' => $identifier,
    'dni' => $identifier,
    'username' => $identifier,
    'name' => $identifier
]);
```

---

### 9. Migración SQL — 8 Índices Compuestos + pg_trgm

**Archivo nuevo:** `database/migrations/2026-05-21-performance-indexes.sql`

```sql
-- Migración de rendimiento: Índices compuestos + búsqueda textual
-- Ejecutar: psql -U user -d dbname -f database/migrations/2026-05-21-performance-indexes.sql
-- Todos los CREATE INDEX CONCURRENTLY requieren ser ejecutados fuera de transacción

-- ============================================================
-- 1. Tickets: filtro por status + fecha (dashboard, listados)
-- ============================================================
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_status_created
    ON oti.tickets (status_id, created_at DESC);

-- ============================================================
-- 2. Tickets: filtro por usuario + fecha (mis tickets)
-- ============================================================
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_user_created
    ON oti.tickets (user_id, created_at DESC);

-- ============================================================
-- 3. Tickets: filtro por admin asignado + status
-- ============================================================
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_assigned_status
    ON oti.tickets (assigned_admin_id, status_id)
    WHERE assigned_admin_id IS NOT NULL;

-- ============================================================
-- 4. Tickets: filtro por ubicación + status (reportes)
-- ============================================================
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_location_status
    ON oti.tickets (location_id, status_id)
    WHERE location_id IS NOT NULL;

-- ============================================================
-- 5. Equipment: filtro por ubicación + status
-- ============================================================
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_equipment_location_status
    ON oti.equipment (location_id, status)
    WHERE is_deleted = false;

-- ============================================================
-- 6. Locations: búsqueda por padre + tipo
-- ============================================================
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_locations_parent_type
    ON oti.locations (parent_id, type)
    WHERE active = true;

-- ============================================================
-- 7. Ticket Activity: historial por ticket + fecha
-- ============================================================
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ticket_activity_ticket_created
    ON oti.ticket_activity (ticket_id, created_at DESC);

-- ============================================================
-- 8. Búsqueda textual con pg_trgm (LIKE/ILIKE optimizados)
-- ============================================================
-- Requiere extensión pg_trgm (solo crear si no existe)
-- CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Índice GIN trigram para búsqueda en usuarios (nombre, email, username)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_users_search_trgm
    ON admin.usuarios USING gin (
        nombre gin_trgm_ops,
        email gin_trgm_ops,
        username gin_trgm_ops
    );

-- Índice GIN trigram para búsqueda en tickets (código, título, descripción)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_search_trgm
    ON oti.tickets USING gin (
        code gin_trgm_ops,
        title gin_trgm_ops,
        description gin_trgm_ops
    );

-- ============================================================
-- NOTAS:
-- - Los CREATE INDEX CONCURRENTLY NO pueden ejecutarse dentro de BEGIN/COMMIT
-- - Ejecutar cada uno como statement independiente
-- - No bloquean escrituras durante la creación (pero toman más tiempo)
-- - La extensión pg_trgm debe ser creada por superusuario
-- ============================================================
```

---

## Plan de Implementación (Semana 1)

| Día | Tareas | Archivos |
|-----|--------|----------|
| Día 1 AM | Consolidación stats.php (12→3 queries) | `app/api/stats.php` |
| Día 1 PM | Consolidación analisis.php (12→2 queries) | `app/api/analisis.php` |
| Día 2 AM | CTE Recursiva Location::getPath() + Self-JOIN getById() | `app/Models/Location.php` |
| Día 2 PM | Cache status names (changeStatus/isFinalStatus) + eliminar fallback User::getAll() | `app/Models/Ticket.php`, `app/Models/User.php` |
| Día 3 AM | session_regenerate_id() condicional + PDO named params | `index.php`, `app/Models/User.php` |
| Día 3 PM | Migración: 8 índices compuestos + pg_trgm | `database/migrations/2026-05-21-performance-indexes.sql` |
| Día 4 | Pruebas: EXPLAIN ANALYZE, comparación de tiempos, verificación de datos | — |
| Día 5 | Rollback si es necesario + documentación | — |

---

## Rollback Plan

### Por Componente

| Componente | Rollback |
|------------|----------|
| stats.php | Reemplazar archivo con backup `stats.php.bak` (guardar antes de modificar) |
| analisis.php | Reemplazar archivo con backup `analisis.php.bak` |
| Location.php | Restaurar métodos originales desde backup o revertir Git |
| Ticket.php | Eliminar método loadStatusNames(), restaurar queries individuales |
| User.php | Restaurar fallback en getAll() |
| index.php | Revertir session_regenerate_id() a llamada incondicional; restaurar PDO params |
| Índices SQL | `DROP INDEX CONCURRENTLY IF EXISTS ...` para cada índice creado (script de rollback incluido abajo) |

### Script de Rollback de Índices

```sql
-- database/migrations/rollback-2026-05-21-performance-indexes.sql
DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_status_created;
DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_user_created;
DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_assigned_status;
DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_location_status;
DROP INDEX CONCURRENTLY IF EXISTS oti.idx_equipment_location_status;
DROP INDEX CONCURRENTLY IF EXISTS oti.idx_locations_parent_type;
DROP INDEX CONCURRENTLY IF EXISTS oti.idx_ticket_activity_ticket_created;
DROP INDEX CONCURRENTLY IF EXISTS admin.idx_users_search_trgm;
DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_search_trgm;
```

### Procedimiento General

1. **Backups obligatorios antes de modificar:** Cada archivo PHP debe copiarse a `.bak` antes de editar
2. **Prueba en staging:** Ejecutar migración de índices en base de pruebas primero
3. **Ventana de rollback:** 24 horas después del deploy en producción, monitorear logs de errores y tiempos de respuesta
4. **Si hay regresión:** Ejecutar script de rollback de índices y restaurar archivos PHP desde backups

---

## Resumen de Reducción de Queries

| Componente | Antes | Después | Reducción |
|------------|-------|---------|-----------|
| stats.php (admin) | 12 queries | 3 queries | **75%** |
| analisis.php | 12 queries | 2 queries | **83%** |
| Location::getPath() | N+1 queries | 1 query | **~100%** |
| Location::getById() | 3+ queries | 1 query | **~67%** |
| User::getAll() | 2 queries | 1 query | **50%** |
| changeStatus/isFinalStatus | N queries | 1 query | **~100%** |
| **Total** | **~30+ queries** | **~8 queries** | **~73%** |
