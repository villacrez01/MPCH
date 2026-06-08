# PLAN DE OPTIMIZACIÓN DE RENDIMIENTO — SISTEMA OTI

> Generado: 2026-05-21
> Versión: 1.0
> Origen: Tree of Thoughts (3 exploradores × 6 enfoques → pruning → 3 expansiones → síntesis)

## Resumen Ejecutivo

El sistema OTI presenta una degradación progresiva de rendimiento debido a ~30 queries individuales por página que se ejecutan sin índices adecuados, sin caché, y con polling frontend excesivo (3 canales simultáneos a 15s/5s/10s). Con 20 admins concurrentes, esto genera ~1,320 queries/minuto en PostgreSQL, causando TTFBs de hasta 800ms y un Lighthouse Performance de ~30.

Este plan combina 3 soluciones evaluadas independientemente en un roadmap de 3 semanas: (1) índices compuestos + consolidación de queries (B-E1), (2) APCu cache + throttling de polling (C-E1), y (3) ETag/304 + heartbeat SSE ligero + session condicional (A-E1). El impacto estimado es reducir las queries a ~50/min, TTFB a ~30ms, y mejorar Lighthouse a ~75. Tiempo total estimado: 8-11 días hábiles con rollback independiente por semana.

## Arquitectura Target

```
┌─────────────────────────────────────────────────────────────────────┐
│                        ARQUITECTURA OPTIMIZADA OTI                   │
│                                                                     │
│  FRONTEND                    BACKEND                        DB      │
│  ┌──────────┐              ┌──────────────┐              ┌─────────┐│
│  │realtime.js│──poll 30s──▶│ stats.php    │────APCu─────▶│  PostgreSQL│
│  │(throttled)│              │ Cache::remember│  hit→0 qry │  Índices │
│  ├──────────┤              │ (TTL 15-60s) │              │compuestos│
│  │analisis-  │──poll 60s──▶│              │              │ GIN trgm │
│  │charts.js  │              └──────────────┘              │          │
│  │(throttled)│                                            │ CTE rec  │
│  ├──────────┤               ┌──────────────┐              │ COUNT    │
│  │ sse.php   │◄──SSE 5s────│  Dirty Flag   │              │ FILTER   │
│  │(light)    │───heartbeat──│ Cache::dirty()│              └─────────┘
│  └──────────┘   (0 payload) │  markDirty()  │
│                             └──────────────┘
│                                   │
│  ETag/304 ◄───────────────────────┘
│  Public/assets/js/sse-client.js
│  (único suscriptor central)
└─────────────────────────────────────────────────────────────────────┘
```

**Flujo de datos optimizado:**

1. Frontend polling (30s/60s throttled) → `stats.php` → `Cache::remember()` → APCu hit = 0 queries; miss = 1 query consolidada con COUNT FILTER
2. SSE cada 5s → `Cache::dirty('dashboard')` → si FALSE, solo envía comentario SSE; si TRUE, ejecuta `getStats()` y marca clean
3. Location tree resuelto vía CTE recursiva (Semana 1) + APCu como optimización adicional (Semana 2)
4. Escrituras (POST/PUT) → `Cache::markDirty('dashboard')` e invalidación de claves
5. ETag/304 en dashboard-poll → si contenido no cambió, responde 304 sin payload

## Roadmap de Implementación (3 Semanas)

### Semana 1: Base Sólida — Índices y Queries (3-5 días)

**Objetivo:** Reducir queries individuales de ~30 a ~8 por página. Preparar el terreno para el caché.

---

#### 1.1 Migración SQL — 8 Índices Compuestos

**Archivo:** `database/migrations/2026-05-21-performance-indexes.sql`

```sql
-- Migración de rendimiento: Índices compuestos + búsqueda textual
-- Ejecutar: psql -U user -d dbname -f database/migrations/2026-05-21-performance-indexes.sql

-- NOTA: CREATE INDEX CONCURRENTLY requiere ejecución fuera de transacción.
-- Ejecutar cada statement de forma independiente.

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
-- 8. Índices GIN trigram para búsqueda textual
-- ============================================================
-- CREATE EXTENSION IF NOT EXISTS pg_trgm; (requiere superusuario)

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_users_search_trgm
    ON admin.usuarios USING gin (
        nombre gin_trgm_ops,
        email gin_trgm_ops,
        username gin_trgm_ops
    );

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_search_trgm
    ON oti.tickets USING gin (
        code gin_trgm_ops,
        title gin_trgm_ops,
        description gin_trgm_ops
    );
```

---

#### 1.2 Ticket::getStats() — Consolidación con COUNT FILTER

**Archivo:** `app/Models/Ticket.php`

Reemplazar el método `getStats()` completo:

```php
public static function getStats($filters = [])
{
    $pdo = self::db();

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($filters['user_id'])) {
        $where .= " AND user_id = :user_id";
        $params['user_id'] = $filters['user_id'];
    }

    if (!empty($filters['assigned_admin_id'])) {
        $where .= " AND assigned_admin_id = :assigned_admin_id";
        $params['assigned_admin_id'] = $filters['assigned_admin_id'];
    }

    if (!empty($filters['location_id'])) {
        $where .= " AND location_id = :location_id";
        $params['location_id'] = $filters['location_id'];
    }

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status_id = 1) AS abiertos,
            COUNT(*) FILTER (WHERE status_id = 2) AS en_proceso,
            COUNT(*) FILTER (WHERE status_id = 3) AS resueltos,
            COUNT(*) FILTER (WHERE status_id = 4) AS cerrados
        FROM oti.tickets {$where}
    ");
    $stmt->execute($params);
    $row = $stmt->fetch();

    return [
        'total' => (int)$row['total'],
        'abiertos' => (int)$row['abiertos'],
        'en_proceso' => (int)$row['en_proceso'],
        'resueltos' => (int)$row['resueltos'],
        'cerrados' => (int)$row['cerrados'],
    ];
}
```

