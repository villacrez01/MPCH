# Optimización de Rendimiento — Sistema OTI (Enfoque A)
## Explorador de Soluciones — Tree of Thoughts

**Fecha:** 2026-05-21
**Versión:** 1.0
**Archivo:** `.specs/research/oti-rendimiento-2026-05-21.proposals.a.md`
**Contexto:** PHP 8.x + PostgreSQL 16 + Vanilla JS + Apache
**Alcance:** Exclusivamente rendimiento (NO seguridad, NO accesibilidad, NO mantenibilidad)

---

## Paso 1: Descomposición del Problema

### Problema Central

El sistema OTI sufre de carga lenta debido a una combinación tóxica de: tormenta de polling triplicado (~1,320 queries/min con 20 admins), consultas N+1 generalizadas en la capa de modelos, ausencia total de caché, assets sin optimizar (JS/CSS 228 KB sin minificar), Chart.js de 1.2 MB cargado sin lazy loading, Google Fonts bloqueante, y falta de estrategia de carga diferida en frontend.

### Subproblemas a Resolver

| # | Subproblema | Impacto | Archivos Clave |
|---|-------------|---------|----------------|
| P1 | **Tormenta de polling triplicado** — stats.php (15s) + SSE (5s) + analisis-charts (10s) compiten por la misma BD | Crítico — 1,320 q/min con 20 admins | stats.php, sse.php, realtime.js, analisis-charts.js |
| P2 | **SSE con loop infinito de queries** — while(true) ejecuta getStats() completo cada 5s | Crítico — 6 queries admin, 3 user, cada 5s para siempre | sse.php:166-186 |
| P3 | **N+1 en Location::getPath()** — bucle while que llama findById() por cada nivel jerárquico | Alto — O(n) queries | Location.php:127-143 |
| P4 | **N+1 en Location::getById()** — si type=oficina/area, ejecuta 2 queries extra para padre/abuelo | Alto — por cada ubicación cargada | Location.php:243-286 |
| P5 | **7-8 LEFT JOINs en ticket detail** — Ticket::findById() y tickets.php API | Alto — joins masivos + auto-join jerárquico 3 niveles | Ticket.php:18-50, tickets.php:86-116 |
| P6 | **User::findByIdentifier() con 5 LEFT JOINs + subquery + ILIKE en 4 campos** | Alto — sin índices GIN trgm | User.php:49-80 |
| P7 | **User::getAll() con fallback duplicado** — si página 1 vacía, ejecuta query casi idéntica otra vez | Medio — query duplicada innecesaria | User.php:119-199 |
| P8 | **analisis.php — 10 queries en carga de página** + luego polling de analisis-charts.js cada 10s | Alto — 10 queries SQL + JS polling | analisis.php:20-96, analisis-charts.js:104 |
| P9 | **session_regenerate_id() en cada request** — overhead de sesión en toda petición autenticada | Medio — I/O de sesión en cada request | index.php:39 |
| P10 | **Ticket::changeStatus() con query individual por estado** — 1 query para solo leer nombre estático | Bajo — query redundante en flujo crítico | Ticket.php:291-293, 309-317 |
| P11 | **CSS 178 KB sin minificar** — app.css (69KB) + fontawesome.min.css (~100KB) + login.css (7KB) | Alto — bloquea renderizado | head.php:10, app.css |
| P12 | **JS 51 KB sin minificar** — realtime.js (23KB) + analisis-charts.js (19KB) + search.js (9KB) con console.log en prod | Medio — peso muerto en red | realtime.js, analisis-charts.js, search.js |
| P13 | **Chart.js 1.2 MB CDN sin lazy loading** — carga en cada página de análisis, incluso si no hay gráficos | Alto — 1.2 MB bloqueante | analisis.php:103 |
| P14 | **Google Fonts bloqueante** — render-blocking resource | Medio | head.php:9 |
| P15 | **Sin caché de queries (APCu)** — ningún resultado se reutiliza entre requests | Medio — cada request recalcula todo | — |
| P16 | **Sin OPcache tuning** — PHP compila archivos sin optimización visible | Bajo — pero acumulativo | php.ini |
| P17 | **Sin preconnect/preload a CDNs** — conexiones TCP tardías a recursos externos | Bajo — 200-300ms extra | head.php |
| P18 | **Sin skeleton loading ni lazy loading** — UX percibida empeora aunque backend mejore | Medio — percepción de lentitud | dashboard.php |

### Restricciones

- PHP 8.x puro, sin frameworks (Laravel, Symfony, etc.)
- PostgreSQL como única BD
- Vanilla JS (sin React, Vue, Alpine)
- Apache con mod_rewrite
- Sin reescritura total del sistema
- Cambios deben ser reversibles individualmente

### Criterios de Evaluación

1. **Reducción de queries BD** — queries/minuto después de implementar
2. **TTFB (Time to First Byte)** — latencia de páginas principales y APIs
3. **Page Load** — tiempo hasta interactividad (LCP, FID)
4. **Payload size** — bytes transferidos en HTML, CSS, JS, fuentes
5. **Perceived performance** — skeleton loading, lazy loading, first paint
6. **Complejidad de implementación** — días-hombre vs impacto obtenido

---

## Paso 2: Mapeo del Espacio de Soluciones

### Dimensiones de Arquitectura para Rendimiento

```
Caché en Aplicación  ──────────────────────────  Sin Caché
    (APCu, Redis)                          (consulta a BD siempre)

Cálculo en Tiempo Real  ──────────────────────  Datos Precalculados
    (queries en cada request)              (materialized views, cache)

Polling Continuo  ─────────────────────────────  Push por Eventos
    (cliente pregunta cada N seg)          (servidor notifica cambios)

Assets Dinámicos  ─────────────────────────────  Assets Estáticos
    (sin build step)                       (minificado, versionado)

Carga Sincrónica  ────────────────────────────  Carga Asíncrona
    (todo bloquea el render)               (lazy, defer, async)

Consulta Unificada  ───────────────────────────  Consultas Separadas
    (una query compleja)                   (múltiples queries simples)
```

### Ejes de Trade-off

