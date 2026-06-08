# Propuesta C: 6 Enfoques para Optimizar Rendimiento OTI

> Fecha: 2026-05-21
> Tipo: Tree of Thoughts — Soluciones de alto nivel (sin implementar)

---

## Descomposición del Problema

El sistema OTI presenta 20+ cuellos de botella en 4 capas:

| Capa | Problema raíz | # Hallazgos |
|------|--------------|-------------|
| Database | Pollling excesivo + N+1 queries + JOINs innecesarios | 11 |
| Frontend | Assets sin minificar + CDN pesado sin lazy | 4 |
| Servidor | Sin caché (APCu, OPcache), sin tuning | 3 |
| UX | Sin feedback visual, carga síncrona | 2 |

**Ecuación de rendimiento:**
```
T_carga = Σ(DB_queries × latency) + Σ(assets_size / throughput) + Σ(blocking_time)
```

**Dimensión crítica:** ~1,320 queries/min solo de polling stats cuando hay 20 admins. Cada query ~2-5ms → 2.6-6.6 seg/min de DB time. A esto se suman los N+1 en Location, los JOINs masivos, y el CSS/JS sin procesar.

---

## Mapeo del Espacio de Soluciones

Dimensiones de diseño:

1. **Cache depth** — Sin caché → APCu parcial → Redis full → Materialized views
2. **Polling strategy** — Polling bruto → Throttling/agregación → SSE eficiente → WebSocket push → Event-driven
3. **Query optimization** — N+1 fix → JOIN reduction → Index tuning → Query rewriting → CQRS
4. **Asset delivery** — Sin procesar → Minify → Bundle → Critical CSS inline → Lazy + Preload → HTTP/2 push
5. **Architecture** — Monolítico actual → Microservices → Read replicas → Sharding → Serverless
6. **Risk tolerance** — Bajo (cambios reversibles) → Medio (refactor parcial) → Alto (rearquitectura)

Trade-off primario: **Latencia vs Consistencia vs Costo de desarrollo**

---

## Enfoques 1-3: Probabilidad > 0.80 (Pragmáticos, Alto Impacto)

---

### Enfoque 1: "Cache First, Polling Last" — Capa de caché APCu + throttling de polling

**Resumen:** Implementar caché en memoria APCu para todas las queries de stats/analytics y reducir la frecuencia de polling en un factor de 6-12x.

**Descripción detallada:**
El cuello de botella #1 (stats.php: 11 queries x 3 fuentes de polling) es responsable de ~1,320 queries/min. La solución es envolver cada función de stats en un patrón de caché con TTL. Las stats generales (conteos, sumarizaciones) se actualizan cada 30-60s, no cada 5s. Las queries de activity reciente se cachean 15s. Las queries de tickets por mes se cachean 300s (5 min). APCu es la opción natural porque está disponible en PHP 8.x sin dependencias externas (a diferencia de Redis).

Paralelamente, se modifica el polling triple: realtime.js pasa de 15s a 30s, SSE se mantiene en 5s pero solo envía heartbeat si stats no cambiaron (bandera "dirty" en APCu), y analisis-charts.js pasa de 10s a 60s. Esto reduce el factor de polling combinado de ~3 queries/seg a ~0.3 queries/seg para 20 admins (~132 queries/min vs ~1,320).

