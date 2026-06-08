# PLAN DE OPTIMIZACIÓN DE RENDIMIENTO — SISTEMA OTI
## Enfoques de Alto Nivel para Eliminar Cuellos de Botella

**Fecha:** 2026-05-21  
**Versión:** 1.0  
**Alcance EXCLUSIVO:** Rendimiento (20 cuellos de botella confirmados)  
**Archivo:** `.specs/research/oti-optimizacion-2026-05-21.proposals.b.md`

---

## PASO 1: DESCOMPOSICIÓN DEL PROBLEMA

### Problema Central
El sistema OTI sufre de carga lenta causada por **tres tormentas concurrentes**: (1) tormenta de polling — 3 mecanismos independientes (SSE cada 5s, realtime.js cada 15s, analisis-charts.js cada 10s) compiten por stats.php generando ~1,320 queries/minuto con 20 admins; (2) tormenta de N+1 — Location::getPath(), Location::getById(), Ticket::getStats() ejecutan queries individuales en loops; (3) tormenta de assets — 178KB CSS sin minificar, 50KB JS sin minificar, Chart.js 1.2MB CDN sin lazy, Google Fonts blocking. El resultado es un sistema funcional pero con latencia perceptible y escalabilidad limitada.

### Restricciones de la Solución
- PHP 8.x puro, sin frameworks (Laravel, Symfony)
- PostgreSQL 16 como única BD
- Vanilla JS (sin React, Vue, Alpine)
- Apache con mod_rewrite
- Sin reescritura total del sistema
- Cambios reversibles y desplegables incrementalmente

### Árbol de Causas Raíz

```
Carga Lenta OTI
├── Backend (80% del problema)
│   ├── Demasiadas queries (70%)
│   │   ├── Tormenta de polling stats.php (3 fuentes, ~1,320 qpm)
│   │   ├── N+1 en Location (getPath + getById = hasta 5 queries c/u)
│   │   ├── analisis.php carga 10 queries en request inicial
│   │   └── changeStatus() query individual por estado
│   ├── Queries ineficientes (20%)
│   │   ├── 7-8 LEFT JOINs en ticket detail
│   │   ├── ILIKE en 4 campos sin GIN trgm index
│   │   └── Subquery correlacionada en Location::findById()
│   └── Sin caché (10%)
│       ├── APCu no usado
│       └── Sin ETag/If-Modified-Since en APIs de polling
├── Frontend (15%)
│   ├── CSS 178KB sin minificar (todo blocking en <head>)
│   ├── JS 50KB sin minificar + console.log en producción
│   ├── Chart.js 1.2MB CDN sin lazy loading
│   └── Google Fonts blocking render
└── Arquitectura/Servidor (5%)
    ├── Sin OPcache tuning
    ├── session_regenerate_id() en cada request
    └── Feature flags V2 muertos
```

### Criterios de Éxito
- **Queries por request:** Reducir de 11→3 en stats.php (admin), de 10→3 en analisis.php
- **Frecuencia de polling:** Eliminar 2 de 3 fuentes (máximo 1 mecanismo activo)
- **Tamaño assets:** CSS <40KB, JS <30KB, Chart.js lazy-load o reemplazado por SVG server-side
- **TTFB APIs:** <100ms (vs ~400ms actual estimado)
- **Lighthouse Performance:** >70 (vs ~30 actual estimado)
- **Conexiones BD:** Sin conexiones ociosas de SSE, sin sesiones bloqueantes

---

## PASO 2: MAPEO DEL ESPACIO DE SOLUCIONES

### Dimensiones de Trade-off en Rendimiento

```
CONSOLIDAR QUERIES ──────────────────────────── CACHEAR RESULTADOS
  (menos viajes a BD, datos frescos)    (menos carga BD, datos pueden ser stale)

POLLING BARATO ──────────────────────────────── PUSH REACTIVO
  (ETag, polling condicional, simple)   (LISTEN/NOTIFY, complejo, instantáneo)

CACHÉ EN APLICACIÓN ─────────────────────────── CACHÉ EN BD
  (APCu, volátil, rápido)               (materialized views, persistente)

OPTIMIZAR BACKEND ───────────────────────────── OPTIMIZAR FRONTEND
  (queries, índices, caching)           (assets, lazy loading, preconnect)

MINIFICAR MANUAL ────────────────────────────── PIPELINE DE BUILD
  (sin dependencias, propenso a error)   (sass/terser, requiere Node.js)

CAMBIOS GRADUALES ───────────────────────────── CAMBIOS AGRESIVOS
  (bajo riesgo, impacto parcial)        (alto riesgo, alto impacto)
```

### Ejes Críticos para OTI

| Decisión | Opción A | Opción B |
|----------|----------|----------|
| Polling stats.php | Unificar en SSE único + ETag | Reemplazar con LISTEN/NOTIFY |
| N+1 Location | Cachear resultados en APCu | Re-escribir con CTE recursivo SQL |
| analisis.php 10 queries | Cachear queries pesadas (30s TTL) | Consolidar en 2-3 queries consolidadas |
| Assets 178KB | Minificar archivos actuales | Build pipeline (sass + terser) |
| Chart.js 1.2MB | Lazy load condicional | Reemplazar con SVG server-side (php-svg) |
| SSE bucle infinito | Agregar heartbeat + reconexión | Migrar a LISTEN/NOTIFY |