| Eje | Trade-off |
|-----|-----------|
| Precisión en tiempo real vs Carga de BD | Datos frescos = más queries ; datos cacheados = menos queries pero posible desfase |
| Velocidad de implementación vs Impacto | Cache APCu es días ; WebSockets es semanas |
| Complejidad operativa vs Ganancia | LISTEN/NOTIFY elimina polling pero añade triggers y conexiones persistentes |
| Payload pequeño vs Funcionalidad | Minificar y diferir JS reduce peso pero puede retrasar interactividad |
| Simplicidad de código vs Rendimiento | Caché y consolidación añaden capas de abstracción |

---

## Paso 3: Seis Enfoques de Alto Nivel

---

### ENFOQUE 1: «Unificación de Canales en Tiempo Real + Cache Condicional»
**Probabilidad:** 0.92 | **Complejidad:** Baja-Media | **Riesgo:** Muy Bajo

**Resumen:** Fusionar los tres canales de polling (stats.php vía realtime.js, SSE vía sse.php, analisis-charts.js) en un único endpoint con cache condicional HTTP, eliminando la tormenta de 1,320 queries/min y reemplazándola con ~30 queries/min.

**Descripción detallada:**
El sistema actual ejecuta polling triplicado porque tres componentes diferentes (realtime.js cada 15s, SSE cada 5s, analisis-charts.js cada 10s) consultan datos del dashboard de forma independiente. Cada uno llama a stats.php o a su propio endpoint, pero todos esencialmente preguntan por los mismos datos: conteos de tickets, estados, prioridades. Este enfoque fusiona los tres canales en una sola fuente de datos.

Se implementa un único endpoint `/api/v1/dashboard-poll` que reemplaza a stats.php y es consumido tanto por realtime.js como por analisis-charts.js. El endpoint calcula un hash MD5 del resultado y lo devuelve como ETag en el header HTTP. El cliente envía `If-None-Match: <etag_anterior>` en cada petición. Si los datos no cambiaron, el servidor responde 304 Not Modified sin ejecutar queries. Solo cuando hay cambios reales (nuevo ticket, cambio de estado) se ejecutan las queries completas. Esto reduce las consultas efectivas de 1,320 por minuto a ~2-5 por minuto (solo cuando algo cambia).

El SSE se simplifica drásticamente: en lugar de ejecutar getStats() completo cada 5s, el bucle while(true) hace una consulta mínima a una tabla `ota.cache_heartbeat` que registra timestamps de última modificación. Si no hay cambios desde el último check, duerme otros 5s. Si hay cambios, llama al endpoint unificado para obtener datos frescos. La conexión PostgreSQL en SSE se abre y cierra por ciclo en lugar de mantenerse persistente.

analisis-charts.js deja de hacer polling independiente y se suscribe al mismo canal SSE que realtime.js. Cuando SSE recibe una actualización, dispara un evento personalizado `dashboard:update` que ambos módulos escuchan. analisis-charts.js actualiza sus gráficos solo si los datos de análisis cambiaron.

**Decisiones clave de diseño:**
- Endpoint único `/api/v1/dashboard-poll` con soporte ETag/304: `header('ETag: "' . md5($data) . '"'); if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) { http_response_code(304); exit; }`
- Tabla `ota.cache_heartbeat` con columnas `key VARCHAR PRIMARY KEY, updated_at TIMESTAMP, etag VARCHAR` — actualizada vía triggers en tablas `tickets`, `equipment`, `locations` y `users`. SSE consulta solo esta tabla cada 5s: `SELECT updated_at, etag FROM oti.cache_heartbeat WHERE key = 'dashboard'`
- SSE modificado: conexión PostgreSQL se abre al inicio del ciclo, se consulta heartbeat, si no hay cambios se cierra conexión y espera 5s. Si hay cambios, se consulta el endpoint y se envía el evento. La conexión no se mantiene abierta entre ciclos.
- realtime.js y analisis-charts.js se reestructuran como módulos que escuchan eventos personalizados. Se crea un `SSEClient` central que maneja la conexión SSE y dispara eventos: `new TicketCounter()`, `new ChartUpdater()`, `new NotificationBadge()`.
- Se elimina el polling de stats.php desde realtime.js (línea 53) y el de analisis-charts.js (línea 104). Solo SSE hace polling, y lo hace contra cache_heartbeat en lugar de stats.php.

**Trade-offs:**
- ✅ Ganas: Reducción de ~1,320 queries/min a ~30 queries/min (97.7% menos). SSE sin conexión persistente. Código JS más limpio y modular. Sin dependencias externas. Implementación en 1-2 semanas.
- ❌ Sacrificas: Latencia de hasta 5s adicionales en la detección de cambios (el heartbeat se consulta cada 5s). Datos del dashboard pueden tener hasta 5s de desfase. La tabla cache_heartbeat añade writes con cada trigger (overhead mínimo).
- Los triggers en BD son la parte más invasiva, pero son reversibles (DROP TRIGGER) e impactan solo writes, no reads.

**Riesgos potenciales:**
- **ETag inconsistente:** Si dos requests concurrentes obtienen datos diferentes, el ETag puede ser inestable y el cliente nunca recibe 304 → mitigación: serializar el ETag con un lock de aplicación o usar un timestamp secuencial en lugar de hash del contenido.
- **Triggers olvidados:** Si se añade una nueva tabla que afecta al dashboard pero no se crea el trigger, el heartbeat nunca se actualiza → mitigación: trigger genérico que se ejecuta en todas las tablas del schema `oti` que afectan dashboard; documentar el patrón.
- **SSE reconexión:** Si la conexión SSE se cae, el cliente debe reconectar con el último ETag conocido → mitigación: `Last-Event-Id` en SSE para reanudar desde el último evento conocido.
- **Los módulos JS reestructurados pueden romper funcionalidad existente** → mitigación: implementar el SSEClient como wrapper progresivo; los módulos antiguos se mantienen en paralelo hasta validación.

---

### ENFOQUE 2: «Caché Estratégico Multinivel — APCu + Materialized Views + HTTP Cache»
**Probabilidad:** 0.88 | **Complejidad:** Media | **Riesgo:** Bajo

**Resumen:** Implementar tres niveles de caché superpuestos (APCu para datos maestros, materialized views de PostgreSQL para agregaciones pesadas, HTTP cache condicional para APIs) que eliminan consultas repetitivas sin cambiar la lógica de negocio existente.