Las queries N+1 de Location (hallazgos #4, #5) se resuelven con un cache tree completo en APCu: getPath() y getById() se cachean con key compuesta "location_path:{id}" y TTL 3600s. El tree de locations es datos de configuración, no transaccionales — cambia rara vez.

**Decisiones clave de diseño:**
- Usar APCu (no Redis) para evitar dependencia externa — zero infraestructura nueva
- TTLs escalonados según volatilidad: stats generales (60s), actividad (15s), históricos (300s)
- Implementar cache stampede protection con lock APCu (apcu_entry() en PHP 8.1+)
- Bandera "dirty" en APCu para SSE: solo ejecutar getStats() si hubo cambio real
- Cache tree de Location con invalidación manual cuando se edita una ubicación

**Trade-offs:**
- **Gana:** Reduce queries de polling ~90%. Elimina N+1 de Location. Sin downtime.
- **Sacrifica:** Stats no son tiempo real estricto (15-60s de lag). Consume ~10-20MB RAM APCu.
- **Impacto estimado:** Carga de DB reduce de 1,320 a ~132 queries/min (-90%). Tiempo de respuesta de stats.php de 80ms a ~5ms.

**Probabilidad:** 0.92
**Complejidad:** Baja
**Riesgos:**
- APCu no disponible en hosting compartido → Mitigación: fallback a file cache o session cache
- Cache stale si no se invalida correctamente → Mitigación: TTL conservador + hook en POST/PUT

---

### Enfoque 2: "Query Slim Down" — Refactor quirúrgico de queries pesadas

**Resumen:** Reemplazar las 7-8 LEFT JOINs en Ticket detail y User queries con consultas optimizadas, índices compuestos GIN/GiST para ILIKE, y eliminación del N+1 en search.php.

**Descripción detallada:**
Las queries más pesadas del sistema son Ticket::findById() con 7 LEFT JOINs (hallazgo #6), User::findByIdentifier() con 5 LEFT JOINs + subquery (hallazgo #7), y search.php con LIKE sin índices (hallazgo #9). El enfoque no es reescribir toda la capa de datos, sino atacar cada query individualmente con objetivos mensurables.

Para Ticket::findById(): reducir de 7 a 4 LEFT JOINs moviendo las uniones a admin.usuarios (que se repiten) a una subquery CTE o a un segundo query simple. Las locations se optimizan con el cache tree del Enfoque 1. Para User::findByIdentifier(): convertir 4 ILIKE con OR en un índice GIN trgm (pg_trgm) que permite búsqueda eficiente con `ILIKE '%texto%'`. Esto transforma un sequential scan en un bitmap index scan.

Para search.php: además del índice GIN trgm, se corrige la tabla incorrecta (admin.roles → admin.usuario_rol). Se reemplaza el positional params con PDO named params para mejor cacheabilidad del plan de ejecución. Se elimina la query duplicada en User::getAll() (hallazgo #8) — si page=1 da vacío, simplemente retornar vacío sin segunda query.

Session::regenerate_id() (hallazgo #10) se mueve a un middleware que solo regenera cada N segundos (configurable, ej. 300s) o solo en cambios de privilegios, no en cada request.

**Decisiones clave de diseño:**
- Índice GIN trgm sobre campos searchables: `CREATE INDEX CONCURRENTLY IF NOT EXISTS ...`
- CTE para consolidar JOINs duplicados en Ticket::findById()
- PDO named params para cache plan cache
- regenerate_id() condicional, no por-request
- Evaluar cada cambio con EXPLAIN ANALYZE antes/después

**Trade-offs:**
- **Gana:** Reducción de 7 JOINs a 4 en queries críticas. Search pasa de sequential scan a index scan. Queries de usuarios 10-50x más rápidas para búsquedas parciales.
- **Sacrifica:** Índices GIN añaden overhead en INSERT/UPDATE (~10-20% más lentas). Código de query más complejo de mantener.

**Probabilidad:** 0.88
**Complejidad:** Media
**Riesgos:**
- Índices GIN pueden crecer mucho en tablas grandes → Mitigación: monitorear tamaño, considerar trigramas solo en campos pequeños
- CTE puede empeorar performance en PostgreSQL si hay row estimation errors → Mitigación: probar con datos reales, tener plan B (query separada)

---

### Enfoque 3: "Frontend Slim" — Compresión de assets + eliminación de bloqueos

**Resumen:** Minificar CSS/JS, eliminar Google Fonts como blocking, lazy-load Chart.js, y agregar preconnect + preload para recursos críticos — todo sin cambiar la arquitectura.

**Descripción detallada:**
El sistema envía ~178 KB de CSS sin minificar (hallazgo #12) y ~50 KB de JS (hallazgo #13) más Chart.js ~1.2 MB (hallazgo #15) y Google Fonts blocking (hallazgo #14). La solución es puramente frontend y no toca lógica de negocio.

Los assets se minifican con un pipeline simple: un script build.php que corre `uglifycss` y `uglifyjs` (o herramientas nativas PHP como `Minify`). El CSS se separa en critical (inline en <head>, < 15 KB) y non-critical (cargado async con media="print" + onload). Google Fonts se carga con `preconnect` + `preload` + `font-display: swap` (no blocking). Chart.js se carga con `defer` desde un CDN con `preconnect` y `preload`, no desde el head blocking.

Para analisis-charts.js y realtime.js: se eliminan console.log/console.error en producción (hallazgo #13) con un simple `if (window.DEBUG)` guard. Se agrega skeleton loading CSS (6KB de CSS animado) para stats cards y tablas — esto no acelera la carga real pero mejora la percepción de velocidad en 200-400ms.

**Decisiones clave de diseño:**
- Critical CSS inline generado automáticamente con un script que parsea el DOM (PhantomJS/Puppeteer no disponibles → solución: critical CSS manual de ~15-20 reglas)
- Preconnect a Google Fonts y CDN de Chart.js
- Chart.js con carga diferida: `document.createElement('script')` después de DOMContentLoaded
- Skeleton loading con CSS puro (no JS) para fade-in suave
- Minificación con https://github.com/matthiasmullie/minify (PHP puro, sin Node)

**Trade-offs:**
- **Gana:** First Contentful Paint de ~2-3s a ~0.5-0.8s. Fully Loaded de ~4-5s a ~1.5-2s. Ahorro de ~200 KB transferidos.
- **Sacrifica:** Mantenimiento de critical CSS manual (cambia al modificar layout). Chart.js lazy puede tener flicker inicial.
- **Impacto:** Percepción de velocidad mejora 2-3x con cambios mínimos en backend.

**Probabilidad:** 0.95
**Complejidad:** Baja
**Riesgos:**
- Critical CSS desactualizado → Mitigación: CI check que compara inline vs full CSS cada deploy
- Sin herramientas Node → usar minify PHP puro (matthiasmullie/minify) que no requiere dependencias externas

---

## Enfoques 4-6: Probabilidad < 0.10 (Exploratorios, Innovadores)

---

### Enfoque 4: "Event-Driven Backend" — Cola de eventos PostgreSQL + triggers NOTIFY para reemplazar polling

**Resumen:** Reemplazar TODO el polling (stats.php, SSE, realtime.js, analisis-charts.js) con un sistema event-driven usando LISTEN/NOTIFY de PostgreSQL y un worker PHP que emite actualizaciones vía Server-Sent Events solo cuando hay cambios reales.

**Descripción detallada:**
Este enfoque elimina por completo el polling de base de datos. En lugar de ejecutar queries cada N segundos, se utilizan triggers PostgreSQL con `NOTIFY` que emiten eventos cuando ocurren cambios en tablas relevantes (tickets, ticket_statuses, locations, usuarios). Un script PHP long-running (proceso separado, supervisado por systemd/supervisor) escucha con `pg_listen()` y recibe notificaciones push desde PostgreSQL.

Cuando el worker recibe un `NOTIFY`, consulta los datos afectados (solo una query específica, no 11) y mantiene un estado en memoria (APCu). Luego el worker tiene 6 conexiones SSE abiertas (una por admin conectado) y envía actualizaciones incrementales. No más queries periódicas. No más sleep(5). No más stats.php.

Esto requiere reescribir el sistema de polling en tiempo real completamente. realtime.js se reduce a una conexión SSE. analisis-charts.js desaparece como polling y se convierte en receptor de eventos. El worker PHP es un proceso demonio que reemplaza sse.php.

Las conexiones SSE se manejan con un event emitter simple en el worker: cuando llega un NOTIFY, se determina qué admins deben recibir el update y se envía el delta. Si un admin cierra sesión, su conexión SSE se cierra.

**Decisiones clave de diseño:**
- PostgreSQL LISTEN/NOTIFY como bus de eventos (sin infraestructura externa como RabbitMQ)
- Worker PHP demonio con `pcntl_fork()` (o proceso único con event loop)
- Estado en memoria compartida (shmop o APCu) para delta tracking
- Conexiones SSE multiplexadas en un solo proceso
- Timeout de conexión SSE de 30s con reconexión automática del cliente

**Trade-offs:**
- **Gana:** Cero queries de polling. Instancias de stats: 0 queries/min (vs 1,320). Tiempo real real (no pseudo-real).
- **Sacrifica:** Complejidad operativa (demonio PHP, supervisor, monitoreo). Conexiones PostgreSQL persistentes compartidas. Depuración más difícil. Requiere cambios en el hosting (permisos para procesos long-running).
- **Riesgo alto:** Si el worker muere, no hay tiempo real hasta que supervisor lo reinicie.

**Probabilidad:** 0.08
**Complejidad:** Muy alta
**Riesgos:**
- pg_listen() bloqueante puede colgar el worker → Mitigación: stream_select() con timeout, heartbeat
- Sin pcntl en Windows → Mitigación: requiere Linux para producción. Alternativa: proceso único con event loop (ReactPHP/amphp)
- NOTIFY no entrega payload en PostgreSQL < 9.3 (payload limitado a 8000 bytes en v9.3+) → Mitigación: usar payload para IDs, no datos completos

---

### Enfoque 5: "CQRS Lite con Materialized Views" — Separación de lectura/escritura en la BD

**Resumen:** Implementar CQRS minimalista usando materialized views de PostgreSQL para todas las consultas de reportes/analytics, con refresco periódico asíncrono mediante pg_cron o triggers diferidos.

**Descripción detallada:**
Este enfoque trata el problema de raíz: las queries de stats y analytics operan sobre el mismo esquema OLTP que recibe inserts/updates constante. Se crean 6-8 materialized views que precomputan todas las sumarizaciones que actualmente hacen 11 queries separadas:

1. `mv_ticket_stats` — conteos por estado, prioridad, equipo (reemplaza queries #1-#4 de stats.php)
2. `mv_activity_summary` — actividad reciente agregada (reemplaza query #7)
3. `mv_monthly_tickets` — tickets por mes (reemplaza query #11)
4. `mv_user_productivity` — top usuarios (reemplaza query #9)
5. `mv_location_tree` — árbol jerárquico pre-join (reemplaza Location N+1)
6. `mv_team_stats` — equipos por tipo (reemplaza query #10)

Las materialized views se refrescan con `REFRESH MATERIALIZED VIEW CONCURRENTLY` (requiere unique index) cada 30-60s mediante pg_cron (extensión PostgreSQL) o un cron job PHP que corre un script refresh_mv.php.

Stats.php se reescribe para leer de las materialized views en lugar de las tablas base. Una sola query (`SELECT * FROM mv_ticket_stats`) reemplaza 6-8 queries individuales. Para datos que necesitan frescura inmediata (ticket individual, no stats), se lee de tablas base directamente — pero optimizadas con los índices del Enfoque 2.

Para Location, la materialized view `mv_location_tree` contiene el árbol completo pre-join con niveles jerárquicos como columnas (nivel1, nivel2, nivel3), eliminando completamente las queries recursivas y el N+1 de getPath().

**Decisiones clave de diseño:**
- REFRESH MATERIALIZED VIEW CONCURRENTLY (evita table locks)
- pg_cron si está disponible, sino cron PHP simple
- Materialized views específicas para dashboards, no para queries transaccionales
- Frecuencia de refresco: 30s para stats activas, 300s para históricas
- Índices únicos en cada MV para permitir CONCURRENTLY

**Trade-offs:**
- **Gana:** Stats se sirven en 1 query en lugar de 11. Location en 0 queries (cache tree). Refrescabilidad configurable por vista.
- **Sacrifica:** Datos de stats son siempre "eventually consistent" (hasta 60s de lag). Cada MV ocupa espacio (~5-20 MB cada una). Refresco CONCURRENTLY es ~2-3x más lento que refresco normal.
- **Breakthrough:** Si las MVs se refrescan en < 100ms en horario pico, este enfoque solo + caching simple eliminaría ~95% de la carga de DB de stats.

**Probabilidad:** 0.07
**Complejidad:** Alta
**Riesgos:**
- REFRESH CONCURRENTLY falla si no hay unique index → Mitigación: agregar index en cada MV
- pg_cron extensión puede no estar instalada → Mitigación: cron PHP como fallback
- MV con datos obsoletos causan malas decisiones → Mitigación: TTL visible en UI ("última actualización: hace X segundos")

---

### Enfoque 6: "Zero Trust Polling — Hydration progresiva con Service Worker + API Gateway de caché"

**Resumen:** Implementar un Service Worker que intercepta todas las llamadas de polling y las sirve desde Cache API con estrategia stale-while-revalidate, combinado con un micro API Gateway PHP que aplica caching condicional (ETag/Last-Modified) en el servidor, reduciendo el tráfico a la BD a solo requests que realmente tienen datos nuevos.

**Descripción detallada:**
Este enfoque es radicalmente diferente porque mueve la lógica de reducción de polling al cliente y al edge de la aplicación, no a la BD. En lugar de optimizar queries, se optimiza cuándo se ejecutan.

El Service Worker (SW) se registra al cargar el sistema por primera vez. El SW intercepta fetch() a /api/stats.php, /api/tickets.php, y /api/search.php. Usa Cache API con estrategia stale-while-revalidate: primero responde con datos cacheados (instantáneo), luego va a la red, y si hay cambios, actualiza el cache y emite un evento `controllerchange` que actualiza la UI.

En el servidor, se implementa un mini API Gateway (un archivo api-gateway.php que rutas mediante mod_rewrite) que:
1. Calcula un hash ETag basado en un checksum rápido de la tabla relevante (ej. `SELECT COUNT(*) + MAX(updated_at) FROM tickets` — query de 0.5ms)
2. Responde con 304 Not Modified si el ETag coincide con el request header
3. Solo ejecuta la query completa si el ETag cambió

Esto significa que SSE y realtime.js ya no necesitan ejecutar queries cada 5-15s. El SW cachea stats.php por 30s. Si el admin hace polling, el SW responde instantáneo del cache. En background, verifica el ETag. Si el ETag no cambió, el gateway responde 304 (sin body), y el SW no actualiza nada. Si el ETag cambió, se ejecuta la query completa (una vez) y el SW actualiza el cache.

Combinación: Cache API (SW) + 304 (Gateway) = ~95% de requests nunca tocan la BD. El 5% restante son cambios reales.

Para Location N+1, el SW cachea la respuesta de /api/locations.php por 1 hora (datos casi estáticos).

**Decisiones clave de diseño:**
- Service Worker con scope restringido a /api/ y /public/
- ETag basado en row hash de tabla principal (tickets)
- Estrategia stale-while-revalidate con timeout de 30s para stats, 1h para locations
- API Gateway liviano (~50 líneas) que wrappea las APIs existentes
- Fallback: si SW no soportado (IE/old browsers), funciona sin cache (pero con ETag del gateway)
- Nomad pattern: el SW se actualiza automáticamente con `skipWaiting()` + `clients.claim()`

**Trade-offs:**
- **Gana:** ~95% de requests de polling nunca tocan BD. Stats "instantáneos" desde Cache API (< 1ms). Sin cambios en BD. Sin nuevas dependencias.
- **Sacrifica:** Service Worker requiere HTTPS (o localhost). No funciona en Safari Private Mode ni IE. La lógica de caché distribuida (SW + Gateway) es más compleja de depurar. Política de cache eviction manual.
- **Breakthrough:** Si funciona, elimina el problema de polling completamente desde el cliente, sin tocar la BD, sin cambiar queries, sin APCu, sin workers.

**Probabilidad:** 0.04
**Complejidad:** Alta
**Riesgos:**
- HTTPS requirement para SW → Mitigación: en desarrollo localhost funciona, en producción debe tener HTTPS
- Cache invalidation compleja con múltiples admins → Mitigación: ETag por request + postMessage() del SW a todas las pestañas
- Service Worker puede cachear datos sensibles → Mitigación: SW solo cachea stats, no datos de ticket individual
- Estrategia mixta (SW + 304) puede duplicar lógica de caché → Mitigación: el SW es el orquestador, el gateway es dumb

---

## Verificación de Diversidad

| Dimensión | E1 (Cache APCu) | E2 (Query Slim) | E3 (Frontend) | E4 (Event-Driven) | E5 (CQRS + MV) | E6 (SW + Gateway) |
|-----------|:---:|:---:|:---:|:---:|:---:|:---:|
| Capa principal | Backend/BD | BD | Frontend | Infraestructura | BD/Arquitectura | Cliente/Edge |
| Enfoque | Cache | Query opt. | Asset opt. | Arquitectura | DB schema | Client caching |
| Riesgo | Muy bajo | Bajo | Muy bajo | Alto | Alto | Alto |
| Dependencias | APCu | pg_trgm | PHP puro | supervisor | pg_cron | SW API |
| Tiempo | 1-2 días | 2-3 días | 1 día | 2-3 semanas | 1-2 semanas | 1-2 semanas |
| DB impact | -90% | -40% | 0% | -99% | -95% | -95% |
| Percepción | Media | Baja | Alta | Alta | Baja | Alta |

**Conclusión de diversidad:** Los 6 enfoques cubren 4 capas diferentes (BD, Backend, Frontend, Infraestructura, Cliente), 3 estrategias radicalmente distintas (caché, optimización, reemplazo de paradigma), y operan en regiones de riesgo completamente diferentes del espacio de soluciones. No hay solapamiento significativo entre enfoques — E1 y E2 son complementarios pero operan en dimensiones distintas (TTL de caché vs índices/queries). E3 es ortogonal (frontend puro). E4-E6 son mutuamente excluyentes entre sí pero podrían combinarse con E1-E3.