---

## PASO 3: GENERACIÓN DE 6 ENFOQUES

---

### ENFOQUE 1: «Consolidación de Queries + Estrategia de Índices» (ALTA PROBABILIDAD)

**Nombre:** Menos Viajes, Más Datos — Una Query Donde Hoy Hay Cinco  
**Probabilidad:** 0.92  
**Complejidad:** BAJA  

#### Resumen
Reescribir los 6 patrones N+1 identificados usando `COUNT(*) FILTER(WHERE...)`, CTEs recursivas, y consultas de barrido único, complementado con 8 índices compuestos en PostgreSQL que cubren los filtros más frecuentes, sin cambiar la lógica de negocio ni la estructura de tablas.

#### Descripción Detallada
El cuello de botella #1 del sistema son las queries individuales ejecutadas en loops y las estadísticas fragmentadas en 5+ consultas separadas. Este enfoque aborda cada caso de forma quirúrgica:

**Stats.php (11 queries admin → 2 queries):** Se reemplazan los 5 COUNTs individuales (stats generales, prioridades, estados, etc.) por una sola query con `COUNT(*) FILTER (WHERE status_id = :abierto)` y `JSON_BUILD_OBJECT()` para prioridades. Las queries de "actividad reciente", "top usuarios" y "tickets por mes" se consolidan en una segunda query usando `DATE_TRUNC('month', created_at)` con `GROUP BY ROLLUP`. Esto reduce de 11 queries a 2 sin perder información.

**Location::getPath() (N+1 → 0):** Se reemplaza el bucle `while($currentId) { findById($currentId) }` por una sola CTE recursiva:
```sql
WITH RECURSIVE path AS (
  SELECT id, name, parent_id, 1 as depth FROM locations WHERE id = :start
  UNION ALL
  SELECT l.id, l.name, l.parent_id, p.depth + 1
  FROM locations l JOIN path p ON l.id = p.parent_id
)
SELECT * FROM path ORDER BY depth DESC;
```

**analisis.php (10 queries → 3 queries):** Se consolidan stats, prioridades, estados, servicios y ubicaciones en 2 queries agrupadas (una para métricas globales, otra para distribuciones). La query de "30 días" y "6 meses" se unifican con `WHERE created_at >= NOW() - INTERVAL '6 months'` y se agrupa por mes. Las queries de "top usuarios" y "resolución" se fusionan con `RANK() OVER (ORDER BY COUNT(*) DESC)`.

**Índices compuestos:** Se agregan 8 índices que cubren los filtros exactos de las queries existentes:
- `oti.tickets (status_id, created_at DESC)` — stats, dashboard
- `oti.tickets (user_id, created_at DESC)` — tickets por usuario
- `oti.tickets (assigned_to, status_id)` — tickets asignados
- `oti.equipment (location_id, status)` — equipos por ubicación
- `oti.locations (parent_id, type)` — jerarquía de locations
- `oti.ticket_activity (ticket_id, created_at DESC)` — actividad reciente
- `oti.systems (slug)` — ya usado en subquery de User
- `oti.ticket_statuses (id)` — ya tiene PK pero se confirma cobertura

#### Decisiones Clave de Diseño
- **No tocar Models, solo reescribir métodos específicos:** `Ticket::getStats()` se reemplaza internamente; los llamadores no cambian
- **CTE recursiva en Location::getPath()** en lugar de APCu: la jerarquía cambia poco, pero la CTE es más segura que un cache que puede quedar stale
- **ROLLUP en lugar de 6 queries separadas:** `GROUP BY ROLLUP(status_id, priority_id)` devuelve subtotales en filas adicionales que se procesan en PHP
- **Versión de contingencia:** Si `FILTER` no funciona por versión de PostgreSQL, usar `SUM(CASE WHEN ... THEN 1 ELSE 0 END)` como fallback

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Reducción de ~70% en queries totales | CTE recursiva añade complejidad SQL |
| Sin cambios en llamadores (interfaz intacta) | FILTER no es estándar SQL (solo PG > 9.4) |
| 8 índices no afectan writes significativamente | ROLLUP requiere procesar filas extra en PHP |
| Resultado inmediato (días de implementación) | No soluciona tormenta de polling (aunque reduce carga por query) |

#### Riesgos Potenciales
- **CTE recursiva infinita:** si hay un ciclo en parent_id, la CTE nunca termina → mitigación: límite `WHERE depth < 10`
- **ROLLUP mal interpretado:** PHP debe procesar filas con NULLs en columnas de agrupación → mitigación: `COALESCE(status_id, -1)` para distinguir subtotales
- **Índices nuevos ralentizan INSERT/UPDATE en tablas con mucho write** → mitigación: monitorear `pg_stat_user_indexes` primera semana
- **FILTER no disponible en PG < 9.4:** mitigación trivial con CASE

---

### ENFOQUE 2: «Despollution — Unificar y Domesticar el Polling Salvaje» (ALTA PROBABILIDAD)

**Nombre:** Tres Fuentes, Un Solo Río — Eliminar la Tormenta de Polling  
**Probabilidad:** 0.88  
**Complejidad:** MEDIA  