**Descripción detallada:**
El sistema OTI no reutiliza ningún resultado entre requests. Cada vez que un usuario carga el dashboard, ejecuta 11 queries. Cada vez que SSE hace polling, ejecuta otras 6 queries. Cada vez que se carga analisis.php, ejecuta 10 queries. Para 20 admins, esto es ~1,320 queries/min de las cuales el 95% son idénticas a las del ciclo anterior porque los datos no cambiaron.

Este enfoque construye tres capas de caché que se complementan:

**Nivel 1 — APCu en aplicación:** Datos maestros que raramente cambian (catálogos de estados, prioridades, tipos de servicio, ubicaciones de alto nivel) se almacenan con TTL de 300s. La función `apcu_fetch('ticket_statuses')` se verifica antes de cualquier consulta a `ticket_statuses`. Si APCu no está disponible, se cae gracefulmente a consulta BD. Esto elimina ~30% de las queries (las de lookup tables).

**Nivel 2 — PostgreSQL Materialized Views:** Las agregaciones pesadas del dashboard (tickets por estado, por prioridad, por mes, top usuarios, actividad reciente) se precalculan en materialized views que se refrescan mediante triggers o un cron cada 60s. `SELECT * FROM mv_dashboard_stats` reemplaza 11 queries individuales. `SELECT * FROM mv_tickets_by_month` reemplaza la query de análisis temporal. Las materialized views se refrescan con `REFRESH MATERIALIZED VIEW CONCURRENTLY` para no bloquear lecturas.

**Nivel 3 — HTTP Cache condicional:** Las APIs de polling (stats.php, sse.php, analisis.php) responden con `Cache-Control: public, max-age=30` y `ETag`. Intermediarios (Apache mod_cache, CDN, o proxy) pueden cachear respuestas. Esto permite que múltiples clientes con los mismos datos reciban la misma respuesta cacheada.

La implementación es progresiva: (1) primero APCu en los modelos (cambios localizados en 5 archivos), (2) luego materialized views con migración SQL (cambios en BD), (3) finalmente headers HTTP (cambios en 3 endpoints API). Cada capa se puede activar/desactivar independientemente.

**Decisiones clave de diseño:**
- APCu: wrapper `App\Cache\Store` con métodos `get($key, $ttl, $fallback_callback)` que verifica `extension_loaded('apcu')` y cae a query si no disponible. Los modelos modificados usan: `$statuses = Cache::get('ticket_statuses', 300, fn() => $this->db->query('SELECT * FROM oti.ticket_statuses')->fetchAll())`.
- Materialized views creadas en schema `oti_mv` (separado de `oti` para claridad): `mv_dashboard_stats`, `mv_tickets_by_month`, `mv_user_activity`. Refrescadas por script PHP: `bin/refresh-mv.php` que ejecuta `REFRESH MATERIALIZED VIEW CONCURRENTLY oti_mv.mv_dashboard_stats` vía cron cada 60s, más un trigger opcional que refresca inmediatamente después de INSERT/UPDATE/DELETE en `oti.tickets`.
- Apache mod_cache se configura en `.htaccess`: `CacheEnable disk /app/api/` con `CacheDefaultExpire 30`. Alternativa ligera: headers HTTP con `Cache-Control` manejados por el middleware del endpoint.
- Las vistas PHP existentes no se modifican; solo se cambia la fuente de datos en los modelos. Si una materialized view no está disponible, se cae a la query original.

**Trade-offs:**
- ✅ Ganas: Eliminación del 80-90% de queries repetitivas. Datos maestros casi gratis (APCu). Agregaciones pesadas precargadas (materialized views). Mínimo cambio en código existente (solo modelos y endpoints). Cada capa es independiente y reversible.
- ❌ Sacrificas: APCu no es persistente (se pierde al reiniciar PHP-FPM). Materialized views pueden mostrar datos con hasta 60s de desfase. mod_cache añade complejidad a Apache. Las materialized views ocupan espacio en disco.
- TTL de 60s en materialized views significa que los datos del dashboard nunca están "en tiempo real" — pero la precisión actual tampoco es real (las queries se ejecutan con delay de 5-15s por polling).

**Riesgos potenciales:**
- **APCu sin fallback probado:** Si `extension_loaded('apcu')` es false pero la extensión existe, puede causar error fatal → mitigación: try-catch alrededor de cada `apcu_fetch` con fallback a base de datos; probar explícitamente en entorno sin APCu.
- **Materialized views desactualizadas:** Si el trigger o cron falla, los dashboards muestran datos viejos sin indicar al usuario → mitigación: incluir timestamp de última actualización en la vista: `SELECT *, NOW() - mv_refreshed_at AS stale_seconds FROM oti_mv.mv_dashboard_stats`; si stale_seconds > 120, forzar refresh síncrono.
- **mod_cache mal configurado:** puede cachear datos sensibles o servir datos de un usuario a otro → mitigación: usar `Cache-Control: private` para endpoints autenticados; solo cachear respuestas anónimas o genéricas; verificar que Vary: Cookie esté configurado.
- **Carrera de refrescos:** Dos procesos intentan refrescar la misma MV simultáneamente → mitigación: usar `REFRESH MATERIALIZED VIEW CONCURRENTLY` que adquiere lock compartido; si el lock no está disponible, saltar el refresh (esperar al próximo ciclo).
- **Espacio en disco:** Las materialized views duplican datos → mitigación: monitorear tamaño con `SELECT pg_size_pretty(pg_total_relation_size('oti_mv.mv_dashboard_stats'))` semanalmente; las tablas agregadas son pequeñas comparadas con el OLTP.

---

### ENFOQUE 3: «Optimización Quirúrgica de Queries — De N+1 a Sets + Índices GIN»
**Probabilidad:** 0.85 | **Complejidad:** Media | **Riesgo:** Bajo

**Resumen:** Reescribir las 8 consultas N+1 críticas identificadas, agregar índices faltantes (GIN trgm, compuestos, parciales), y eliminar queries redundantes (changeStatus, fallback de getAll) usando exclusivamente SQL avanzado y cambios localizados en modelos.