---

#### 1.3 Location::getPath() — CTE Recursiva

**Archivo:** `app/Models/Location.php`

Reemplazar `getPath()`:

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
            WHERE lp.depth < 10
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

---

#### 1.4 Location::getById() — Self-JOIN

**Archivo:** `app/Models/Location.php`

Reemplazar `getById()`:

```php
public static function getById($id)
{
    if (!$id) return [];

    $pdo = self::db();
    $stmt = $pdo->prepare("
        SELECT l.*,
               p.id AS parent_id_val, p.name AS parent_name, p.type AS parent_type,
               g.id AS grandparent_id, g.name AS grandparent_name, g.type AS grandparent_type,
               (SELECT name FROM oti.locations WHERE id = l.parent_id) AS parent_name_flat
        FROM oti.locations l
        LEFT JOIN oti.locations p ON l.parent_id = p.id AND p.active = true
        LEFT JOIN oti.locations g ON p.parent_id = g.id AND g.active = true
        WHERE l.id = :id AND l.active = true
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $location = $stmt->fetch();

    if (!$location) return [];

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

---

#### 1.5 User::getAll() — Eliminar Fallback

**Archivo:** `app/Models/User.php`

Reemplazar método `getAll()` — eliminar la segunda query de fallback que se ejecuta cuando la primera no retorna resultados en página 1:

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

---

#### 1.6 User::findByIdentifier() — PDO Named Params Consistentes

**Archivo:** `app/Models/User.php` (líneas 49-80)

Reemplazar la query para usar named params individuales:

```php
public static function findByIdentifier($identifier)
{
    $pdo = self::db();
    $stmt = $pdo->prepare("
        SELECT u.*, up.dni, up.phone, up.position_id, up.location_id, up.avatar_filename
        FROM admin.usuarios u
        LEFT JOIN oti.user_profiles up ON u.id = up.user_id
        WHERE u.email ILIKE :email
           OR up.dni = :dni
           OR u.username ILIKE :username
           OR u.nombre ILIKE :name
        LIMIT 1
    ");
    $stmt->execute([
        'email' => $identifier,
        'dni' => $identifier,
        'username' => $identifier,
        'name' => $identifier
    ]);
    return $stmt->fetch();
}
```

---

#### 1.7 Ticket::changeStatus() — Static Cache de Status Names

**Archivo:** `app/Models/Ticket.php`

Agregar propiedad estática y método helper, modificar `changeStatus()` e `isFinalStatus()`:

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

---

#### 1.8 stats.php — Consolidación de 12→3 Queries

**Archivo:** `app/api/stats.php`

Reemplazar completamente con las 3 queries consolidadas:

```php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;
use App\Services\AuthService;

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$isOtiAdmin = AuthService::isAdmin();
$userId = $_SESSION['user']['id'] ?? null;

try {
    $pdo = Database::connect();
    $response = [];

    // Q1: Master — tickets + equipos + usuarios en UNA query
    $stmt = $pdo->query("
        SELECT
            COUNT(t.id) FILTER (WHERE t.id IS NOT NULL) AS tickets_total,
            COUNT(t.id) FILTER (WHERE t.status_id = 1) AS tickets_abiertos,
            COUNT(t.id) FILTER (WHERE t.status_id = 2) AS tickets_en_proceso,
            COUNT(t.id) FILTER (WHERE t.status_id = 3) AS tickets_resueltos,
            COUNT(t.id) FILTER (WHERE t.status_id = 4) AS tickets_cerrados,
            (SELECT JSON_AGG(json_build_object('mes', sub.mes, 'count', sub.count) ORDER BY sub.mes)
             FROM (
                 SELECT TO_CHAR(t2.created_at, 'YYYY-MM') AS mes, COUNT(*) AS count
                 FROM oti.tickets t2
                 WHERE t2.created_at >= NOW() - INTERVAL '6 months'
                 GROUP BY TO_CHAR(t2.created_at, 'YYYY-MM')
             ) sub
            ) AS tickets_por_mes,
            (SELECT JSON_AGG(json_build_object('date', sub2.date, 'count', sub2.count) ORDER BY sub2.date)
             FROM (
                 SELECT DATE(t3.created_at) AS date, COUNT(*) AS count
                 FROM oti.tickets t3
                 WHERE t3.created_at >= NOW() - INTERVAL '30 days'
                 GROUP BY DATE(t3.created_at)
             ) sub2
            ) AS ultimos_30_dias,
            COUNT(e.id) FILTER (WHERE e.id IS NOT NULL) AS equipos_total,
            COUNT(e.id) FILTER (WHERE e.status = 'active' AND e.is_deleted = false) AS equipos_activos,
            COUNT(e.id) FILTER (WHERE e.status = 'maintenance' AND e.is_deleted = false) AS equipos_mantenimiento,
            COUNT(e.id) FILTER (WHERE e.status = 'inactive' AND e.is_deleted = false) AS equipos_inactivos,
            COUNT(u_total.id) AS usuarios_total,
            COUNT(u_total.id) FILTER (WHERE u_total.activo = true) AS usuarios_activos,
            CASE WHEN COUNT(t.id) FILTER (WHERE t.id IS NOT NULL) > 0
                 THEN ROUND(
                     (COUNT(t.id) FILTER (WHERE t.status_id IN (3,4))::numeric
                      / COUNT(t.id) FILTER (WHERE t.id IS NOT NULL)) * 100, 1)
                 ELSE 0
            END AS tasa_resolucion
        FROM oti.tickets t
        FULL JOIN oti.equipment e ON 1=0
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

    // Q2: Datos agregados (prioridad, estado, ubicación, top usuarios)
    $stmt = $pdo->query("
        SELECT
            (SELECT JSON_AGG(json_build_object('name', tp.name, 'count', sub3.count, 'color', sub3.color) ORDER BY sub3.pid)
             FROM (
                 SELECT tp2.name, COUNT(t4.id) AS count, tp2.id AS pid,
                        CASE tp2.id WHEN 1 THEN '#dc2626' WHEN 2 THEN '#f59e0b' WHEN 3 THEN '#10b981' ELSE '#6366f1' END AS color
                 FROM oti.tickets t4
                 JOIN oti.ticket_priorities tp2 ON t4.priority_id = tp2.id
                 GROUP BY tp2.name, tp2.id
             ) sub3
            ) AS por_prioridad,
            (SELECT JSON_AGG(json_build_object('name', ts2.name, 'count', sub4.count) ORDER BY sub4.sid)
             FROM (
                 SELECT ts3.name, COUNT(t5.id) AS count, ts3.id AS sid
                 FROM oti.tickets t5
                 JOIN oti.ticket_statuses ts3 ON t5.status_id = ts3.id
                 GROUP BY ts3.name, ts3.id
             ) sub4
            ) AS por_estado,
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

    // Q3: Actividad reciente + tickets recientes
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

**Criterio de éxito Semana 1:** EXPLAIN ANALYZE muestra index scans en lugar de sequential scans. Las queries individuales se reducen de ~30 a ~8 por página. TTFB stats.php baja de ~400ms a ~150ms.

**Rollback Semana 1:** Revertir commits de modelos PHP desde backup. Ejecutar script de rollback de índices:

```sql
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

---

### Semana 2: Caché Inteligente — APCu + Throttling (2-3 días)

**Objetivo:** Eliminar queries repetitivas con APCu y reducir frecuencia de polling frontend.

---

#### 2.1 App\Cache\Store — Wrapper APCu

**Archivo nuevo:** `app/Cache/Store.php`

```php
<?php
declare(strict_types=1);

namespace App\Cache;

class Store
{
    private static ?bool $available = null;

    public static function isAvailable(): bool
    {
        if (self::$available === null) {
            self::$available = extension_loaded('apcu') && ini_get('apcu.enabled');
        }
        return self::$available;
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (!self::isAvailable()) {
            return $callback();
        }

        if (function_exists('apcu_entry')) {
            return apcu_entry($key, $callback, $ttl);
        }

        $cached = apcu_fetch($key, $success);
        if ($success) {
            return $cached;
        }

        $lockKey = $key . '_lock';
        $lockTimeout = 5;

        if (apcu_add($lockKey, time(), $lockTimeout)) {
            try {
                $value = $callback();
                apcu_store($key, $value, $ttl);
                return $value;
            } finally {
                apcu_delete($lockKey);
            }
        }

        usleep(50000);
        $cached = apcu_fetch($key, $success);
        return $success ? $cached : $callback();
    }

    public static function rememberForever(string $key, callable $callback): mixed
    {
        if (!self::isAvailable()) {
            return $callback();
        }

        $cached = apcu_fetch($key, $success);
        if ($success) {
            return $cached;
        }

        $value = $callback();
        apcu_store($key, $value);
        return $value;
    }

    public static function forget(string $key): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        return apcu_delete($key);
    }

    public static function has(string $key): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        return apcu_exists($key);
    }

    public static function dirty(string $key): bool
    {
        if (!self::isAvailable()) {
            return true;
        }

        $value = apcu_fetch('dirty_' . $key, $success);
        if (!$success) {
            return true;
        }

        return (bool)$value;
    }

    public static function markDirty(string $key): void
    {
        if (!self::isAvailable()) {
            return;
        }
        apcu_store('dirty_' . $key, true);
    }

    public static function markClean(string $key): void
    {
        if (!self::isAvailable()) {
            return;
        }
        apcu_store('dirty_' . $key, false);
    }

    public static function getStats(): array
    {
        if (!self::isAvailable()) {
            return ['hits' => 0, 'misses' => 0, 'hit_ratio' => 0.0, 'num_entries' => 0];
        }

        $info = apcu_cache_info(true);
        $sma = apcu_sma_info(true);

        $hits = $info['num_hits'] ?? 0;
        $misses = $info['num_misses'] ?? 0;
        $total = $hits + $misses;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'hit_ratio' => $total > 0 ? round($hits / $total, 4) : 0.0,
            'num_entries' => $info['num_entries'] ?? 0,
            'mem_size' => $info['mem_size'] ?? 0,
            'avail_mem' => $sma['avail_mem'] ?? 0,
        ];
    }
}
```

---

#### 2.2 Autoloading del Cache

**Archivo:** `composer.json` (o `autoload.php` si no se usa composer)

Si usa composer, agregar al `autoload.psr-4`:

```json
"App\\": "app/"
```

Ejecutar:

```bash
composer dump-autoload
```

Si no usa composer, agregar en `autoload.php`:

```php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
```

---

#### 2.3 Ticket::getStats() con APCu

**Archivo:** `app/Models/Ticket.php`

Agregar al inicio (después de `namespace`):

```php
use App\Cache\Store as Cache;
```

Modificar `getStats()` para usar APCu:

```php
public static function getStats($filters = [])
{
    $cacheKey = 'ticket_stats';

    if (!empty($filters)) {
        ksort($filters);
        $cacheKey .= '_' . md5(json_encode($filters));
    }

    return Cache::remember($cacheKey, 60, function () use ($filters) {
        $pdo = self::db();

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $where .= " AND user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }

        if (!empty($filters['assigned_admin_id'])) {
            $where .= " AND assigned_admin_id = :assigned_admin_id";
            $params['assigned_admin_id'] = $filters['assigned_admin_id'];
        }

        if (!empty($filters['location_id'])) {
            $where .= " AND location_id = :location_id";
            $params['location_id'] = $filters['location_id'];
        }

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status_id = 1) AS abiertos,
                COUNT(*) FILTER (WHERE status_id = 2) AS en_proceso,
                COUNT(*) FILTER (WHERE status_id = 3) AS resueltos,
                COUNT(*) FILTER (WHERE status_id = 4) AS cerrados
            FROM oti.tickets {$where}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total' => (int)$row['total'],
            'abiertos' => (int)$row['abiertos'],
            'en_proceso' => (int)$row['en_proceso'],
            'resueltos' => (int)$row['resueltos'],
            'cerrados' => (int)$row['cerrados'],
        ];
    });
}
```

Modificar `getByPriority()`:

```php
public static function getByPriority()
{
    return Cache::remember('tickets_by_priority', 60, function () {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT tp.name, COUNT(t.id) as count
            FROM oti.tickets t
            JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            GROUP BY tp.name, tp.id
            ORDER BY tp.id
        ");
        return $stmt->fetchAll();
    });
}
```

Modificar `getByStatus()`:

```php
public static function getByStatus()
{
    return Cache::remember('tickets_by_status', 60, function () {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT ts.name, COUNT(t.id) as count
            FROM oti.tickets t
            JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            GROUP BY ts.name, ts.id
            ORDER BY ts.id
        ");
        return $stmt->fetchAll();
    });
}
```

Modificar `getLast30Days()`:

```php
public static function getLast30Days()
{
    return Cache::remember('tickets_last_30_days', 300, function () {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM oti.tickets
            WHERE created_at >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        return $stmt->fetchAll();
    });
}
```

---

#### 2.4 Location::getCachedTree() con APCu

**Archivo:** `app/Models/Location.php`

Agregar al inicio:

```php
use App\Cache\Store as Cache;
```

Modificar `getPath()`, `getById()`, y agregar métodos auxiliares:

```php
public static function getPath($locationId)
{
    if (!$locationId) return [];

    $tree = self::getCachedTree();

    $path = [];
    $currentId = $locationId;

    while ($currentId && isset($tree[$currentId])) {
        array_unshift($path, $tree[$currentId]);
        $currentId = $tree[$currentId]['parent_id'];
    }

    return $path;
}