#### Resumen
Eliminar 2 de las 3 fuentes de polling concurrentes (realtime.js cada 15s, analisis-charts.js cada 10s, SSE cada 5s) dejando un único mecanismo SSE optimizado con ETag condicional, reduciendo las llamadas a stats.php de ~1,320 queries/minuto a ~120 queries/minuto.

#### Descripción Detallada
El sistema actual tiene tres mecanismos independientes que consultan stats.php y sse.php: (1) `realtime.js` con `setInterval` cada 15 segundos (línea 53); (2) `analisis-charts.js` con `setInterval` cada 10 segundos (línea 104); (3) `sse.php` con `while(true)` y `sleep(5)` ejecutando getStats() completo cada 5 segundos. Con 20 administradores conectados, esto genera ~1,320 queries por minuto en stats.php y ~600 queries por minuto en sse.php.

Este enfoque propone:

**Eliminar realtime.js polling:** El SSE existente ya entrega actualizaciones en tiempo real. Se modifica `sse.php` para que emita eventos específicos (`message: stats_update`, `message: ticket_update`, `message: notification`) en lugar de enviar todo a todos. `realtime.js` se simplifica para ser solo un cliente SSE que escucha eventos y actualiza el DOM. El `setInterval` de 15s se elimina completamente.

**Eliminar analisis-charts.js polling:** La página de análisis carga datos históricos que cambian con poca frecuencia. En lugar de poll cada 10s, los datos se cargan una vez al renderizar la página y se actualizan solo cuando el usuario recarga o navega de vuelta. Si se desea actualización en vivo, se reutiliza el SSE pero con eventos específicos (`message: analysis_update`) que solo se emiten cuando hay cambios en datos de análisis.

**Optimizar SSE con ETag:** `sse.php` calcula un hash MD5 del último timestamp de cambios (de una tabla `oti.change_log` o `MAX(updated_at)` de tickets/equipment). En cada ciclo de 5s, primero verifica si hay cambios desde el último ETag. Si no hay cambios, envía un comentario SSE (`: keepalive\n\n`) sin ejecutar queries. Esto reduce las ejecuciones de getStats() de 12/minuto a <1/minuto cuando no hay actividad.

**Reestructurar sse.php:** En lugar de un solo archivo que ejecuta getStats() completo para todos, se crea un canal SSE por tipo de evento:
- `/api/sse?channel=stats` — solo stats (dashboard)
- `/api/sse?channel=tickets` — solo cambios en tickets (páginas de tickets)
- `/api/sse?channel=notifications` — solo notificaciones de usuario

Cada canal ejecuta solo las queries necesarias para ese tipo de datos, reduciendo la carga por conexión SSE.

#### Decisiones Clave de Diseño
- **Unificación vía EventSource nativo:** No se agregan librerías; el `EventSource` API del navegador ya soporta eventos nombrados con `event:` y `data:` en SSE
- **Tabla `change_log` ligera:** Tabla con `id`, `channel (text)`, `changed_at (timestamp)` que se actualiza vía triggers en tablas relevantes. SSE consulta solo `SELECT MAX(changed_at) FROM change_log WHERE channel = :channel`
- **Heartbeat sin queries:** Si no hay cambios, SSE envía `: keepalive\n\n` (comentario SSE, ignorado por el cliente) que mantiene la conexión viva sin ejecutar SQL
- **Timeout de conexión SSE:** Apache/PHP-FPM tienen timeout; se implementa reconexión automática del lado del cliente con `retry: 3000` en el evento SSE
- **analisis-charts.js se ejecuta una vez:** `document.addEventListener('DOMContentLoaded', () => { loadCharts(); })` sin setInterval. Si se desea actualización, se escucha el canal `stats` del SSE central

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Elimina ~90% de queries de polling | Cambios en realtime.js y analisis-charts.js (riesgo JS) |
| SSE unificado y eficiente | Tabla change_log requiere triggers en BD |
| Chart.js solo carga datos una vez | Análisis no se actualiza en vivo (aceptable) |
| Menos conexiones HTTP concurrentes | SSE channel routing añade complejidad al servidor |

#### Riesgos Potenciales
- **EventSource cierra conexión después de ~30s sin datos:** los heartbeats cada 5s lo evitan, pero si el servidor se congela, la reconexión automática reintenta
- **change_log desincronizado:** si un trigger falla, SSE cree que no hay cambios → mitigación: respaldo con MAX(updated_at) de tablas reales
- **Firewall corporativo bloquea SSE:** algunos proxies no entienden `text/event-stream` → mitigación: fallback a polling cada 30s detectado por `EventSource.onerror`
- **Cambios en realtime.js pueden romper funcionalidad existente:** las actualizaciones de DOM ahora vía SSE en lugar de setInterval → pruebas extensivas de integración

---

### ENFOQUE 3: «Caché en Tres Capas — APCu + HTTP + PostgreSQL Materializado» (ALTA PROBABILIDAD)

**Nombre:** Nunca Preguntes Dos Veces — Caché Agresivo de Todo lo Consultivo  
**Probabilidad:** 0.85  
**Complejidad:** MEDIA-ALTA  