**Descripción detallada:**
Las 20 consultas N+1 identificadas en el sistema se pueden reducir a 5 consultas bien escritas usando SQL moderno de PostgreSQL. Este enfoque no toca caché, no toca frontend, no toca arquitectura. Solo se enfoca en que cada query haga exactamente lo que necesita, una sola vez, de la forma más eficiente posible. Es el enfoque más conservador de los tres de alta probabilidad, pero también el que tiene el impacto más directo y medible.

**Consolidaciones de queries:**

1. **Ticket::getStats() (5 queries → 1):** Reemplazar los 5 COUNTs separados (total, abiertos, en_proceso, resueltos, cerrados) con una sola query usando `COUNT(*) FILTER(WHERE status_id = 1)` para cada estado. Esto elimina 4 scans de tabla completos.

2. **Location::getPath() (N+1 → 0):** En lugar de recorrer la jerarquía con bucles PHP que ejecutan findById() por nivel, usar una única CTE recursiva:
```sql
WITH RECURSIVE location_path AS (
    SELECT id, name, parent_id, 1 AS depth FROM oti.locations WHERE id = ?
    UNION ALL
    SELECT l.id, l.name, l.parent_id, lp.depth + 1
    FROM oti.locations l INNER JOIN location_path lp ON l.id = lp.parent_id
) SELECT * FROM location_path ORDER BY depth DESC;
```

3. **Location::getById() (3 queries → 1):** Si type=oficina/area, en lugar de ejecutar queries separadas para padre y abuelo, usar un self-JOIN: `SELECT l.*, p.id AS parent_id, p.name AS parent_name, g.id AS grandparent_id, g.name AS grandparent_name FROM oti.locations l LEFT JOIN oti.locations p ON l.parent_id = p.id LEFT JOIN oti.locations g ON p.parent_id = g.id WHERE l.id = ?`.

4. **Ticket::findById() (7 LEFT JOINs → 7 LEFT JOINs pero optimizados):** No se pueden eliminar JOINs porque son necesarios para el detalle completo del ticket. Pero se optimizan: (a) eliminar joins redundantes a `admin.usuarios` (se hace dos veces), (b) mover el auto-join jerárquico de locations a una CTE, (c) agregar índices compuestos en todas las foreign keys involucradas.

5. **User::findByIdentifier() (5 LEFT JOINs + subquery):** La subquery `(SELECT id FROM admin.sistemas WHERE slug='oti')` se reemplaza por un JOIN directo con filtro. Los 4 ILIKE con OR se optimizan con un índice GIN trgm: `CREATE INDEX idx_users_search_trgm ON oti.users USING gin (name gin_trgm_ops, email gin_trgm_ops, ...)`.

6. **User::getAll() fallback duplicado:** Eliminar la segunda query (líneas 165-192). Si la primera query con paginación devuelve 0 resultados en page=1, simplemente devolver array vacío. Si la página solicitada excede los resultados, devolver array vacío. La lógica de "reintentar sin filtros" era un bug, no una feature.

7. **Ticket::changeStatus() e isFinalStatus():** Cachear el resultado de `SELECT name FROM ticket_statuses WHERE id=:id` en una variable estática de clase, o mejor aún, en APCu. Como los status IDs son ~5 registros que no cambian nunca, se cargan una vez y se reutilizan.

8. **session_regenerate_id() en cada request:** Cambiar a regeneración cada N requests (ej. cada 10) o después de cambios de privilegio. `session_regenerate_id()` en cada request es innecesario si se usa `session.use_strict_mode=1` y `session.use_cookies=1` con cookie segura.

**Índices a agregar (migración SQL única):**

```sql
-- Índices compuestos para queries de dashboard
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_status_created ON oti.tickets(status_id, created_at DESC);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_user_status ON oti.tickets(user_id, status_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_created_month ON oti.tickets(date_trunc('month', created_at));

-- Índice GIN para búsqueda full-text en tickets
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_search_trgm ON oti.tickets USING gin (title gin_trgm_ops, description gin_trgm_ops);

-- Índices para foreign keys en JOINs
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_priority ON oti.tickets(priority_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_service ON oti.tickets(service_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_location ON oti.tickets(location_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tickets_assigned ON oti.tickets(assigned_to);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_equipment_location ON oti.equipment(location_id);
```

**Decisiones clave de diseño:**
- Las migraciones de índices usan `CREATE INDEX CONCURRENTLY` para no bloquear writes en producción.
- Las CTEs recursivas son específicas de PostgreSQL y requieren que la tabla `locations` tenga una estructura jerárquica limpia (sin ciclos). Se añade una restricción CHECK: `parent_id != id` y se valida en la lógica de negocio.
- El índice GIN trgm requiere `pg_trgm` extension. Se verifica su existencia antes de crear: `CREATE EXTENSION IF NOT EXISTS pg_trgm`.
- Los cambios en modelos se protegen con feature flag a nivel de archivo: si la nueva query falla, se loguea el error y se ejecuta la query antigua.

**Trade-offs:**
- ✅ Ganas: Reducción de 8 N+1 a consultas únicas. Eliminación de queries redundantes por completo. Índices que benefician a TODAS las queries del sistema, no solo las optimizadas. Mejora medible en EXPLAIN ANALYZE de cada query. Sin dependencias externas. Sin cambios en frontend.
- ❌ Sacrificas: SQL más complejo (CTE recursiva, FILTER, GIN). La CTE recursiva en location_path requiere validación de que no haya ciclos en la jerarquía (debe agregarse check en lógica de negocio). Los índices añaden overhead en writes (INSERT/UPDATE/DELETE son marginalmente más lentos).

**Riesgos potenciales:**
- **CTE recursiva con ciclo:** Si la tabla locations tiene un ciclo (A.parent_id = B y B.parent_id = A), la CTE entra en loop infinito → mitigación: (1) agregar `MAXDEPTH 20` en la CTE, (2) validar a nivel de aplicación que no se creen ciclos, (3) query de auditoría que detecte ciclos: `WITH RECURSIVE ... HAVING COUNT(*) > 50` para detectar loops.
- **Índice GIN muy grande:** Si hay muchos tickets con títulos largos, el índice GIN puede ser pesado → mitigación: indexar solo title (no description) inicialmente; medir tamaño; agregar description solo si es necesario.
- **session_regenerate_id() menos frecuente puede ser menos seguro** → mitigación: compensar con `session.use_strict_mode=1`, `session.use_only_cookies=1`, y regenerar inmediatamente después de login/logout/cambio de rol.
- **CONCURRENTLY no disponible en todas las versiones de PostgreSQL** → mitigación: verificar `SHOW server_version` >= 9.2 (asumimos 16 en este entorno, es seguro).

