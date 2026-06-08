# Expansión C-E1: Cache First, Polling Last

**Propuesta original:** C-E1 (APCu + Throttling)
**Puntaje pruning:** 90.80
**Complejidad:** Baja
**Tiempo estimado:** 1-2 días

## Arquitectura de la Solución

```
┌──────────────────────────┐         ┌─────────────────────┐
│      Frontend JS         │         │    PostgreSQL 16     │
│  ┌───────────────────┐   │  ┌─────┤  oti schema          │
│  │ realtime.js        │───┼──┤     │  - tickets           │
│  │ (poll 30s)         │   │  │     │  - equipment         │
│  ├───────────────────┤   │  │     │  - locations         │
│  │ analisis-charts.js │──┼──┤     │  - users             │
│  │ (poll 60s)        │   │  │     └──────┬──────────────┘
│  ├───────────────────┤   │  │            │
│  │ SSE (5s, dirty)   │───┼──┤            │
│  └───────────────────┘   │  │            │
└──────────┬───────────────┘  │     ┌──────┴──────────────┐
           │                  │     │  APCu Cache         │
           ▼                  │     │  ┌───────────────┐  │
   ┌──────────────┐          │     │  │ ticket_stats   │◄─┼── Ticket.php::getStats()
   │ stats.php    │◄─────────┼─────┼──┤ (TTL 60s)      │  │
   │ (cache hit)  │          │     │  ├───────────────┤  │
   └──────────────┘          │     │  │ location_tree  │◄─┼── Location.php::getPath()
                             │     │  │ (TTL 3600s)   │  │
                             │     │  ├───────────────┤  │
                             │     │  │ dashboard_dirty│◄─┼── sse.php (flag check)
                             │     │  │ (no TTL)      │  │
                             │     │  ├───────────────┤  │
                             │     │  │ ss_activities  │◄─┼── sse.php::getStats()
                             │     │  │ (TTL 15s)     │  │
                             │     │  └───────────────┘  │
                             │     └─────────────────────┘
                             │
                        ┌────┴────┐
                        │ Apache  │
                        │ mod_rw  │
                        └─────────┘
```

**Flujo de datos con caché:**
1. Frontend polling → `stats.php` → `Cache::remember('ticket_stats', 60, ...)` → si hay hit en APCu, 0 queries; si miss, 1 query consolidada
2. Frontend SSE → `sse.php` → cada 5s verifica `Cache::dirty('dashboard')` → si FALSE, envía comentario SSE (sin payload); si TRUE, ejecuta `getStats()`, marca como no dirty
3. Location tree → `Cache::remember('location_tree', 3600, ...)` → carga todo el árbol una vez por hora, `getPath()` y `getById()` resuelven en memoria
4. Escrituras (POST/PUT) → `Cache::markDirty('dashboard')` y `Cache::forget('ticket_stats')` para invalidación inmediata

## Componentes a Implementar

### 1. App\Cache\Store — Wrapper de APCu

**Archivo nuevo:** `app/Cache/Store.php`