#### Resumen
Implementar tres niveles de caché ortogonales: (1) APCu para datos maestros (statuses, prioridades, tipos) y resultados de queries pesadas; (2) HTTP Cache con ETag/Last-Modified para APIs de polling; (3) Materialized Views en PostgreSQL para reportes de análisis que no requieren frescura en tiempo real. Cada nivel tiene TTL escalonado y fallback automático.

#### Descripción Detallada
El sistema OTI no cachea absolutamente nada. Cada request a stats.php, cada carga de analisis.php, cada iteración de SSE ejecuta queries SQL completas contra PostgreSQL. Dado que muchos datos cambian con baja frecuencia (tipos de ticket, estados, prioridades, jerarquía de ubicaciones), la oportunidad de caching es enorme.

**Nivel 1 — APCu (Aplicación):** Datos maestros que cambian en escala de horas/días se cachean con TTL largo (300-3600s):
- `ticket_statuses`, `priorities`, `service_types`, `systems` — TTL 3600s
- `locations` (árbol completo) — TTL 600s
- Resultados de `getStats()` para dashboard — TTL 30s
- Resultados de queries de analisis.php — TTL 60s

Implementación: wrapper `App\Cache\APCuStore` con métodos `remember($key, $ttl, $callback)` que verifica `apcu_exists()`, retorna cache o ejecuta callback y almacena resultado.

**Nivel 2 — HTTP Cache (ETag/If-None-Match):** Para endpoints de polling (stats.php, sse.php, notifications.php), se implementa ETag basado en el último cambio relevante:
- stats.php: calcula hash de `MAX(updated_at)` de tickets + equipment
- sse.php: usa change_log (enfoque 2) para determinar si hay datos nuevos
- PHP retorna `header('ETag: "' . md5($lastModified) . '"')` y verifica `$_SERVER['HTTP_IF_NONE_MATCH']`
- Si coincide, retorna `304 Not Modified` sin ejecutar queries ni serializar JSON
- El frontend JS agrega `headers: { 'If-None-Match': etag }` en fetch calls

**Nivel 3 — PostgreSQL Materialized Views:** Para los reportes pesados de analisis.php (tickets por mes, top usuarios, distribución por ubicación) que consumen 10 queries en carga de página:
```sql
CREATE MATERIALIZED VIEW oti.analytics_dashboard AS
SELECT
  DATE_TRUNC('month', t.created_at) as month,
  COUNT(*) as total,
  COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
  COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
  -- ...
FROM oti.tickets t GROUP BY 1 ORDER BY 1 DESC;
```
Se refresca cada 5 minutos con `REFRESH MATERIALIZED VIEW CONCURRENTLY` (disponible en PG 16, requiere índice único). El PHP consulta la MV en lugar de las tablas base.

#### Decisiones Clave de Diseño
- **APCu primero, Redis como upgrade:** APCu está disponible si PHP tiene la extensión; no requiere servicio externo. Si se necesita persistencia entre reinicios de PHP-FPM, migrar a Redis con misma interfaz
- **ETag débil vs fuerte:** Se usa ETag fuerte (basado en contenido) para stats.php; ETag débil (basado en timestamp) para sse.php
- **MV refrescada vía pg_cron o script PHP:** Se crea `app/Console/RefreshAnalytics.php` que ejecuta `REFRESH MATERIALIZED VIEW CONCURRENTLY analytics_dashboard` y se programa como cron job cada 5 minutos
- **Invalidación manual:** Se agrega botón "Actualizar datos" en dashboard que fuerza cache bust vía `?nocache=` + timestamp
- **Fallback universal:** Cada llamada cacheada verifica `function_exists('apcu_store')` o `extension_loaded('apcu')`; si falla, ejecuta query normalmente

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Datos maestros se cachean para siempre | APCu se pierde al reiniciar PHP-FPM |
| ETag elimina payloads de respuesta 90% del tiempo | ETag requiere modificar cada endpoint API |
| MV hace que analisis.php cargue en <50ms | MV se refresca cada 5 min — datos no frescos |
| Sistema tolerante a fallos de caché | Complejidad de 3 niveles puede confundir debug |

#### Riesgos Potenciales
- **APCu size limit:** Por defecto 32MB; si se cachean demasiadas cosas, se evictan las menos usadas → mitigación: monitorear `apcu_cache_info()` y ajustar `apc.shm_size`
- **ETag mal implementado:** Si el hash no cambia cuando los datos cambian, el frontend nunca actualiza → mitigación: incluir `COUNT(*)` en el hash además del timestamp
- **MV con datos viejos:** Si un ticket se cierra y la MV no se ha refrescado, el dashboard muestra datos incorrectos → mitigación: el botón "Actualizar datos" fuerza refresh inmediato
- **Concurrent refresh bloquea lecturas:** `REFRESH MATERIALIZED VIEW CONCURRENTLY` evita el bloqueo pero requiere más CPU y un índice único → mitigación: programar refresh en horarios de baja actividad

---

### ENFOQUE 4: «PostgreSQL Listen/Notify + PHP Proceso Dedicado» (BAJA PROBABILIDAD)

**Nombre:** Adiós al Polling — SSE Reactivo con Notificaciones de Base de Datos  
**Probabilidad:** 0.09  
**Complejidad:** ALTA  