public static function getById($id)
{
    if (!$id) return [];

    $tree = self::getCachedTree();

    if (!isset($tree[$id])) {
        $location = self::findById($id);
        return $location ?: [];
    }

    $location = $tree[$id];

    if ($location['type'] === 'oficina' || $location['type'] === 'area') {
        $parentId = $location['parent_id'];
        if ($parentId && isset($tree[$parentId])) {
            $area = $tree[$parentId];
            $location['area_name'] = $area['name'];
            $location['area_id'] = $area['id'];

            if ($area['parent_id'] && isset($tree[$area['parent_id']])) {
                $sede = $tree[$area['parent_id']];
                $location['sede_name'] = $sede['name'];
                $location['sede_id'] = $sede['id'];
            }
        }
    } elseif ($location['type'] === 'sede') {
        $location['sede_name'] = $location['name'];
        $location['sede_id'] = $location['id'];
    }

    return $location;
}

public static function getCachedTree(): array
{
    return Cache::remember('location_tree', 3600, function () {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT id, name, type, parent_id, active, nivel,
                   manager_id, code, short_name
            FROM oti.locations
            WHERE active = true
            ORDER BY type, nivel, name
        ");
        $locations = $stmt->fetchAll();

        $tree = [];
        foreach ($locations as $loc) {
            $tree[(int)$loc['id']] = $loc;
        }
        return $tree;
    });
}