```php
<?php
declare(strict_types=1);

namespace App\Cache;

class Store
{
    private static ?bool $available = null;

    /**
     * Verifica si APCu está disponible
     */
    public static function isAvailable(): bool
    {
        if (self::$available === null) {
            self::$available = extension_loaded('apcu') && ini_get('apcu.enabled');
        }
        return self::$available;
    }

    /**
     * Obtiene un valor del caché. Si no existe, ejecuta $callback y almacena el resultado.
     * Protección contra cache stampede usando apcu_entry() (PHP 8.1+).
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (!self::isAvailable()) {
            return $callback();
        }

        if (function_exists('apcu_entry')) {
            return apcu_entry($key, $callback, $ttl);
        }

        // Fallback manual con semáforo
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

        // Otra solicitud está generando el caché; esperar y reintentar
        usleep(50000);
        $cached = apcu_fetch($key, $success);
        return $success ? $cached : $callback();
    }

    /**
     * Almacena un valor sin fecha de expiración (se usa para banderas dirty)
     */
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

    /**
     * Elimina una clave del caché
     */
    public static function forget(string $key): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        return apcu_delete($key);
    }

    /**
     * Verifica si una clave existe en el caché
     */
    public static function has(string $key): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        return apcu_exists($key);
    }

    /**
     * Bandera dirty: verifica si hay cambios pendientes
     * Retorna TRUE si no hay bandera (por defecto asume que hay cambios)
     */
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

    /**
     * Marca como dirty (hay cambios)
     */
    public static function markDirty(string $key): void
    {
        if (!self::isAvailable()) {
            return;
        }
        apcu_store('dirty_' . $key, true);
    }

    /**
     * Marca como no dirty (cambios procesados)
     */
    public static function markClean(string $key): void
    {
        if (!self::isAvailable()) {
            return;
        }
        apcu_store('dirty_' . $key, false);
    }

    /**
     * Estadísticas del caché (hit/miss ratio)
     * @return array{hits: int, misses: int, hit_ratio: float, num_entries: int}
     */
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

### 2. Modificaciones a Ticket.php — getStats() con APCu

**Archivo existente:** `app/Models/Ticket.php`

Agregar al inicio del archivo (después de `namespace`):

```php
use App\Cache\Store as Cache;
```

Modificar `getStats()` (reemplazar el método completo en líneas 341-382):

```php
public static function getStats($filters = [])
{
    $cacheKey = 'ticket_stats';

    if (!empty($filters)) {
        // Si hay filtros, incluir un hash en la clave para evitar colisiones
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

Modificar `getByPriority()` (reemplazar líneas 387-398):

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

Modificar `getByStatus()` (reemplazar líneas 403-414):

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

Modificar `getLast30Days()` (reemplazar líneas 419-430):

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

### 3. Cache Tree de Location

**Archivo existente:** `app/Models/Location.php`

Agregar al inicio del archivo (después de `namespace`):

```php
use App\Cache\Store as Cache;
```

Reemplazar `getPath()` (líneas 127-143):

```php
public static function getPath($locationId)
{
    $tree = self::getCachedTree();

    $path = [];
    $currentId = $locationId;

    while ($currentId && isset($tree[$currentId])) {
        array_unshift($path, $tree[$currentId]);
        $currentId = $tree[$currentId]['parent_id'];
    }

    return $path;
}
```

Reemplazar `getById()` (líneas 243-286):

```php
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
```

Agregar los nuevos métodos auxiliares al final de la clase `Location`:

```php
/**
 * Obtiene el árbol completo de ubicaciones desde APCu (carga una vez por hora)
 * @return array<int, array> Mapa [id => location]
 */
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

/**
 * Invalida el caché del árbol de ubicaciones.
 * Debe llamarse después de crear/editar/eliminar una ubicación.
 */
public static function invalidateCache(): void
{
    Cache::forget('location_tree');
}
```

Reemplazar `getAll()` (líneas 16-33) también se puede cachear opcionalmente, pero al usarse en formularios conviene mantenerlo sin caché o con TTL muy corto. Se puede cachear `getHeadquarters()` y `getByType()`:

```php
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

### 4. Bandera Dirty para SSE

**Archivo existente:** `app/api/sse.php`

Agregar al inicio (después de los `use`):

```php
use App\Cache\Store as Cache;
```

Reemplazar el `while` loop (líneas 166-187):

```php
while (!connection_aborted()) {
    try {
        if (Cache::dirty('dashboard')) {
            $data = getStats($pdo, $isOtiAdmin, $userId);
            Cache::markClean('dashboard');

            echo "event: update\n";
            echo "data: " . json_encode($data) . "\n\n";
        } else {
            // Comentario SSE para mantener conexión viva sin payload
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

Además, cachear los resultados de `getStats()` dentro de la función `getStats()` para que si múltiples conexiones SSE preguntan en el mismo ciclo, no disparen queries:

Modificar la función `getStats()` (envolver cada bloque de queries pesadas con cache):

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
                            t.id,
                            t.code as codigo,
                            t.title as titulo,
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

> **Nota:** La función `timeAgo()` se mantiene igual.

### 5. Throttling de Polling en Frontend

**Archivo existente:** `public/assets/js/realtime.js`

Cambiar intervalo de polling de 15000ms a 30000ms:

Línea 53:
```javascript
updateInterval = setInterval(fetchAllData, 30000);
```

Línea 104 (fallback SSE):
```javascript
updateInterval = setInterval(fetchAllData, 30000);
```

Línea 111 (catch SSE):
```javascript
updateInterval = setInterval(fetchAllData, 30000);
```

Además, actualizar el intervalo de notificaciones de 30s a 60s:

Línea 57:
```javascript
setInterval(fetchNotifications, 60000);
```

**Archivo existente:** `public/assets/js/analisis-charts.js`

Cambiar línea 104 de 10000ms a 60000ms:

```javascript
updateInterval = setInterval(fetchAndUpdateCharts, 60000);
```

Agregar marcador de última actualización en la UI. Al final de la función `fetchAndUpdateCharts()` (después de la línea 416), agregar:

```javascript
// Actualizar marcador de última actualización
const lastUpdateEl = document.getElementById('charts-last-update');
if (lastUpdateEl) {
    const now = new Date();
    lastUpdateEl.textContent = 'Última actualización: ' +
        now.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
```

En `app/Views/admin/analisis.php`, agregar dentro del `.page-header` (después de la línea 382), antes del cierre del div:

```php
<div id="charts-last-update" style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
    Última actualización: —</div>
```

### 6. Marcadores Dirty vía Eventos de Aplicación

En lugar de triggers SQL (que requerirían `plphp` no siempre disponible), se implementa invalidación programática: cada endpoint de escritura llama a `Cache::markDirty()` y `Cache::forget()`.

**Archivos a modificar:**

**`app/api/tickets.php`** — Agregar después de cada escritura exitosa:

Agregar al inicio:
```php
use App\Cache\Store as Cache;
```

En `case 'update-ticket'`, después de línea 244 (`echo json_encode(['success' => true, ...])`):
```php
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

En `case 'delete-ticket'`, después de línea 262:
```php
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

**`app/api/equipos.php`** — Agregar después de cada operación de escritura:
```php
Cache::markDirty('dashboard');
```

**`app/api/locations.php`** — Agregar después de cada creación/edición:
```php
Cache::markDirty('dashboard');
\App\Models\Location::invalidateCache();
```

**`app/Models/Ticket.php`** — En los métodos `create()`, `update()`, `cancel()`, `deleteTicket()`:

En `create()` después de `$pdo->commit()` (línea 236):
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

**`app/Services/TicketService.php`** — En los métodos `assign()`, `respond()`, `changeStatus()`, `addComment()`:

```php
use App\Cache\Store as Cache;

// En cada método, después de la operación exitosa:
Cache::markDirty('dashboard');
Cache::forget('ticket_stats');
```

**Migración SQL opcional** (para dirty flags vía BD como respaldo si APCu no está disponible):

**Archivo nuevo:** `database/migrations/2026-05-21-create-dirty-flags-table.sql`

```sql
-- Tabla de banderas dirty para invalidación de caché
-- Usada como respaldo si APCu no está disponible

BEGIN;

CREATE TABLE IF NOT EXISTS oti.cache_dirty_flags (
    key TEXT PRIMARY KEY,
    is_dirty BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Función para marcar como dirty desde triggers
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

-- Triggers en tablas relevantes
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

### 7. Integración en stats.php — Caché del endpoint completo

**Archivo existente:** `app/api/stats.php`

Agregar al inicio (después de `use App\Core\Database`):

```php
use App\Cache\Store as Cache;
```

Envolver el bloque entero del admin (líneas 34-189) y el bloque de usuario (líneas 191-219) con caché:

Modificar al inicio del try (después de línea 29):
```php
try {
    $cacheKey = $isOtiAdmin ? 'api_stats_admin' : 'api_stats_user_' . $userId;
    $cached = Cache::remember($cacheKey, 15, function () use ($isOtiAdmin, $userId) {
        $pdo = Database::connect();
        $response = [];

        // ... copiar todo el contenido actual del try (líneas 34-229) aquí ...
        // (sangrar un nivel y cambiar `$response` como variable local de la closure)

        return $response;
    });

    echo json_encode($cached);
```

Esto elimina el 100% de las queries de stats.php durante 15 segundos por cada cache key.

### 8. Caché en analisis.php — Server-side data

**Archivo existente:** `app/Views/admin/analisis.php`

Modificar el bloque de consultas iniciales (líneas 17-100) para usar APCu:

```php
use App\Cache\Store as Cache;

$initialData = Cache::remember('analisis_initial_data', 300, function () {
    try {
        $pdo = Database::connect();
        $data = [];

        $stmt = $pdo->query("
            SELECT COUNT(*) as total,
                   COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
                   COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
                   COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
                   COUNT(*) FILTER (WHERE status_id = 4) as cerrados
            FROM oti.tickets
        ");
        $data['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)$data['stats']['total'];
        $resueltos = (int)$data['stats']['resueltos'] + (int)$data['stats']['cerrados'];
        $data['tasa_resolucion'] = $total > 0 ? round(($resueltos / $total) * 100, 1) : 0;

        $stmt = $pdo->query("
            SELECT AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600) as horas
            FROM oti.tickets WHERE resolved_at IS NOT NULL
        ");
        $data['tiempo_promedio'] = round($stmt->fetch()['horas'] ?? 0, 1);

        $stmt = $pdo->query("
            SELECT TO_CHAR(created_at, 'YYYY-MM') as mes, COUNT(*) as count
            FROM oti.tickets WHERE created_at >= NOW() - INTERVAL '6 months'
            GROUP BY TO_CHAR(created_at, 'YYYY-MM') ORDER BY mes
        ");
        $data['tickets_por_mes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT tp.name, COUNT(t.id) as count,
                   CASE tp.id WHEN 1 THEN '#dc2626' WHEN 2 THEN '#f59e0b' WHEN 3 THEN '#10b981' END as color
            FROM oti.tickets t
            JOIN oti.ticket_priorities tp ON t.priority_id = tp.id
            GROUP BY tp.name, tp.id ORDER BY tp.id
        ");
        $data['por_prioridad'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT l.name, COUNT(t.id) as count
            FROM oti.tickets t JOIN oti.locations l ON t.location_id = l.id
            GROUP BY l.name ORDER BY count DESC LIMIT 5
        ");
        $data['por_ubicacion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT u.nombre || ' ' || COALESCE(u.apellidos, '') as name, COUNT(t.id) as count
            FROM oti.tickets t JOIN admin.usuarios u ON t.user_id = u.id
            GROUP BY u.nombre, u.apellidos ORDER BY count DESC LIMIT 5
        ");
        $data['top_usuarios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT asset_type, COUNT(*) as count
            FROM oti.equipment WHERE is_deleted = false
            GROUP BY asset_type ORDER BY count DESC
        ");
        $data['equipos_por_tipo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT COUNT(*) as total,
                   COUNT(*) FILTER (WHERE status = 'active') as activos,
                   COUNT(*) FILTER (WHERE status = 'maintenance') as mantenimiento,
                   COUNT(*) FILTER (WHERE status = 'inactive') as inactivos
            FROM oti.equipment WHERE is_deleted = false
        ");
        $data['equipos'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("
            SELECT ts.name, COUNT(t.id) as count
            FROM oti.tickets t JOIN oti.ticket_statuses ts ON t.status_id = ts.id
            GROUP BY ts.name, ts.id ORDER BY ts.id
        ");
        $data['por_estado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
});

// Extraer variables para uso en template (mantener compatibilidad)
$stats = $initialData['stats'] ?? [];
$resueltos = ((int)($stats['resueltos'] ?? 0) + (int)($stats['cerrados'] ?? 0));
```

Esto elimina las 10-12 queries de la carga inicial de analisis.php durante 5 minutos.

## Plan de Implementación (Semana 1)

| Día | Tareas | Archivos |
|-----|--------|----------|
| Día 1 AM | Cache wrapper `app/Cache/Store.php` + integración básica en `Ticket.php` | `app/Cache/Store.php`, `app/Models/Ticket.php` |
| Día 1 PM | Location cache tree + invalidación | `app/Models/Location.php` |
| Día 2 AM | SSE dirty flag + stats.php APCu cache | `app/api/sse.php`, `app/api/stats.php` |
| Día 2 PM | Frontend throttling + analisis.php cache + migración SQL | `realtime.js`, `analisis-charts.js`, `app/Views/admin/analisis.php`, migración SQL |

### Pasos adicionales:

**3. Verificar APCu en servidor:**
```bash
php -m | findstr apcu
```

Si no está instalado:
```bash
# En Windows con PHP, habilitar extension=apcu en php.ini
```

**4. Cache invalidation verification:**
Después de implementar, verificar que al crear/actualizar un ticket, la bandera dirty se marca y el SSE reacciona:
1. Abrir dashboard admin → ver stats cacheados
2. Crear ticket desde otro navegador
3. Observar que SSE recibe el update dentro de los 5s siguientes

## Rollback Plan

1. **APCu:** Comentar `Cache::remember()` en modelos → revertir a queries directas (cambiar `return Cache::remember(...)` por `return $callback()`)
2. **Location tree:** Revertir `getPath()` y `getById()` a implementación anterior (git checkout)
3. **Frontend throttling:** Restaurar intervalos originales (15000 y 10000)
4. **Migración SQL:** `DROP TRIGGER IF EXISTS trg_tickets_dirty ON oti.tickets;` y `DROP FUNCTION IF EXISTS oti.mark_cache_dirty();`
5. **Archivo `app/Cache/Store.php`:** Eliminar si causa problemas; los modelos ya tienen el fallback a queries directas
6. Tiempo de rollback: < 30 minutos

## Pruebas

### Estrategia de testing

| Prueba | Descripción | Criterio de éxito |
|--------|-------------|-------------------|
| **T1: Cache hit** | Cargar stats.php 2 veces en 15s | Segunda llamada: 0 queries SQL, respuesta < 5ms |
| **T2: Cache miss + stampede** | 10 requests concurrentes a stats.php | Solo 1 query SQL ejecutada, 9 respuestas desde APCu |
| **T3: Dirty flag SSE** | Crear ticket vía API, esperar SSE | SSE emite update dentro de 5s tras el POST |
| **T4: Location tree** | Cargar dashboard con 10 location breadcrumbs | 0 queries de locations (todo desde APCu) |
| **T5: Throttling** | Monitorear red en DevTools | realtime.js: 1 request/30s; analisis-charts.js: 1 request/60s |
| **T6: Failover** | Deshabilitar APCu (`apcu.enabled=0`) | Sistema funciona sin caché, queries directas |
| **T7: Rollback** | Ejecutar plan de rollback | Sistema vuelve a estado original en < 30 min |

### Medición de impacto

Antes de implementar (baseline):
- Queries/min: ~1,320
- Tiempo render dashboard: ~450ms
- Polling requests/min: ~14 (realtime 4/min + analisis 6/min + SSE 12/min + notifs 2/min)

Después de implementar (estimado):
- Queries/min: ~50-80 (reducción 94-96%)
- Tiempo render dashboard: ~50ms (cache hit) / ~450ms (cache miss)
- Polling requests/min: ~7 (realtime 2/min + analisis 1/min + SSE 12/min + notifs 1/min)

### Cómo probar en entorno local

```bash
# 1. Verificar APCu disponible
php -r "var_dump(extension_loaded('apcu'));"

# 2. Cache stats
php -r "
require 'vendor/autoload.php';
use App\Cache\Store as Cache;
var_dump(Cache::isAvailable());
var_dump(Cache::remember('test_key', 60, fn() => 'hello'));
var_dump(Cache::has('test_key'));
var_dump(Cache::getStats());
"

# 3. Verificar dirty flag
php -r "
require 'vendor/autoload.php';
use App\Cache\Store as Cache;
Cache::markDirty('dashboard');
var_dump(Cache::dirty('dashboard')); // true
Cache::markClean('dashboard');
var_dump(Cache::dirty('dashboard')); // false
"
```

## Resumen de Impacto en Queries

| Endpoint | Antes | Después | Reducción |
|----------|-------|---------|-----------|
| stats.php (admin) | 9 queries/request | 0 queries (cache 15s) | 100% |
| stats.php (user) | 2 queries/request | 0 queries (cache 15s) | 100% |
| sse.php (admin) | 4 queries/5s | 0 queries si no dirty, 4 queries/5s si dirty | ~95% |
| sse.php (user) | 2 queries/5s | 0 queries si no dirty, 2 queries/5s si dirty | ~95% |
| analisis.php (carga) | 10 queries | 0 queries (cache 300s) | 100% |
| Location getPath() | 1+N queries | 0 queries | 100% |
| **Total estimado** | **~1,320/min** | **~50-80/min** | **~94-96%** |