#### Resumen
Reemplazar completamente el bucle `while(true)` con `sleep(5)` de sse.php por un proceso PHP persistente que usa `pg_get_notify()` para recibir eventos PostgreSQL LISTEN/NOTIFY en tiempo real, eliminando toda query periódica y conexión ociosa. El proceso se comunica con Apache vía archivo temporal o Redis pub/sub.

#### Descripción Detallada
El SSE actual mantiene una conexión PostgreSQL persistente en un bucle infinito que ejecuta `getStats()` completo cada 5 segundos (líneas 166-186). Esto consume una conexión de BD por cada administrador conectado y ejecuta ~600 queries/minuto. PostgreSQL 16 tiene un mecanismo nativo de notificaciones asíncronas: `LISTEN`/`NOTIFY`.

La arquitectura propuesta:

**Proceso Worker Dedicado (`sse_worker.php`):** Un script PHP independiente que se ejecuta como proceso hijo (o usando `pcntl_fork`) que:
1. Se conecta a PostgreSQL y ejecuta `LISTEN oti_updates`
2. Entra en un bucle `while(true)` con `pg_get_notify($conn, PGSQL_ASSOC)`
3. Cuando recibe una notificación, escribe el payload (JSON con tipo de cambio y IDs) en un archivo FIFO o en Redis pub/sub
4. Tiene un timeout de 30s: si no recibe notificaciones, se reconecta a PostgreSQL (keepalive)
5. Solo un proceso worker para todo el sistema (no uno por admin)

**Conexión SSE Ligera (`sse.php`):** Se transforma en un script que:
1. No se conecta a PostgreSQL en absoluto
2. Lee del archivo FIFO o suscribe a Redis pub/sub
3. Cuando hay datos nuevos, los transforma al formato SSE y los envía a todos los clientes conectados
4. Si no hay datos en 5s, envía comentario keepalive
5. No ejecuta NINGUNA query SQL en su bucle principal

**Triggers PostgreSQL:** Se crean triggers en las tablas `oti.tickets`, `oti.equipment`, `oti.ticket_activity` que ejecutan `pg_notify('oti_updates', payload_json)` cuando hay INSERT/UPDATE/DELETE:
```sql
CREATE OR REPLACE FUNCTION notify_ticket_change()
RETURNS trigger AS $$
BEGIN
  PERFORM pg_notify('oti_updates', json_build_object(
    'type', 'ticket',
    'action', TG_OP,
    'id', NEW.id,
    'changed_at', NOW()
  )::text);
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_ticket_notify
AFTER INSERT OR UPDATE ON oti.tickets
FOR EACH ROW EXECUTE FUNCTION notify_ticket_change();
```

**Arquitectura de entrega:** Para servidores compartidos sin Redis, se usa un archivo FIFO (named pipe) en `/tmp/oti_sse.fifo` que el worker escribe y sse.php lee. Redis es la opción preferida si está disponible.

#### Decisiones Clave de Diseño
- **Worker único + FIFO vs. conexión por admin:** Se evita el problema de N conexiones PostgreSQL (una por admin conectado)
- **Triggers en BD vs. lógica de aplicación:** Los triggers aseguran que ninguna operación (incluso hecha desde consola SQL directa) escape las notificaciones
- **Payload mínimo:** El trigger solo envía tipo, acción e ID; el worker decide qué datos enviar a los clientes basado en suscripciones
- **pg_get_notify con timeout:** `pg_get_notify($conn, PGSQL_ASSOC)` retorna NULL si no hay notificaciones en el timeout; se usa `pg_socket()` + `stream_select()` para timeout de 5s sin bloquear
- **Reconexión automática:** Si la conexión PostgreSQL se cae, el worker espera 1s y reintenta con `pg_ping()` o reconexión completa

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| CERO queries SQL en el bucle SSE | Complejidad de proceso worker separado |
| Una sola conexión BD para todo el SSE | Dependencia de características PostgreSQL (LISTEN/NOTIFY) |
| Datos en tiempo real (sub-segundo) | Redis o FIFO necesario para comunicación inter-proceso |
| Escalable a cientos de administradores | Triggers en BD — otro lugar donde pueden fallar |
| Sin polling, sin ETag, sin caché | Depuración más difícil (procesos separados) |

#### Riesgos Potenciales
- **Worker muere sin supervisión:** Si el proceso worker falla, el SSE deja de enviar actualizaciones silenciosamente → mitigación: script supervisor que monitorea y reinicia (o systemd unit)
- **FIFO lleno:** Si el worker produce datos más rápido de lo que sse.php los consume, el FIFO se llena y el worker se bloquea → mitigación: buffer circular con tamaño máximo o Redis pub/sub (sin límite real)
- **Trigger overhead:** Cada INSERT/UPDATE ejecuta una función PL/pgSQL adicional → mitigación: benchmark con `pg_stat_statements` para medir overhead (esperado <0.1ms)
- **pg_get_notify pérdida de eventos:** Si el worker está ocupado procesando un evento, puede perder notificaciones intermedias → mitigación: procesar eventos en cola (pg_get_notify devuelve el más reciente o se usan todas las pendientes con `while(...)`)
- **Hosting compartido sin acceso a procesos persistentes:** En algunos entornos compartidos no se puede ejecutar un worker PHP persistente → mitigación: el enfoque cae a polling condicional (enfoque 2)