---

### ENFOQUE 4: «Arquitectura de Datos Precalculados — Dashboard Sin Queries en Tiempo Real»
**Probabilidad:** 0.08 | **Complejidad:** Alta | **Riesgo:** Medio

**Resumen:** Reemplazar todas las consultas en vivo del dashboard por datos precalculados almacenados en tablas de agregación y archivos JSON estáticos servidos directamente por Apache, cero queries BD para el dashboard y polling, solo actualizaciones por cron/trigger.

**Descripción detallada:**
Este enfoque parte de una premisa radical: los datos del dashboard (conteos, estados, prioridades, actividad reciente) no necesitan estar en tiempo real. Para un sistema municipal de ~1,000 tickets/mes, datos actualizados cada 60 segundos son perfectamente aceptables. Pero el sistema actual ejecuta ~1,320 queries/min para mostrar estos mismos datos. La solución es precalcularlos.

Se crean tablas de agregación en `oti.aggregations` con estructura llave-valor-timestamp:
```sql
CREATE TABLE oti.aggregations (
    key VARCHAR(100) PRIMARY KEY,
    value JSONB NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

Un script PHP (`bin/precompute-dashboard.php`) que se ejecuta cada 60 segundos vía cron (o un schedule interno si no hay cron disponible) ejecuta 2-3 queries grandes que calculan todos los indicadores del dashboard (stats, prioridades, estados, tickets recientes, actividad, top usuarios, equipos por tipo, tickets por mes) y los almacena en la tabla `oti.aggregations` y simultáneamente escribe archivos JSON en `public/data/dashboard.json`, `public/data/analisis.json`, etc.

Las APIs dejan de conectar a la BD para el dashboard. stats.php lee `data/dashboard.json` y lo devuelve. SSE no ejecuta queries cada 5s; en su lugar, verifica el timestamp de modificación del archivo JSON (`filemtime('data/dashboard.json')`). Si no cambió, envía heartbeat. Si cambió, envía el nuevo JSON. analisis.php carga `data/analisis.json` directamente desde disco.

El resultado es que el dashboard funciona incluso si la BD está caída (mientras los JSON estén en disco). 20 admins polling cada 5s no generan ni una sola query BD. La carga en BD se reduce a 2-3 queries grandes cada 60s (ejecutadas por el script de precomputación), más las queries transaccionales de creación/actualización de tickets.

**Decisiones clave de diseño:**
- Script `bin/precompute-dashboard.php`: se ejecuta en un proceso PHP separado (no bloquea requests web). Usa el mismo autoloader y Database.php. Consultas optimizadas: 2-3 queries grandes con todas las agregaciones necesarias, no 11 queries separadas.
- Escritura atómica de JSON: `file_put_contents('data/dashboard.json.tmp', $json); rename('data/dashboard.json.tmp', 'data/dashboard.json');` para evitar lecturas de archivos parcialmente escritos.
- SSE modificado: en lugar de conectar a BD cada 5s, usa `clearstatcache(); $mtime = filemtime('data/dashboard.json');` y compara con el último mtime conocido. Si cambió, lee el archivo y envía el evento. Si no cambió, envía un comentario SSE (línea que empieza con `:`) para mantener la conexión viva sin enviar datos.
- Fallback automático: si `data/dashboard.json` no existe o está corrupto, stats.php ejecuta las queries originales como plan B. El script de precomputación, si falla, no toca los archivos existentes.
- Los archivos JSON se sirven con `Cache-Control: public, max-age=30` y compresión gzip vía Apache mod_deflate (ya configurado en .htaccess).

**Trade-offs:**
- ✅ Ganas: CERO queries BD para dashboard/polling con 20+ usuarios concurrentes. Dashboard funciona incluso con BD caída (resiliencia). TTFB de API de dashboard < 5ms (solo leer archivo y devolverlo). Sin dependencias externas. Sin cambios en frontend (las APIs devuelven exactamente el mismo JSON que antes).
- ❌ Sacrificas: Datos del dashboard con hasta 60 segundos de desfase (vs ~5s actual). Necesidad de cron o schedule para ejecutar precompute. Escritura en disco cada 60s. Dos fuentes de verdad (BD y JSON) que pueden desincronizarse si el cron falla.
- La arquitectura es radicalmente diferente en cómo se obtienen los datos, pero transparente para el frontend.

**Riesgos potenciales:**
- **Cron no disponible:** Entornos de hosting compartido pueden no tener acceso a cron → mitigación: implementar schedule interno en PHP usando `register_shutdown_function` que ejecuta el precompute si han pasado >60s desde la última ejecución. Alternativa: usar el loop de SSE para ejecutar el precompute cuando detecta que han pasado >60s (SSE tiene bucle, puede hacer el trabajo).
- **Archivos JSON corruptos:** Si el script muere mientras escribe, rename() atómico protege contra escritura parcial, pero el JSON puede estar vacío o incompleto → mitigación: validar JSON con `json_decode()` antes de servirlo; si es inválido, ejecutar queries de fallback.
- **Desincronización BD ↔ JSON:** Si un usuario crea un ticket y el precompute no se ha ejecutado aún, el dashboard no muestra el nuevo ticket hasta 60s después → mitigación: en la vista de detalle de ticket individual (que no depende de JSON), siempre consultar BD directamente. El desfase solo afecta a agregados del dashboard.
- **Disco lleno:** Si los archivos JSON crecen mucho (ej. analisis incluye datos históricos) → mitigación: monitorear tamaño en el script de precompute; si > 10MB, comprimir con gzip al escribir. Los datos agregados suelen ser < 100KB.
- **Race condition en SSE:** Si el archivo cambia mientras SSE lo lee → mitigación: lectura atómica con `file_get_contents()` que es atómica en sistemas POSIX; en Windows (entorno actual), usar copia temporal como respaldo.

---

### ENFOQUE 5: «Push Real con PostgreSQL LISTEN/NOTIFY + Canal de Eventos Unificado»
**Probabilidad:** 0.06 | **Complejidad:** Alta | **Riesgo:** Alto

**Resumen:** Eliminar TODO el polling (SSE incluido) reemplazándolo con un sistema push nativo donde PostgreSQL NOTIFICA a PHP cuando hay cambios, PHP envía el evento al navegador vía SSE (que ahora solo espera pasivamente), y el navegador actualiza el DOM sin preguntar.

**Descripción detallada:**
Este enfoque elimina el concepto de "polling" por completo del sistema. En su lugar, cada cambio de estado en la base de datos (INSERT, UPDATE, DELETE en tablas relevantes) dispara un trigger PostgreSQL que ejecuta `NOTIFY oti_channel, '{"type":"ticket_created","ticket_id":123}'`. Un worker PHP (el script SSE) ejecuta `pg_get_notify($connection, PGSQL_ASSOC)` en un bucle sin sleep, quedándose bloqueado hasta que PostgreSQL le envía una notificación real. Cuando recibe una notificación, consulta solo los datos mínimos necesarios (el ticket que cambió) y envía el evento SSE al navegador.

El navegador ya no pregunta "¿hay cambios?" cada 5s. En cambio, escucha pasivamente eventos SSE. Cuando recibe un evento `ticket_created`, el JavaScript actualiza solo la sección relevante del DOM (el contador de tickets abiertos, la tabla de recientes) sin refrescar toda la página ni el dashboard completo. Cuando recibe `status_changed`, actualiza solo la fila de la tabla correspondiente.

Para manejar el caso de que un navegador se conecte después de que ocurrieron eventos (por ejemplo, después de recargar la página), se implementa un buffer de eventos en PostgreSQL: una tabla `oti.event_queue` que almacena los últimos N eventos (ej. últimos 100). Cuando un cliente SSE se conecta, primero recibe todos los eventos del buffer, luego cambia a modo escucha. Esto asegura que ningún cliente pierda eventos.

**Decisiones clave de diseño:**
- Trigger en PostgreSQL por tabla relevante (tickets, equipment, locations, users, comments):
```sql
CREATE OR REPLACE FUNCTION oti.notify_event() RETURNS TRIGGER AS $$
BEGIN
    PERFORM pg_notify('oti_channel', json_build_object(
        'table', TG_TABLE_NAME,
        'action', TG_OP,
        'id', COALESCE(NEW.id, OLD.id),
        'timestamp', extract(epoch FROM now())::bigint
    )::text);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_tickets_notify AFTER INSERT OR UPDATE OR DELETE ON oti.tickets