public static function invalidateCache(): void
{
    Cache::forget('location_tree');
}

public static function getHeadquarters()
{
    return Cache::remember('location_headquarters', 3600, function () {
        $pdo = self::db();
        $stmt = $pdo->query("
            SELECT * FROM oti.locations
            WHERE type = 'sede' AND active = true
            ORDER BY name
        ");
        return $stmt->fetchAll();
    });
}
```

---

#### 2.5 SSE con Dirty Flag

**Archivo:** `app/api/sse.php`

Agregar al inicio:

```php
use App\Cache\Store as Cache;
```

Reemplazar el bucle `while`:

```php
while (!connection_aborted()) {
    try {
        if (Cache::dirty('dashboard')) {
            $data = getStats($pdo, $isOtiAdmin, $userId);
            Cache::markClean('dashboard');

            echo "event: update\n";
            echo "data: " . json_encode($data) . "\n\n";
        } else {
            echo ": heartbeat - no changes\n\n";
        }

        flush();

        if (ob_get_level() > 0) {
            ob_end_flush();
        }

    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Error interno']) . "\n\n";
        flush();
    }

    sleep(5);
}
```

Cachear las queries de `getStats()` con APCu (envolver cada bloque):

```php
function getStats($pdo, $isOtiAdmin, $userId) {
    $response = [];

    try {
        if ($isOtiAdmin) {
            $response['stats'] = Cache::remember('sse_ticket_stats', 15, function () use ($pdo) {
                $stmt = $pdo->query("
                    SELECT
                        COUNT(*) as total,
                        COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
                        COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
                        COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
                        COUNT(*) FILTER (WHERE status_id = 4) as cerrados
                    FROM oti.tickets
                ");
                return $stmt->fetch(PDO::FETCH_ASSOC);
            });

            $response['equipos'] = Cache::remember('sse_equipment_stats', 15, function () use ($pdo) {
                $stmt = $pdo->query("
                    SELECT
                        COUNT(*) as total,
                        COUNT(*) FILTER (WHERE status = 'active') as activos,
                        COUNT(*) FILTER (WHERE status = 'maintenance') as mantenimiento,
                        COUNT(*) FILTER (WHERE status = 'inactive') as inactivos
                    FROM oti.equipment
                    WHERE is_deleted = false
                ");
                return $stmt->fetch(PDO::FETCH_ASSOC);
            });

            $response['usuarios'] = Cache::remember('sse_user_stats', 15, function () use ($pdo) {
                $stmt = $pdo->query("
                    SELECT
                        COUNT(*) as total,
                        COUNT(*) FILTER (WHERE activo = true) as activos
                    FROM admin.usuarios
                ");
                return $stmt->fetch(PDO::FETCH_ASSOC);
            });

            $response['actividad_reciente'] = Cache::remember('sse_recent_activity', 15, function () use ($pdo) {
                $stmt = $pdo->query("
                    SELECT * FROM (
                        SELECT
                            'ticket' as tipo,
                            t.id, t.code as codigo, t.title as titulo,
                            ts.name as status_name,
                            CASE ts.id
                                WHEN 1 THEN 'ticket-open'
                                WHEN 2 THEN 'ticket-process'
                                WHEN 3 THEN 'ticket-resolved'
                                ELSE 'ticket'
                            END as status_class,
                            CASE ts.id
                                WHEN 1 THEN 'Abierto'
                                WHEN 2 THEN 'En Proceso'
                                WHEN 3 THEN 'Resuelto'
                                ELSE ts.name
                            END as badge,
                            t.created_at as fecha,
                            (u.nombre || ' ' || COALESCE(u.apellidos, '')) as usuario,
                            t.description as descripcion
                        FROM oti.tickets t
                        JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                        LEFT JOIN admin.usuarios u ON t.user_id = u.id
                        ORDER BY t.created_at DESC
                        LIMIT 8
                    ) sub
                    ORDER BY sub.fecha DESC
                ");
                $rawActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rawActivity as &$item) {
                    $item['tiempo'] = timeAgo($item['fecha']);
                }
                unset($item);

                return $rawActivity;
            });
        } else {
            $response['stats'] = Cache::remember('sse_user_stats_' . $userId, 15, function () use ($pdo, $userId) {
                $stmt = $pdo->prepare("
                    SELECT
                        COUNT(*) as total,
                        COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
                        COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
                        COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
                        COUNT(*) FILTER (WHERE status_id = 4) as cerrados
                    FROM oti.tickets
                    WHERE user_id = :user_id
                ");
                $stmt->execute(['user_id' => $userId]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            });

            $response['tickets_recientes'] = Cache::remember('sse_user_recent_' . $userId, 15, function () use ($pdo, $userId) {
                $stmt = $pdo->prepare("
                    SELECT t.id, t.code, t.title, t.created_at,
                           ts.name as status_name, tp.name as priority_name
                    FROM oti.tickets t
                    JOIN oti.ticket_statuses ts ON t.status_id = ts.id
                    JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
                    WHERE t.user_id = :user_id
                    ORDER BY t.created_at DESC
                    LIMIT 10
                ");
                $stmt->execute(['user_id' => $userId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            });
        }

        $response['timestamp'] = time();

    } catch (Exception $e) {
        error_log($e->getMessage());
        $response['error'] = 'Error interno';
    }

    return $response;
}
```

---

#### 2.6 Stats Endpoint con APCu

**Archivo:** `app/api/stats.php`

Agregar al inicio:

```php
use App\Cache\Store as Cache;
```

Envolver el bloque de lógica principal:

```php
try {
    $cacheKey = $isOtiAdmin ? 'api_stats_admin' : 'api_stats_user_' . $userId;
    $cached = Cache::remember($cacheKey, 15, function () use ($isOtiAdmin, $userId) {
        $pdo = Database::connect();
        $response = [];

        // [TODO: copiar aquí el contenido completo del try de stats.php,
        //        con las 3 queries consolidadas de la Semana 1]

        // NOTA: El código es exactamente el mismo que en 1.8,
        //       pero dentro de esta closure. Asegurar que $response
        //       se retorna al final.

        return $response;
    });

    echo json_encode($cached);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}
```

---

#### 2.7 Throttling Frontend

**Archivo:** `public/assets/js/realtime.js`

Cambiar polling de 15000ms a 30000ms:

```javascript
// Línea 53 (y 104, 111):
updateInterval = setInterval(fetchAllData, 30000);
```

Cambiar notificaciones de 30s a 60s:

```javascript
// Línea 57:
setInterval(fetchNotifications, 60000);
```

**Archivo:** `public/assets/js/analisis-charts.js`

Cambiar polling de 10000ms a 60000ms:

```javascript
// Línea 104:
updateInterval = setInterval(fetchAndUpdateCharts, 60000);
```

Agregar marcador de última actualización en la UI. Al final de `fetchAndUpdateCharts()`:

```javascript
const lastUpdateEl = document.getElementById('charts-last-update');
if (lastUpdateEl) {
    const now = new Date();
    lastUpdateEl.textContent = 'Última actualización: ' +
        now.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
```

**Archivo:** `app/Views/admin/analisis.php`

Agregar dentro del `.page-header`:

```php
<div id="charts-last-update" style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
    Última actualización: —</div>
```

---

#### 2.8 Invalidación Programática

**Archivos a modificar:**

**`app/Models/Ticket.php`** — En `create()` después de commit:

```php
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

En `update()` cuando retorna `true`:

```php
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

En `cancel()` después de `TicketActivity::create(...)`:

```php
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

En `deleteTicket()` después de `TicketActivity::create(...)`:

```php
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

**`app/api/tickets.php`** — En `case 'update-ticket'` y `case 'delete-ticket'` después de éxito:

```php
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

**`app/api/equipos.php`** — Después de cada operación de escritura:

```php
Cache::markDirty('dashboard');
```

**`app/api/locations.php`** — Después de cada creación/edición:

```php
Cache::markDirty('dashboard');
\App\Models\Location::invalidateCache();
```

**`app/Services/TicketService.php`** — En `assign()`, `respond()`, `changeStatus()`, `addComment()` después de éxito:

```php
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

---

#### 2.9 Migración SQL de Respaldo para Dirty Flags

**Archivo nuevo:** `database/migrations/2026-05-21-create-dirty-flags-table.sql`

```sql
BEGIN;

CREATE TABLE IF NOT EXISTS oti.cache_dirty_flags (
    key TEXT PRIMARY KEY,
    is_dirty BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION oti.mark_cache_dirty()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO oti.cache_dirty_flags (key, is_dirty, updated_at)
    VALUES ('dashboard', TRUE, NOW())
    ON CONFLICT (key) DO UPDATE SET
        is_dirty = TRUE,
        updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_tickets_dirty ON oti.tickets;
CREATE TRIGGER trg_tickets_dirty
    AFTER INSERT OR UPDATE OR DELETE ON oti.tickets
    FOR EACH STATEMENT EXECUTE FUNCTION oti.mark_cache_dirty();

DROP TRIGGER IF EXISTS trg_equipment_dirty ON oti.equipment;
CREATE TRIGGER trg_equipment_dirty
    AFTER INSERT OR UPDATE OR DELETE ON oti.equipment
    FOR EACH STATEMENT EXECUTE FUNCTION oti.mark_cache_dirty();

DROP TRIGGER IF EXISTS trg_ticket_activities_dirty ON oti.ticket_activities;
CREATE TRIGGER trg_ticket_activities_dirty
    AFTER INSERT ON oti.ticket_activities
    FOR EACH STATEMENT EXECUTE FUNCTION oti.mark_cache_dirty();

DROP TRIGGER IF EXISTS trg_locations_dirty ON oti.locations;
CREATE TRIGGER trg_locations_dirty
    AFTER INSERT OR UPDATE OR DELETE ON oti.locations
    FOR EACH STATEMENT EXECUTE FUNCTION oti.mark_cache_dirty();

COMMIT;
```

**Criterio de éxito Semana 2:** Las queries de polling se reducen de ~1,320/min a ~80/min con 20 admins. APCu hit ratio > 90%. SSE solo ejecuta getStats() cuando hay cambios reales.

**Rollback Semana 2:** Eliminar llamadas a `Cache::remember()` en modelos (revertir a queries directas). Restaurar polling original (15000ms y 10000ms). Eliminar archivo `app/Cache/Store.php`.

---

### Semana 3: Pulido — ETag, Heartbeat y session (2-3 días)

**Objetivo:** Refinar el sistema con optimizaciones menores pero importantes.

---

#### 3.1 ETag/304 en Endpoint de Stats

**Archivo:** `app/api/stats.php`

Agregar headers ETag con cache condicional:

```php
$json = json_encode($response);
$etag = md5($json);

if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    $clientEtag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
    if ($clientEtag === $etag) {
        http_response_code(304);
        header('ETag: "' . $etag . '"');
        header('Cache-Control: public, max-age=15');
        exit;
    }
}

header('ETag: "' . $etag . '"');
header('Cache-Control: public, max-age=15');
echo $json;
```

---

#### 3.2 session_regenerate_id() Condicional

**Archivo:** `index.php`

Reemplazar `session_regenerate_id()` incondicional:

```php
$_SESSION['last_activity'] = time();

$now = time();
$lastRegen = $_SESSION['_last_regenerate'] ?? 0;
if ($lastRegen === 0 || ($now - $lastRegen) > 300) {
    session_regenerate_id(true);
    $_SESSION['_last_regenerate'] = $now;
}
```

---

#### 3.3 Tabla cache_heartbeat (respaldo si APCu no disponible)

**Archivo:** `database/migrations/2026-05-21-heartbeat-table.sql`

```sql
BEGIN;

CREATE TABLE IF NOT EXISTS oti.cache_heartbeat (
    key VARCHAR(100) PRIMARY KEY,
    etag VARCHAR(64) NOT NULL DEFAULT md5(random()::text || clock_timestamp()::text),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO oti.cache_heartbeat (key) VALUES ('dashboard')
ON CONFLICT (key) DO NOTHING;

CREATE OR REPLACE FUNCTION oti.update_heartbeat()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE oti.cache_heartbeat
    SET etag = md5(random()::text || clock_timestamp()::text),
        updated_at = NOW()
    WHERE key = 'dashboard';
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_tickets_heartbeat ON oti.tickets;
CREATE TRIGGER trg_tickets_heartbeat
AFTER INSERT OR UPDATE OR DELETE ON oti.tickets
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

DROP TRIGGER IF EXISTS trg_equipment_heartbeat ON oti.equipment;
CREATE TRIGGER trg_equipment_heartbeat
AFTER INSERT OR UPDATE OR DELETE ON oti.equipment
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

DROP TRIGGER IF EXISTS trg_locations_heartbeat ON oti.locations;
CREATE TRIGGER trg_locations_heartbeat
AFTER INSERT OR UPDATE OR DELETE ON oti.locations
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

DROP TRIGGER IF EXISTS trg_ticket_activity_heartbeat ON oti.ticket_activity;
CREATE TRIGGER trg_ticket_activity_heartbeat
AFTER INSERT ON oti.ticket_activity
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

DROP TRIGGER IF EXISTS trg_usuarios_heartbeat ON admin.usuarios;
CREATE TRIGGER trg_usuarios_heartbeat
AFTER INSERT OR UPDATE OR DELETE ON admin.usuarios
FOR EACH STATEMENT EXECUTE FUNCTION oti.update_heartbeat();

COMMIT;
```

**Criterio de éxito Semana 3:** ETag evita payloads redundantes (respuestas 304 sin cuerpo). Sesiones regeneradas cada 5 min en lugar de cada request.

**Rollback Semana 3:** Revertir cambios a `index.php` (restaurar `session_regenerate_id()` incondicional). Remover headers ETag de `stats.php`.

---

## Estimación de Impacto Acumulativo

| Métrica | Actual | Semana 1 | Semana 2 | Semana 3 |
|---------|:------:|:--------:|:--------:|:--------:|
| Queries/min (20 admins) | ~1,320 | ~900 | ~80 | ~50 |
| Queries por carga de dashboard | ~30 | ~8 | ~1 (cache hit) | ~0 (304) |
| TTFB stats.php | ~400ms | ~150ms | ~50ms | ~30ms |
| TTFB analisis.php | ~800ms | ~300ms | ~100ms | ~100ms |
| Lighthouse Performance | ~30 | ~45 | ~70 | ~75 |
| Payload CSS/JS | 228KB | 228KB | 228KB | ~80KB |
| Conexiones BD SSE | 20 activas | 20 activas | 0 (dirty flag) | 0 |
| Polling JS por admin | 3/min | 3/min | 2/min | 2/min |
| APCu hit ratio | — | — | > 90% | > 95% |

## Plan de Pruebas

### Semana 1 — Índices y Queries

| Prueba | Descripción | Criterio de éxito |
|--------|-------------|-------------------|
| **P1.1 EXPLAIN ANALYZE** | Ejecutar queries principales con EXPLAIN | Index scans, no sequential scans |
| **P1.2 COUNT FILTER** | Verificar que counts coinciden con versión anterior | Mismos valores numéricos |
| **P1.3 CTE Recursiva** | getPath() con ubicación de 3 niveles | Path completo con sede→área→oficina |
| **P1.4 Self-JOIN** | getById() con oficina/área/sede | Todos los nombres parent resueltos |
| **P1.5 Static Cache** | changeStatus() múltiples veces | Solo 1 query a ticket_statuses |
| **P1.6 Regresión** | CRUD completo de tickets | Sin errores, datos correctos |

```sql
-- Verificar índices en uso:
SELECT schemaname, tablename, indexname, indexdef
FROM pg_indexes
WHERE tablename IN ('tickets', 'equipment', 'locations', 'ticket_activity', 'usuarios')
ORDER BY tablename, indexname;

-- Verificar queries usando índices:
EXPLAIN (ANALYZE, BUFFERS)
SELECT COUNT(*) FILTER (WHERE status_id = 1) AS abiertos FROM oti.tickets;
```

### Semana 2 — APCu + Throttling

| Prueba | Descripción | Criterio de éxito |
|--------|-------------|-------------------|
| **P2.1 Cache hit** | stats.php 2 veces en 15s | 2da llamada: 0 queries SQL, < 5ms |
| **P2.2 Cache stampede** | 10 requests concurrentes | Solo 1 query SQL, 9 desde APCu |
| **P2.3 Dirty flag** | Crear ticket vía API, esperar SSE | SSE emite update dentro de 5s |
| **P2.4 Location tree** | Dashboard con 10 breadcrumbs | 0 queries de locations |
| **P2.5 Throttling** | Monitorear red en DevTools | realtime.js: 1 req/30s; analisis: 1 req/60s |
| **P2.6 Failover** | Deshabilitar APCu (`apcu.enabled=0`) | Sistema funciona sin caché |

```bash
# Verificar APCu
php -m | findstr apcu

# Verificar cache stats
php -r "
require 'vendor/autoload.php';
use App\Cache\Store as Cache;
print_r(Cache::getStats());
"

# Verificar dirty flag
php -r "
require 'vendor/autoload.php';
use App\Cache\Store as Cache;
Cache::markDirty('dashboard');
var_dump(Cache::dirty('dashboard'));
Cache::markClean('dashboard');
var_dump(Cache::dirty('dashboard'));
"
```

### Semana 3 — Pulido

| Prueba | Descripción | Criterio de éxito |
|--------|-------------|-------------------|
| **P3.1 ETag** | stats.php con If-None-Match | 304 sin cuerpo si no cambios |
| **P3.2 Session** | Monitorear encabezados de sesión | session_id cambia cada 5 min máximo |
| **P3.3 Heartbeat DB** | INSERT en tickets → heartbeat cambia | etag cambia, SSE detecta |

```bash
# Probar ETag
curl -v http://localhost/OTI/app/api/stats.php
curl -v -H "If-None-Match: <etag_del_primer_request>" http://localhost/OTI/app/api/stats.php
```

## Plan de Rollback Global

### Por Semana

| Semana | Tiempo de rollback | Procedimiento |
|:------:|:------------------:|---------------|
| 1 | < 1 hora | `git checkout` de modelos PHP + script DROP INDEX CONCURRENTLY |
| 2 | < 30 min | Eliminar `app/Cache/Store.php`, restaurar `Ticket.php` y `Location.php`, restaurar polling |
| 3 | < 15 min | Revertir `index.php` y `stats.php` |

### Script de Rollback Completo

```bash
# 1. Revertir archivos PHP (si se usó git)
git checkout -- app/Models/Ticket.php app/Models/Location.php app/Models/User.php
git checkout -- app/api/stats.php app/api/sse.php
git checkout -- index.php
git checkout -- public/assets/js/realtime.js public/assets/js/analisis-charts.js

# 2. Eliminar archivos nuevos
rm -f app/Cache/Store.php

# 3. Revertir índices (ejecutar cada DROP por separado)
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_status_created;"
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_user_created;"
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_assigned_status;"
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_location_status;"
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS oti.idx_equipment_location_status;"
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS oti.idx_locations_parent_type;"
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS oti.idx_ticket_activity_ticket_created;"
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS admin.idx_users_search_trgm;"
psql -U user -d dbname -c "DROP INDEX CONCURRENTLY IF EXISTS oti.idx_tickets_search_trgm;"

# 4. Revertir tablas auxiliares
psql -U user -d dbname -c "DROP TABLE IF EXISTS oti.cache_dirty_flags CASCADE;"
psql -U user -d dbname -c "DROP TABLE IF EXISTS oti.cache_heartbeat CASCADE;"
psql -U user -d dbname -c "DROP FUNCTION IF EXISTS oti.mark_cache_dirty();"
psql -U user -d dbname -c "DROP FUNCTION IF EXISTS oti.update_heartbeat();"
```

### Precauciones

1. **Backups obligatorios:** Cada archivo PHP debe copiarse a `.bak` antes de editar
2. **Prueba en staging:** Ejecutar migración de índices en base de pruebas primero
3. **Ventana de rollback:** 24 horas después del deploy en producción
4. **Monitoreo:** Verificar logs de errores y tiempos de respuesta post-deploy

## Monitoreo Post-Implementación

### Dashboard de Monitoreo de Caché

Crear endpoint interno para verificar estado:

**Archivo:** `app/api/cache-stats.php`

```php
<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
use App\Cache\Store as Cache;

echo json_encode([
    'apcu_available' => Cache::isAvailable(),
    'cache_stats' => Cache::getStats(),
    'dirty_status' => [
        'dashboard' => Cache::dirty('dashboard'),
    ],
    'php_version' => phpversion(),
    'apcu_version' => phpversion('apcu'),
]);
```

### Métricas a Monitorear

| Métrica | Dónde | Frecuencia | Alerta si |
|---------|-------|-----------|-----------|
| APCu hit ratio | `Cache::getStats()` | Cada hora | < 70% |
| Queries por minuto | `pg_stat_activity` | Continuo | > 200 |
| TTFB stats.php | DevTools / logs | Por release | > 200ms |
| 304 rate | Logs de Apache | Diario | < 20% de requests |
| Errores APCu | `error_log` | Continuo | Cualquier error |

### Verificación de Queries

```sql
-- Monitorear queries activas
SELECT state, count(*) as count,
       ROUND(AVG(EXTRACT(EPOCH FROM (NOW() - query_start))::numeric), 2) as avg_duration_sec
FROM pg_stat_activity
WHERE state = 'active' AND query NOT LIKE '%pg_stat%'
GROUP BY state;

-- Hit ratio de índices
SELECT schemaname, tablename, indexname,
       idx_scan, idx_tup_read, idx_tup_fetch
FROM pg_stat_user_indexes
WHERE schemaname = 'oti'
ORDER BY idx_scan DESC;
```

## Referencias

| Archivo | Descripción |
|---------|-------------|
| `.specs/research/oti-rendimiento-2026-05-21.selection.md` | Selección de Top 3 propuestas (Fase 2b) |
| `.specs/research/oti-rendimiento-2026-05-21.expansion-A-E1.md` | Unificación de Canales + ETag/304 |
| `.specs/research/oti-rendimiento-2026-05-21.expansion-B-E1.md` | Consolidación de Queries + Índices |
| `.specs/research/oti-rendimiento-2026-05-21.expansion-C-E1.md` | Cache First (APCu) + Throttling |
| `.specs/research/oti-estrategia-4.5.md` | Estrategia adaptativa FULL_SYNTHESIS |
| `database/migrations/2026-05-21-performance-indexes.sql` | (Semana 1) 8 índices compuestos + GIN trgm |
| `database/migrations/2026-05-21-create-dirty-flags-table.sql` | (Semana 2) Tabla dirty flags de respaldo |
| `database/migrations/2026-05-21-heartbeat-table.sql` | (Semana 3) Tabla heartbeat con triggers |
| `app/Cache/Store.php` | (Semana 2) Wrapper APCu con remember/dirty |
| `app/api/stats.php` | (S1+S2+S3) Consolidado 12→3 queries + APCu + ETag |
| `app/api/sse.php` | (S2) Dirty flag en lugar de getStats() cada 5s |
| `public/assets/js/realtime.js` | (S2+S3) Polling 30s + suscriptor SSEClient |
| `public/assets/js/analisis-charts.js` | (S2+S3) Polling 60s + suscriptor SSEClient |
| `app/Models/Ticket.php` | (S1+S2) Static cache + APCu + invalidación |
| `app/Models/Location.php` | (S1+S2) CTE recursiva + getCachedTree() |
| `app/Models/User.php` | (S1) Eliminar fallback + PDO named params |
| `index.php` | (S3) session_regenerate_id() condicional |