---

### ENFOQUE 5: «Assets Ninja — Pipeline de Build + Lazy Loading + SVG Server-Side» (BAJA PROBABILIDAD)

**Nombre:** Frontend Ultrarrápido — 20KB de CSS, 15KB de JS, Sin Chart.js en CDN  
**Probabilidad:** 0.07  
**Complejidad:** ALTA  

#### Resumen
Aplicar un pipeline de build agresivo (sass + terser + purgecss) que reduce CSS de 178KB a ~20KB y JS de 50KB a ~15KB; reemplazar Chart.js (1.2MB CDN) por gráficos SVG generados en servidor con `php-svg`; implementar preload crítico, lazy loading no crítico, y font-display:swap para Google Fonts. Esto reduce el tiempo de carga inicial de ~3s a ~500ms.

#### Descripción Detallada
El frontend actual carga 178KB de CSS (app.css + fontawesome.min.css + login.css), 50KB de JS (realtime.js + analisis-charts.js + search.js), 1.2MB de Chart.js CDN, y Google Fonts blocking. Todo esto en el `<head>` sin diferimiento.

**Pipeline de Build (Node.js + PHP script):** Se crea `build.js` (o `build.php` como alternativa sin Node) que:
1. **PurgeCSS:** Escanea archivos PHP en `app/Views/` y elimina clases CSS no usadas de app.css. Fontawesome.min.css se reemplaza por SVGs inline (el sistema ya usa SVGs inline mayoritariamente, pero el CSS de Font Awesome permanece).
2. **Sass/PostCSS:** Convierte app.css a app.min.css con autoprefixer, minificación y consolidación de media queries duplicadas
3. **Terser/UglifyJS:** Minifica realtime.js, analisis-charts.js, search.js en un solo archivo `app.min.js` (aprovechando namespaces existentes para evitar colisiones)
4. **Versionado por hash:** `app.min.css?v=abc123` y `app.min.js?v=abc123` para cache bust

**Reemplazo de Chart.js por SVG Server-Side:** Chart.js aporta 1.2MB de JS que se carga en analisis.php (línea 103). Se implementa un generador de gráficos SVG en PHP puro:
- `App\Services\ChartService` con métodos `pieChart($data, $options)`, `barChart($data, $options)`, `lineChart($data, $options)`
- Usa elementos SVG nativos (`<svg>`, `<rect>`, `<circle>`, `<path>`, `<text>`) calculados en PHP
- Los gráficos se generan en el servidor y se insertan como HTML inline (SVG en el DOM)
- Los datos se pasan desde PHP a la vista sin necesidad de fetch JS
- Tamaño: ~5KB de código PHP vs 1.2MB de Chart.js CDN

**Estrategia de Carga:**
- **Critical CSS inline:** Las primeras ~5KB de CSS (layout de cabecera, sidebar) se inyectan directamente en `<head>` via `<style>` tag
- **CSS no crítico diferido:** `app.min.css` se carga con `media="print" onload="this.media='all'"` (técnica de filament group)
- **JS diferido:** `app.min.js` se carga con `defer` (no bloquea render)
- **Google Fonts con preconnect + font-display:swap:** `<link rel="preconnect" href="https://fonts.googleapis.com">` y CSS con `font-display: swap` para evitar FOIT (Flash of Invisible Text)
- **Preload de recursos críticos:** `<link rel="preload" href="/OTI/public/assets/images/logo.svg" as="image">`
- **Skeleton loading:** Se agregan placeholders CSS para cards y tablas mientras cargan datos SSE

#### Decisiones Clave de Diseño
- **SVG server-side vs canvas:** SVG es escalable, accesible (aria-label en gráficos), y no requiere JS para renderizar; es ideal para un sistema municipal que necesita soporte de accesibilidad
- **Build script dual:** Se ofrece `build.sh` (Linux, producción) y `build.bat` (Windows, desarrollo local); ambos producen el mismo output
- **PurgeCSS con whitelist:** Clases CSS generadas dinámicamente (como `.status-1`, `.status-2` en tickets) se agregan a whitelist para que no sean purgadas
- **ChartService con modo degradado:** Si no se pueden generar SVG (por límite de memoria), se renderiza una tabla HTML con los datos (accesible y funcional)
- **Sin dependencias JS de terceros:** Ni Chart.js, ni D3.js, ni Highcharts; todo es SVG PHP nativo

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| CSS de 178KB a ~20KB (89% reducción) | Pipeline de build requiere Node.js |
| JS de 50KB a ~15KB (70% reducción) | Chart.js reemplazado — pierde interactividad (hover, zoom) |
| Elimina 1.2MB de Chart.js CDN | SVG inline aumenta tamaño HTML (pero es compresible) |
| Carga inicial <500ms vs ~3s | Los SVGs no tienen animaciones |
| Accesibilidad mejora (SVGs con aria-label) | Las vistas PHP existentes deben modificarse para pasar datos a ChartService |