FOR EACH ROW EXECUTE FUNCTION oti.notify_event();
```
- Tabla `oti.event_queue` con `id SERIAL, event_data JSONB, created_at TIMESTAMPTZ DEFAULT NOW()`. Los eventos se insertan vía trigger y se limpian automáticamente cuando superan 100 registros (DELETE con subquery).
- SSE.php modificado: `while(true) { $notify = pg_get_notify($connection, PGSQL_ASSOC); if ($notify) { procesar evento y enviar SSE } else { usleep(100000); // 100ms } }` — sin sleep(5) fijo. La conexión PostgreSQL se mantiene abierta, necesaria para `pg_get_notify` (requiere conexión persistente).
- JavaScript: se crea un `EventBus` central que recibe eventos SSE y los distribuye a suscriptores. Cada componente del dashboard se suscribe a tipos específicos: `EventBus.on('ticket_created', (data) => updateTicketCounter(+1))`, `EventBus.on('ticket_created', (data) => prependTicketRow(data.ticket))`. Los componentes de Chart.js se actualizan solo cuando el evento afecta la data del gráfico.
- Reconexión con replay de eventos perdidos: el cliente SSE envía `Last-Event-Id` con el ID del último evento recibido. En la reconexión, el servidor reenvía todos los eventos desde ese ID usando la tabla event_queue.

**Trade-offs:**
- ✅ Ganas: Latencia de ~100ms vs ~5s actual. CERO queries de polling. Cero consultas heartbeat (vs Enfoque 1 que tiene consulta mínima cada 5s). Datos verdaderamente en tiempo real. Experiencia de usuario superior (las actualizaciones llegan inmediatamente).
- ❌ Sacrificas: Conexión PostgreSQL persistente en SSE (incompatible con el singleton Database::connect() actual). Triggers en todas las tablas del schema. Complejidad operativa de pg_get_notify. El cliente SSE se reconecta con Last-Event-Id. Mayor consumo de conexiones BD (una por cliente SSE conectado). La reconexión con replay de eventos requiere infraestructura adicional (event_queue).

**Riesgos potenciales:**
- **Conexiones PostgreSQL agotadas:** Cada cliente SSE mantiene una conexión persistente. Con 20 admins = 20 conexiones dedicadas solo para SSE → mitigación: usar pgbouncer con transaction pooling para multiplexar conexiones; configurar max_connections en 100+; monitorear con `SELECT count(*) FROM pg_stat_activity`.
- **pg_get_notify() bloqueante:** si la conexión se cae, pg_get_notify lanza warning y retorna false → mitigación: detectar conexión muerta con `pg_connection_status()` y reconectar automáticamente; `pg_ping()` antes de cada iteración.
- **Tormenta de notificaciones:** Si un update masivo afecta 1000 tickets, se disparan 1000 notificaciones → mitigación: en el trigger, si TG_OP = 'UPDATE' y hay más de 10 filas en la transacción, enviar una sola notificación 'bulk_update' en lugar de 1000 individuales. Medir con `pg_trigger_depth()` para evitar triggers recursivos.
- **Event Queue overflow:** Si el servidor de eventos se cae por horas, los eventos acumulados pueden ser muchos → mitigación: event_queue con máximo 1000 eventos y TTL de 1 hora. En reconexión, si el último ID ya fue purgado, enviar refresh completo del dashboard.
- **Navegadores con SSE cerrado:** algunos proxies corporativos cierran conexiones SSE largas → mitigación: heartbeat cada 30s (comentario SSE `:heartbeat\n\n`); reconexión automática nativa de EventSource.

---

### ENFOQUE 6: «Frontend Ultraligero — Static Shell + Micro-Frontend con Island Architecture»
**Probabilidad:** 0.04 | **Complejidad:** Muy Alta | **Riesgo:** Alto

**Resumen:** Reconstruir el frontend del sistema OTI bajo una arquitectura de "islas" donde cada componente interactivo (tabla de tickets, gráficos, contadores) es una isla JavaScript independiente que se hidrata bajo demanda, con datos cargados desde fragmentos JSON precacheados, eliminando Chart.js y reemplazándolo con SVG generado en servidor, y eliminando todo el JS de 51KB reemplazándolo con ~10KB de código moderno.

**Descripción detallada:**
Este enfoque es el más radical en el frontend: cambia la forma en que el navegador obtiene y renderiza datos, pero mantiene el backend PHP intacto. Se inspira en el patrón "Islands Architecture" (Astro, Fresh) donde la página HTML inicial es estática (server-rendered) y contiene pequeñas "islas" de interactividad que se activan (hydratan) independientemente.

**Componentes del enfoque:**

1. **Shell estático:** Las páginas HTML se sirven completamente renderizadas desde el servidor (como ahora), pero con la diferencia de que el contenido dinámico (tablas de tickets, contadores) se renderiza con datos de los JSON precalculados (ver Enfoque 4). Si el JSON está disponible, se incluye inline en el HTML como `<script id="data-dashboard" type="application/json">{...}</script>`. Esto elimina la necesidad de fetch inicial para cargar el dashboard.

2. **Islas JavaScript:** En lugar de cargar `realtime.js` (23KB), `analisis-charts.js` (19KB) y `search.js` (9KB) = 51KB de JS monolítico y acoplado, se crean Web Components nativos (Custom Elements v1, sin polyfills para navegadores modernos) que son autónomos:
   - `<x-ticket-counter status="abiertos">` — se conecta al EventBus (ver Enfoque 5) y actualiza su contador numérico
   - `<x-ticket-table>` — recibe datos inline del servidor y permite ordenar/filtrar sin recargar
   - `<x-search-modal>` — búsqueda con debounce que consulta endpoint dedicado
   - `<x-chart type="bar" data="...">` — genera SVG inline (sin Chart.js)

3. **Chart.js reemplazado por SVG en servidor:** Se implementa una clase PHP `App\Services\ChartRenderer` que genera gráficos SVG directamente desde los datos, sin JavaScript. Los gráficos se renderizan en el servidor y se incluyen como SVG inline en el HTML (~2-5KB por gráfico vs 1.2MB de Chart.js). La clase implementa: barras (verticales/horizontales), líneas, pastel, doughnut. No tiene animación (estática) pero no necesita JS, no necesita carga, no necesita CDN.

4. **Lazy loading condicional:** Las islas se hidratan según visibilidad con `IntersectionObserver`. Si un gráfico no está visible (debajo del fold), no se carga su JS asociado hasta que el usuario scrollea. Los datos ya están en el HTML (inline JSON), solo falta hidratar la interactividad.

5. **Eliminación de Google Fonts blocking:** Google Fonts se elimina del render blocking. Se implementa con `link rel="preconnect"` + `link rel="preload" as="style"` + carga asíncrona con `media="print" onload="this.media='all'"` (técnica de critical CSS). Alternativa: self-hostear las fuentes con `font-display: swap`.

6. **CSS crítico inline + carga diferida del resto:** Los estilos necesarios para el above-the-fold (header, sidebar, contadores principales) se inlinan en `<head>`. El resto de `app.css` (69KB) se carga con `media="print" onload="this.media='all'"`. fontawesome.min.css (100KB) se elimina por completo; los ~10 iconos usados en el sistema se reemplazan por SVG inline (se genera un sprite SVG en `public/assets/icons/sprite.svg`).

**Decisiones clave de diseño:**
- Web Components nativos sin polyfills: el sistema está en entorno controlado (municipal, navegadores modernos). `connectedCallback()` para setup, `observedAttributes()` para reactividad, `disconnectedCallback()` para cleanup (remover event listeners).
- ChartRenderer PHP: genera elementos `<svg>` con atributos `viewBox`, `<rect>` para barras, `<path>` para líneas, `<text>` para etiquetas. Colores definidos en CSS custom properties para consistencia con el theme. ~200 líneas de PHP.
- Los datos inline JSON se inyectan en vistas PHP con `echo '<script id="data-'.$key.'" type="application/json">'.json_encode($data).'</script>';` — esto evita el fetch inicial. Las islas JS leen de `document.getElementById('data-dashboard').textContent` y se parsean con `JSON.parse()`.
- CSS critical: se identifica con PurgeCSS (o manualmente) los selectores usados above the fold: ~5-10KB. Se inlinan directamente en `<style>` en head.php. El resto se carga diferido.

**Trade-offs:**
- ✅ Ganas: Peso total de JS se reduce de 51KB a ~10KB. Chart.js 1.2MB eliminado. Google Fonts no bloquea render. CSS de 178KB se vuelve ~10KB crítico + 69KB diferido. Carga inicial del dashboard sin fetch a BD (datos inline). Gráficos SVG sin dependencias externas. TTFB < 100ms, LCP < 1.5s.
- ❌ Sacrificas: ChartRenderer PHP es significativamente menos capaz que Chart.js (sin animación, sin tooltips interactivos, sin zoom, sin tipos de gráfico exóticos). Web Components requieren navegadores modernos (Chrome 67+, Firefox 63+, Safari 12.1+ — OK para municipales pero puede excluir IE11 si aún se usa). Las islas JS requieren reescribir ~550 líneas de analisis-charts.js. El inline JSON aumenta el tamaño del HTML inicial (~100KB extra para datos de dashboard completo).

**Riesgos potenciales:**
- **ChartRenderer subdimensionado:** Si un usuario necesita interactividad en gráficos (tooltips, zoom), SVG estático no lo proporciona → mitigación: implementar tooltips CSS `:hover` con `attr()`; si se requiere interactividad avanzada, cargar Chart.js bajo demanda solo para esa página específica (lazy load con `import()` dinámico).
- **Web Components en navegadores municipales antiguos:** Si la municipalidad usa IE11 o navegadores antiguos, Custom Elements v1 no funcionan → mitigación: (a) verificar con el equipo de TI qué navegadores usan, (b) si es necesario, cargar polyfill condicional `if (!('customElements' in window)) { document.write('<script src=".../webcomponents-polyfill.js"><\/script>') }`, (c) como plan Z, mantener el JS legacy como fallback con feature flag.
- **SVG inline muy grande:** Si un gráfico tiene 365 barras (datos diarios de un año), el SVG puede ser pesado → mitigación: limitar a 30 puntos por gráfico; si hay más datos, agregar option para agrupar semanalmente o mensualmente. El product owner debe definir el límite.
- **Mantenimiento de ChartRenderer:** Si en el futuro se necesitan tipos de gráfico no implementados (heatmap, radar, treemap), alguien debe programarlos en PHP → mitigación: documentar claramente el set de gráficos soportados; si se necesita un nuevo tipo, implementar como caso aislado.
- **Datos inline vs actualización en tiempo real:** Si el dashboard se carga con datos inline y luego SSE actualiza los contadores, puede haber un "salto visual" cuando los datos cacheados son reemplazados por datos frescos → mitigación: smooth transitions CSS en los contadores; en placeholders usar skeleton loading (CSS animation) mientras los datos iniciales se cargan.

---

## Paso 4: Verificación de Diversidad

### Matriz de Diferenciación

| Dimensión | E1: Unificación Canales | E2: Caché Multinivel | E3: Optimización Queries | E4: Datos Precalculados | E5: LISTEN/NOTIFY Push | E6: Frontend Ultraligero |
|---|---|---|---|---|---|---|
| **Estrategia** | Polling eficiente | Caché en 3 capas | SQL quirúrgico | Eliminar queries en vivo | Push puro | Static shell |
| **¿Qué optimiza?** | Frecuencia de consulta | Reutilización de datos | Costo por query | Fuente de datos | Latencia de evento | Peso del frontend |
| **Afecta Backend** | Sí (endpoint, SSE) | Sí (caché, MV) | Sí (queries, índices) | Sí (cron, JSON) | Sí (triggers, SSE) | Sí (ChartRenderer) |
| **Afecta Frontend** | Sí (SSEClient) | No | No | No | Sí (EventBus) | Sí (Web Components) |
| **Afecta BD** | Tabla heartbeat | MV + APCu | Índices | Tabla aggregations | Triggers + NOTIFY | No |
| **Dependencias** | Ninguna | APCu + mod_cache | Ninguna | Cron | pgbouncer | Ninguna |
| **Tiempo real** | ~5s (heartbeat) | ~60s (MV) | N/A (bajo demanda) | ~60s (cron) | ~100ms | N/A (bajo demanda) |
| **Reducción queries** | ~98% | ~90% | ~70% | ~100% dashboard | ~100% | Indirecta |
| **Complejidad** | Baja-Media | Media | Media | Alta | Alta | Muy Alta |
| **Riesgo** | Muy Bajo | Bajo | Bajo | Medio | Alto | Alto |

### Análisis de Cobertura del Espacio de Soluciones

Los 6 enfoques cubren regiones fundamentalmente diferentes del espacio de soluciones para rendimiento:

1. **E1 (Unificación de Canales)** → Región de **protocolo/frecuencia**: no cambia qué se consulta, sino cuándo y cómo. Ataque directo al problema #1 (tormenta de polling) con el mínimo cambio posible.

2. **E2 (Caché Multinivel)** → Región de **almacenamiento intermedio**: evita recalcular lo ya calculado. Complementa a cualquier otro enfoque porque agrega capas de caché sin modificar lógica.

3. **E3 (Optimización Quirúrgica)** → Región de **eficiencia de consulta**: hace que cada query individual sea óptima. Ortogonal a E1 y E2 — se puede combinar con cualquier enfoque.

4. **E4 (Datos Precalculados)** → Región de **fuente de datos**: cambia la fuente de verdad del dashboard de BD en vivo a JSON precomputado. Arquitectura de datos radicalmente diferente.

5. **E5 (LISTEN/NOTIFY Push)** → Región de **mecanismo de entrega**: cambia el patrón de comunicación de pull a push. Elimina el polling por completo a nivel de protocolo.

6. **E6 (Frontend Ultraligero)** → Región de **entrega al cliente**: optimiza el pipeline de entrega frontend (CSS, JS, fuentes, gráficos). No toca BD ni backend de datos.

### Complementariedades

- **E1 + E2 + E3** son altamente complementarios: E3 hace las queries rápidas, E2 evita repetirlas, E1 hace que se ejecuten con menos frecuencia. Esta combinación cubre ~95% de los problemas de rendimiento del backend.

- **E4 + E6** eliminan por completo la necesidad de queries BD para el dashboard y reducen drásticamente el payload frontend, ideales si el servidor BD es el cuello de botella principal.

- **E5 es el único que logra verdadero tiempo real** (< 100ms latency), pero es el más complejo de implementar y mantener. Recomendado solo si la latencia de 5s de E1 no es aceptable.

- **E1 y E5 son mutuamente excluyentes** (uno usa polling condicional, el otro push puro). Pero E1 es un paso intermedio natural hacia E5: si ya se tiene un canal unificado y heartbeat, migrar a LISTEN/NOTIFY es cuestión de cambiar la fuente del heartbeat.

### Recomendación Preliminar

**Mejor relación impacto/esfuerzo:** E3 (Optimización Quirúrgica) + E1 (Unificación de Canales) ejecutados en paralelo.

- Semana 1: E3 — índices, consolidación de queries N+1, eliminación de fallback duplicado, cache de statuses. Esto solo reduce ~70% de las queries sin cambiar ningún endpoint ni frontend.
- Semana 2: E1 — endpoint unificado con ETag, tabla heartbeat, SSE modificado, SSEClient. Esto reduce otro ~28% de las queries.
- Semana 3: E2 (APCu) como complemento opcional si el rendimiento aún no es suficiente.

Si el TTFB sigue siendo alto después de E1+E3, agregar E4 (datos precalculados en JSON) para el dashboard, que elimina las últimas queries restantes.

E5 y E6 se reservan para una segunda fase si los requerimientos de tiempo real o peso frontend lo justifican.

---

*Fin del documento — 6 enfoques de alto nivel para optimización de rendimiento.*