#### Riesgos Potenciales
- **PurgeCSS elimina clases usadas dinámicamente:** tabs, modales, y elementos toggle usan clases que PurgeCSS no detecta en archivos PHP estáticos → mitigación: modo safelist con patrones regex
- **SVG server-side es más lento que Chart.js cliente:** el servidor debe generar SVG en cada request → mitigación: cachear el SVG generado en APCu por 5 minutos
- **Critical CSS mal identificado:** si se incluye muy poco, la página se ve mal durante la carga; si se incluye mucho, no hay ahorro → mitigación: tooling como Critical (NPM) o manual con las primeras vistas
- **Build script no se ejecuta en deploy:** assets viejos sin minificar en producción → mitigación: pre-commit hook que corre build y verifica que los archivos `dist/` estén actualizados
- **El equipo municipal no tiene Node.js instalado:** mitigar ofreciendo build.php alternativo que hace lo mismo con PHP puro (menos eficiente pero funcional)

---

### ENFOQUE 6: «Particionamiento Vertical + Balanceo de Lectura/Escritura» (BAJA PROBABILIDAD)

**Nombre:** Separar Aguas — Queries de Escritura a una Conexión, Lecturas a Otra  
**Probabilidad:** 0.04  
**Complejidad:** MUY ALTA  

#### Resumen
Implementar separación de responsabilidades en la capa de base de datos: las queries de lectura (stats, reportes, análisis) se enrutan a una conexión PostgreSQL de solo-lectura (replica o conexión dedicada) con `default_transaction_read_only = on` y configuraciones de caché agresivas; las escrituras (creación de tickets, cambios de estado) van a la conexión principal. Esto elimina la contención de recursos entre las consultas analíticas pesadas y las operaciones transaccionales del día a día.

#### Descripción Detallada
El sistema OTI mezcla en la misma conexión PostgreSQL consultas analíticas pesadas (stats con FILTER, reportes con GROUP BY, CTEs recursivas de Location) con operaciones transaccionales cortas (INSERT ticket, UPDATE status). Esto causa que las consultas pesadas acaparen recursos de CPU/IO y retrasen las operaciones críticas. Además, `sse.php` mantiene conexiones persistentes que compiten con las conexiones del pool de Apache.

**Conexiones separadas:** Se crean dos instancias de `Database`:
- `Database::write()` — conexión principal (read-write) para operaciones CRUD, login, creación de tickets
- `Database::read()` — conexión de solo-lectura (read-only) para stats, dashboard, reportes, SSE, búsquedas

La configuración se define en `.env`:
```
DB_WRITE_HOST=localhost
DB_WRITE_PORT=5432
DB_READ_HOST=localhost
DB_READ_PORT=5433  # mismo servidor, puerto diferente (pgbouncer en modo read-only)
```
En desarrollo, ambas apuntan al mismo servidor pero con `default_transaction_read_only = on` en la conexión de lectura (castigo si se intenta escribir por error).

**Pool de conexiones con PgBouncer:** Se coloca PgBouncer entre PHP y PostgreSQL en dos modos:
- `pgbouncer_write.ini` — Transaction pooling, 10 conexiones, para escrituras
- `pgbouncer_read.ini` — Session pooling, 50 conexiones, para lecturas (estadísticas, reportes, SSE)

Las conexiones de lectura pueden ser muchas (cada admin tiene SSE + dashboard) pero son baratas porque están en modo read-only. Las conexiones de escritura son pocas pero rápidas.

**Enrutamiento a nivel de Model:** Cada método de modelo decide qué conexión usar:
- `Ticket::create()`, `Ticket::update()`, `User::create()` → `Database::write()`
- `Ticket::findById()`, `Ticket::getStats()`, `User::findAll()` → `Database::read()`
- Los métodos que leen y escriben en la misma operación (ej: `changeStatus()` que lee el estado actual y escribe el nuevo) usan `Database::write()` para consistencia

**Query Routing via Proxy (opcional avanzado):** Para sistemas sin PgBouncer, se modifica `Database.php` para aceptar un parámetro `$mode = 'read'|'write'` que selecciona el host/credencial adecuados:
```php
class Database {
  private static array $instances = [];
  public static function connect(string $mode = 'write'): PDO {
    $config = $mode === 'read' ? self::$readConfig : self::$writeConfig;
    // ...
  }
}
```

**Ajustes de PostgreSQL para read-only:**
- `default_transaction_read_only = on` en la conexión de lectura
- `statement_timeout = 30000` (30s) para que queries lentas no acaparen
- `idle_in_transaction_session_timeout = 60000` (60s) para limpiar conexiones olvidadas
- `work_mem = 64MB` en la conexión de lectura (más memoria para queries analíticas)
- `work_mem = 4MB` en la conexión de escritura (conservadora para transacciones cortas)

#### Decisiones Clave de Diseño
- **Dos conexiones en lugar de pooling de Apache:** El pool de Apache ya maneja múltiples procesos PHP; tener dos conexiones por request (una read, una write) duplica el uso de conexiones pero permite optimizar cada una
- **PgBouncer recomendado pero no obligatorio:** El sistema funciona sin PgBouncer (conexiones directas) pero se beneficia del pooling para escalar a >50 admins concurrentes
- **Consistencia eventual para stats:** Las lecturas pueden ver datos ligeramente desactualizados (lag de replicación o transacción en curso) — aceptable para dashboard y reportes
- **Transacciones que leen y escriben:** Usan `Database::write()` para garantizar consistencia monotónica (read-after-write)
- **Monitorización:** `pg_stat_activity` se consulta periódicamente para detectar queries lentas en cada pool

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Queries analíticas no bloquean writes | Complejidad operativa (2 pools de conexiones) |
| Más conexiones disponibles para SSE/reportes | Posible inconsistencia lectura/escritura (lags) |
| Cada conexión optimizada (work_mem, timeout) | Desarrollo más lento (decidir conexión por query) |
| Escalabilidad horizontal (añadir réplicas de lectura) | PgBouncer añade latencia de red (~1ms) |
| session_regenerate_id() puede ir a write, stats a read | Refactor de Database.php para soportar modo dual |

#### Riesgos Potenciales
- **Lag de replicación:** Si se usa una réplica física, los datos pueden tener segundos de atraso → mitigación: las operaciones críticas (ver ticket recién creado) fuerzan `Database::write()`
- **Escritura accidental en conexión read-only:** Si un desarrollador usa `Database::read()` para un INSERT, PostgreSQL lanza error 25006 (cannot execute INSERT in a read-only transaction) → mitigación: esto es deseable (defensa en profundidad)
- **Transacciones que cruzan conexiones:** No se puede hacer un SELECT en read y un INSERT en write dentro de la misma transacción → mitigación: las transacciones que necesitan consistencia total usan `Database::write()` exclusivamente
- **PgBouncer transaction pooling rompe SET statements:** `SET search_path` o `SET work_mem` no persisten entre transacciones → mitigación: usar `SET LOCAL` dentro de la transacción o parámetros en la cadena de conexión
- **Doble conexión por request:** Cada página ahora abre 2 conexiones en lugar de 1 → mitigación: lazy connection — solo abrir la conexión de lectura si se ejecuta una query de lectura

---

## PASO 4: VERIFICACIÓN DE DIVERSIDAD

### Matriz de Diferenciación

| Dimensión | E1: Consolidación | E2: Despollution | E3: Caché 3 Capas | E4: LISTEN/NOTIFY | E5: Assets Ninja | E6: Read/Write Split |
|-----------|:---:|:---:|:---:|:---:|:---:|:---:|
| **Estrategia** | BD | Polling | Cache | Reactivo | Frontend | Conexiones |
| **Riesgo** | Bajo | Medio | Bajo | Alto | Medio-Alto | Muy Alto |
| **Esfuerzo** | 3-5 días | 5-7 días | 5-7 días | 10-15 días | 7-10 días | 10-15 días |
| **Impacto queries** | -70% | -90% | -80% | -100% (SSE) | 0% | -50% (contention) |
| **Impacto frontend** | 0% | Medio | 0% | 0% | Alto | 0% |
| **Dependencias nuevas** | Ninguna | Ninguna | APCu | pg_notify, FIFO/Redis | sass, terser, Node | PgBouncer |
| **Mejora TTFB** | -60% | -40% | -50% | -30% | -80% (carga) | -20% |
| **Reversibilidad** | Inmediata | Inmediata | Inmediata | Media | Inmediata | Baja |

### Cobertura del Espacio de Soluciones

```
                    E5 (Frontend)
                   /
                  /
    E3 (Caché) ──┤
    /            │
   /             E4 (Push Puro)
  │
BD ─── E1 (Consolidación)
  │
   \             E2 (Polling Unificado)
    \            /
     \          /
      E6 (Read/Write Split)
```

Los 6 enfoques cubren regiones ortogonales:
1. **E1 (Consolidación):** Ataque directo a N+1 y queries ineficientes — el low-hanging fruit más obvio
2. **E2 (Despollution):** Ataque a la arquitectura de polling — elimina la fuente de la tormenta
3. **E3 (Caché 3 Capas):** Ataque sistémico — reducir viajes a BD en todos los niveles
4. **E4 (LISTEN/NOTIFY):** Ataque radical al SSE — elimina polling por completo (exploratorio)
5. **E5 (Assets Ninja):** Ataque al frontend — reduce payload de red y tiempo de render (exploratorio en contexto municipal)
6. **E6 (Read/Write Split):** Ataque a la infraestructura BD — elimina contención entre lecturas y escrituras (exploratorio, alta complejidad)

### ¿Son Genuinamente Diferentes?

Sí. Cada enfoque optimiza para una variable distinta:

| Enfoque | Optimiza | Ignora |
|---------|----------|--------|
| E1 | Número de queries por request | Polling, frontend, caché |
| E2 | Frecuencia de polling | Queries individuales, frontend |
| E3 | Latencia de respuesta repetida | Queries N+1, polling, frontend |
| E4 | Eficiencia del SSE | Queries, frontend, caché |
| E5 | Payload de red y tiempo de render | Backend queries, polling |
| E6 | Contención de recursos BD | Frontend, queries específicas |

Ningún enfoque es sustituto de otro; son complementarios. La combinación óptima sería **E1 + E2 + E3 + E5** en ese orden (consolidar queries → eliminar polling → cachear lo que queda → optimizar frontend).

---

*Fin del documento — 6 enfoques de alto nivel para optimización de rendimiento OTI*
