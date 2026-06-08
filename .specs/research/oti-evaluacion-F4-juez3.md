# Evaluación Fase 4 — Juez 3 (PostgreSQL / Backend Performance)

**Fecha:** 2026-05-21
**Perfil:** Experto en PostgreSQL, optimización de queries, eficiencia de backend y reducción de carga en BD.

## Resumen de Puntuaciones

| Solución | Cobertura(20) | Código(20) | Completitud(15) | Impacto(15) | Integrabilidad(15) | Plan(10) | Riesgos(5) | Total |
|----------|:------------:|:----------:|:---------------:|:-----------:|:------------------:|:--------:|:----------:|:-----:|
| **C-E1** (APCu + Throttling) | 55 | 90 | 90 | 90 | 75 | 85 | 95 | **80.50** |
| **A-E1** (Unificación + ETag) | 45 | 85 | 85 | 88 | 80 | 88 | 70 | **76.25** |
| **B-E1** (Consolidación + Índices) | 72 | 82 | 70 | 65 | 88 | 85 | 55 | **75.50** |

---

## Evaluación Detallada

### 1. Solución C-E1 — Cache First, Polling Last (APCu + Throttling) — **80.50 pts**

#### Cobertura de Cuellos de Botella (55/100)
Resuelve ~55% de los problemas identificados. Su fortaleza es que ataca los puntos de mayor impacto: elimina el 100% de queries en cache hit para stats.php, SSE, analisis.php y location tree. Sin embargo, no toca problemas estructurales como session_regenerate_id() incondicional, User::getAll fallback duplicado, ausencia de índices compuestos, ni la consolidación de queries individuales — los envuelve en caché en lugar de resolverlos. La estrategia "cachear todo" es pragmática y efectiva, pero deja los problemas de base sin corregir.

#### Calidad del Código Propuesto (90/100)
Código sintácticamente correcto, completo, sin pseudo-código ni secciones marcadas con `...`. El wrapper `App\Cache\Store` es robusto: incluye detección de disponibilidad de APCu (`isAvailable()`), protección contra cache stampede con `apcu_entry()` (PHP 8.1+) y un fallback manual con semáforo (`apcu_add` lock + `usleep`). El manejo de errores es exhaustivo: try/catch en SSE loop con error_log, degradación graceful si APCu no está disponible. Las closures de cache tienen early returns. Punto menor: la clave de cache para stats con filtros usa `md5(json_encode($filters))` — funcional pero podría colisionar si el orden de filtros cambia (mitigado con `ksort`).

#### Completitud de la Solución (90/100)
Cubre todos los frentes: backend PHP completo (Store wrapper, modificaciones en 4 modelos), frontend JS (realtime.js, analisis-charts.js), migración SQL opcional para dirty flags vía BD, estrategia de pruebas con 7 pruebas detalladas (T1-T7) con criterios de éxito cuantificables, plan de rollback paso a paso con 6 puntos, medición de impacto pre/post. La tabla de impacto en queries (`Resumen de Impacto en Queries`) es particularmente clara. Se echa de menos un script de prueba SQL con EXPLAIN ANALYZE o pg_stat_statements.

#### Impacto Estimado en Reducción de Queries (90/100) — CRITERIO CLAVE
94-96% de reducción estimada (~50-80 queries/min desde 1,320). Es la solución con mayor impacto directo en la BD porque elimina queries por completo en lugar de solo consolidarlas. Análisis por endpoint:
- **stats.php**: 9 queries → 0 (cache 15s). Impacto masivo.
- **sse.php**: 4 queries/5s → 0 si no dirty. Dirty flag evita ~95% de ejecuciones.
- **analisis.php carga**: 10 queries → 0 (cache 300s).
- **Location tree**: N+1 → 0 queries (cache 3600s).
- **Polling reducido**: realtime 15s→30s, analisis 10s→60s, notifs 30s→60s.

El único aspecto que baja el puntaje de 100 es que el polling frontal NO se elimina, solo se reduce (~50% menos), y cada request (aunque cacheado) sigue consumiendo conexión Apache + PHP + ciclo de vida de sesión.

#### Integrabilidad con el Sistema Existente (75/100)
Cambios en ~12 archivos, lo cual es moderado. La principal debilidad es la dependencia de APCu, una extensión PECL que debe verificarse en el servidor (`php -m | findstr apcu`). Si no está instalada, el sistema hace fallback a queries directas (funcional pero sin beneficio). Interfaz pública 100% compatible: los mismos endpoints, mismos formatos JSON. El plan de implementación incluye verificación de APCu y documentación de instalación. Puntos fuertes: no requiere cambios en vistas ni en routers existentes.

Sin embargo, como juez de BD, me preocupa que APCu es un caché de proceso (no compartido entre workers PHP-FPM). En un entorno con múltiples workers PHP-FPM, cada worker tiene su propio APCu, lo que significa que una invalidación de caché en un worker no es visible para los demás. La migración SQL de dirty flags como respaldo mitiga parcialmente esto, pero el caché de datos (ticket_stats, location_tree) quedaría inconsistente entre workers. Esto podría causar que un admin vea datos antiguos hasta que el TTL expire.

#### Plan de Implementación y Rollback (85/100)
Plan desglosado por día (2 días, realista para la complejidad baja). Orden correcto de dependencias: primero el wrapper, luego modelos, luego SSE, luego frontend. Rollback detallado por componente con instrucciones específicas: comentar `Cache::remember()`, restore de intervalos, DROP TRIGGER, eliminación de archivo. Tiempo de rollback estimado (< 30 min). Debilidad: no incluye script de rollback SQL, solo comandos descriptivos. No menciona backups antes de modificar.

#### Riesgos Residuales (95/100)
Excelente identificación de riesgos. Cache stampede mitigado con dos mecanismos: `apcu_entry()` (la opción atómica recomendada por PHP) y fallback manual con lock + semáforo (para versiones sin apcu_entry). Degradación graceful documentada: si APCu no está disponible, cada `Cache::remember()` ejecuta el callback directamente. El sistema funciona sin APCu, solo sin beneficio de rendimiento. Se agrega migración SQL opcional para dirty flags como respaldo. Concurrencia SSE manejada con bandera atómica. Riesgo no identificado: fragmentación de APCu en entornos multi-worker.

---

### 2. Solución A-E1 — Unificación de Canales en Tiempo Real + Cache Condicional — **76.25 pts**

#### Cobertura de Cuellos de Botella (45/100)
Resuelve ~40-45% de los problemas. Su enfoque es principalmente arquitectónico: elimina el polling de frontend sustituyéndolo por SSE con heartbeat + ETag. Logra eliminar 2 de 3 canales de polling. Sin embargo, no toca problemas fundamentales de BD: N+1 en locations, queries consolidables, índices ausentes, session_regenerate_id(), User::getAll fallback, cache de status names. La solución asume que el verdadero cuello de botella es la frecuencia de polling, no la eficiencia de cada query. Es un approach válido pero parcial.

#### Calidad del Código Propuesto (85/100)
Código completo y real. El endpoint `dashboard-poll.php` está bien implementado con soporte de ETag/304, headers HTTP correctos (`Cache-Control`, `If-None-Match`), y estructura RESTful. El SSE reescrito es correcto: usa `fetchDashboardData()` con cURL interno, mantiene el etag en `$lastEtag`, manejo de errores con try/catch. SSEClient.js es un patrón Observer bien implementado con reconexión exponencial (3s, 6s, 12s, 24s, max 30s). Puntos débiles: el cURL interno desde SSE hacia dashboard-poll agrega latencia de red HTTP (handshake TCP + request/response) en cada cambio detectado. `session_start()` tanto en SSE como en dashboard-poll puede causar contención de bloqueo de sesión. No hay manejo de timeout en el fetch interno más allá de `CURLOPT_TIMEOUT => 4`.

#### Completitud de la Solución (85/100)
Cubre backend PHP (dashboard-poll, SSE), frontend JS (sse-client, realtime refactor, analisis-charts refactor), migración SQL ejecutable, .htaccess, integración en vistas (footer.php). La estrategia de pruebas es buena: 5 pruebas unitarias, 3 de carga, 5 de integración. Sin embargo, faltan scripts de prueba ejecutables (por ejemplo, curl para verificar 304, o script PHP para verificar heartbeat trigger). El plan de rollback es detallado con comandos exactos, incluyendo `git checkout` y `DROP TABLE`. La solución asume que stats.php puede eliminarse después de 1 semana, pero no proporciona guía de migración para clientes que dependan de él.

#### Impacto Estimado en Reducción de Queries (88/100)
97.7% de reducción estimada (~30 queries/min). El ETag/304 elimina el payload en ~99% de las respuestas cuando no hay cambios. La reducción de polling es total (elimina 2 canales completos). Desde la perspectiva de BD, el heartbeat query (`SELECT etag FROM oti.cache_heartbeat WHERE key = 'dashboard'`) es una consulta por PK, extremadamente ligera (~0.1ms con índice implícito de PK). Con 20 admins: 20 conexiones SSE × 12 heartbeats/min = 240 heartbeats/min, pero solo 12 queries/min a BD porque el trigger FOR EACH STATEMENT ejecuta una sola UPDATE por transacción. Esto es correcto.

Sin embargo, el heartbeat query corre 12 veces por minuto aunque no haya cambios. Comparado con C-E1, que puede tener 0 queries/min durante períodos sin cambios, A-E1 tiene un piso constante de ~12 queries/min. Desde mi perspectiva de DB expert, este piso constante es aceptable (queries ultra-ligeras) pero evitable. Además, cuando hay un cambio, el SSE debe hacer un cURL a dashboard-poll, que ejecuta 11 queries completas de dashboard, en lugar de servir datos cacheados como C-E1. La penalización de cambios simultáneos es mayor.

#### Integrabilidad con el Sistema Existente (80/100)
Cambios en ~7 archivos. Sin dependencias externas nuevas (PostgreSQL puro). Nuevo endpoint en `/api/v1/` con regla .htaccess que no interfiere con rutas existentes. Compatibilidad hacia atrás: stats.php se mantiene con header `X-Deprecated`. El SSEClient se integra como script independiente que se carga ANTES de realtime.js. La solución es bien pensada para no romper nada existente. Debilidad: requiere que `session_start()` funcione correctamente entre SSE (que mantiene sesión abierta) y dashboard-poll (que la lee). Si el bloqueo de sesión de PHP (session locking) no se maneja, las escrituras de sesión en una conexión pueden bloquear la otra.

#### Plan de Implementación y Rollback (88/100)
Plan de 7 días desglosado por día con archivos específicos. Orden de dependencias lógico: BD triggers → .htaccess → endpoint → SSE → JS → integración. Rollback extremadamente detallado con comandos SQL exactos (`DROP TRIGGER IF EXISTS trg_tickets_heartbeat ON oti.tickets;`) y comando git para revertir todo en un paso. Script de rollback rápido incluido (< 5 min). Es el plan de rollback más completo de las tres soluciones. Debilidad: 5-7 días es una estimación algo larga para cambios que son mayoritariamente añadir archivos nuevos (no modificar lógica compleja).

#### Riesgos Residuales (70/100)
Identifica reconexión SSE con backoff exponencial. El ETag mitiga tráfico innecesario. Sin embargo, no identifica riesgos importantes desde mi perspectiva:
1. **Bloqueo de sesión PHP**: El SSE mantiene una sesión abierta. `session_start()` en el ciclo each 5s puede bloquear el archivo de sesión. Si otro request (dashboard-poll de otro admin) intenta leer la misma sesión, se bloquea.
2. **Latencia de cURL interno**: El SSE hace un cURL HTTP a dashboard-poll en cada cambio. Esto agrega latencia de red + handshake TCP (pueden ser 100-500ms adicionales).
3. **ETag en proxies inversos**: Algunos proxies (Varnish, Cloudflare, nginx) pueden cachear respuestas 304 agresivamente y no revalidar. No se menciona configuración de `Cache-Control: no-cache` en SSE (aunque está presente en dashboard-poll).
4. **Trigger FOR EACH STATEMENT vs múltiples cambios**: Si 100 tickets se insertan en una transacción, el trigger ejecuta UNA SOLA VEZ (correcto), pero el heartbeat no detecta progreso parcial hasta el COMMIT (diseño intencional, pero podría ser un riesgo si hay transacciones largas).

---

### 3. Solución B-E1 — Consolidación de Queries + Estrategia de Índices — **75.50 pts**

#### Cobertura de Cuellos de Botella (72/100)
Resuelve ~65-70% de los problemas. Es la solución más completa en términos de qué problemas corrige: consolida 12+12 queries en stats.php y analisis.php, elimina N+1 en locations con CTE recursiva y self-JOIN, cachea status names con static cache, elimina fallback duplicado en User::getAll, optimiza session_regenerate_id(), corrige PDO named params, y agrega 8 índices compuestos + pg_trgm. Sin embargo, NO aborda el problema de polling excesivo (el mayor generador de carga) ni la cacheabilidad de respuestas. La solución reduce la carga POR QUERY pero no reduce el NÚMERO DE QUERIES. En un sistema con 20 admins haciendo polling cada 15s, esto sigue siendo ~356 queries/min.

#### Calidad del Código Propuesto (82/100)
Código real y completo. El uso de `JSON_BUILD_OBJECT` con `JSON_AGG` para consolidar sub-queries agregadas en una sola pasada es técnicamente sólido. `COUNT(*) FILTER(WHERE ...)` está usado correctamente en lugar de CASE WHEN. La CTE recursiva en `getPath()` tiene safety limit (depth < 10). El código de session_regenerate_id() condicional es correcto. Puntos débiles:
- La Q1 MASTER usa `FULL JOIN oti.equipment e ON 1=0` (cross-join placeholder), lo cual es frágil y podría dar resultados inesperados con diferentes planes de ejecución de PostgreSQL. Preferiría subqueries laterales o CTEs separadas.
- `JSON_BUILD_OBJECT` en subqueries correlacionadas puede ser costoso para conjuntos grandes de datos; no hay EXPLAIN ANALYZE que valide el plan.
- `json_decode($master['tickets_por_mes'] ?? '[]', true)` es correcto pero frágil si PostgreSQL devuelve NULL en lugar de string JSON.
- No hay manejo de errores específico para fallos de json_decode o datos malformados.

#### Completitud de la Solución (70/100)
Código PHP completo para todos los componentes. Migración SQL ejecutable y bien documentada (incluye notas sobre CREATE INDEX CONCURRENTLY fuera de transacción). Rollback detallado con script de rollback de índices. Sin embargo, carece completamente de cambios frontend: no modifica polling, no reduce intervalos, no añade caché. La estrategia de pruebas es débil: solo menciona "EXPLAIN ANALYZE, comparación de tiempos" sin scripts concretos ni criterios de éxito cuantificables por prueba. No hay medición de baseline vs post-implementación en queries/min, solo en queries/request. El título dice "Semana 1" pero el plan solo cubre 5 días.

#### Impacto Estimado en Reducción de Queries (65/100) — CRITERIO CLAVE
73% de reducción por request (de ~30 queries a ~8 queries por carga), pero 0% de reducción en frecuencia de polling. El impacto real en queries/min es significativamente menor que las otras soluciones porque cada request sigue ocurriendo cada 15s.

Cálculo realista:
- **Antes:** 1,320 queries/min (12 queries × 4 req/min × 20 admins + overhead)
- **Después:** ~356 queries/min (3 queries × 4 req/min × 20 admins + 2 queries para analisis)

Mientras C-E1 y A-E1 llevan la carga a ~50-80 y ~30 queries/min respectivamente, B-E1 se queda en ~356 queries/min. En términos de carga real de BD, esto sigue siendo significativo. Los índices ayudan a que esas 356 queries/min sean rápidas,  pero no eliminan la contención de conexiones, el overhead de planificación de queries, ni la competencia por buffers compartidos.

Fortalezas concretas: la CTE recursiva elimina el N+1 en locations (el peor patrón de todos), los índices compuestos mejorarán significativamente los full table scans, y el cache estático de status names evita queries repetitivas innecesarias.

#### Integrabilidad con el Sistema Existente (88/100)
Cambios en ~6 archivos, localizados. Sin dependencias externas nuevas. Interfaz 100% compatible: mismos endpoints, mismos formatos JSON. Las migraciones SQL son CONCURRENTLY (no bloquean escrituras). El cambio de session_regenerate_id() es inocuo. La corrección de PDO named params es compatible hacia atrás. Es la solución con menor riesgo de ruptura. Puntos débiles: no especifica qué pasa si la migración de índices CONCURRENTLY falla en producción (e.g., deadlock, falta de espacio en disco, locks prolongados). Los índices GIN con pg_trgm requieren extensión que puede no estar instalada (mencionado pero sin plan de contingencia claro).

#### Plan de Implementación y Rollback (85/100)
Plan de 3-5 días con desglose por día y archivos específicos. Orden correcto: primero stats.php y analisis.php (más impacto), luego locations, luego índices. Rollback detallado por componente con script de rollback de índices completo. Incluye buena práctica de backups obligatorios (`*.bak`) antes de modificar. Debilidad: no estima tiempo de rollback. La migración CONCURRENTLY requiere ejecución fuera de transacción y no puede revertirse fácilmente con un simple DROP si hay dependencias. El script de rollback de índices es correcto pero asume que DROP INDEX CONCURRENTLY no fallará.

#### Riesgos Residuales (55/100)
La identificación de riesgos es insuficiente:
1. **SQL consolidado complejo**: Las queries con JSON_BUILD_OBJECT + JSON_AGG + subqueries correlacionadas pueden ser más lentas que las queries individuales si el optimizador de PostgreSQL elige mal el plan. No se menciona verificación con EXPLAIN ANALYZE antes del deploy.
2. **Riesgo de FULL JOIN ON 1=0**: El placeholder `FULL JOIN oti.equipment e ON 1=0` en la Q1 MASTER es hacky. Podría causar resultados inesperados con ciertas configuraciones de join_collapse_limit o from_collapse_limit.
3. **GIN index maintenance**: Los índices GIN con pg_trgm tienen overhead en INSERT/UPDATE, especialmente en tablas grandes como tickets. No se menciona el impacto en escrituras ni la estrategia de mantenimiento (VACUUM, autovacuum tuning).
4. **CTE recursiva sin límite de profundidad**: Aunque hay `WHERE lp.depth < 10`, no hay validación de entrada para `$locationId`. Si se pasa un ID inválido, la recursión itera sobre datos incorrectos.
5. **No hay degradación graceful**: Si alguna query consolidada falla (timeout, error de sintaxis), todo el endpoint falla. Las queries individuales permitían que algunas secciones del dashboard se renderizaran aunque otras fallaran.

---

## Ranking Final

| Posición | Solución | Puntaje | Veredicto |
|:--------:|----------|:-------:|:----------:|
| 🥇 1 | **C-E1** — APCu + Throttling | **80.50** | Aprobar |
| 🥈 2 | **A-E1** — Unificación + ETag | **76.25** | Aprobar |
| 🥉 3 | **B-E1** — Consolidación + Índices | **75.50** | Aprobar (límite) |

## Veredicto del Juez 3

**Solución recomendada: C-E1 (APCu + Throttling)**

Como juez especializado en PostgreSQL y rendimiento de backend, mi criterio más valorado es el **Impacto Real en Reducción de Queries** (peso 15). C-E1 es la única solución que puede reducir las queries de BD a CERO durante períodos de cache hit, que son la mayoría (~95% del tiempo en un dashboard de monitoreo). Aunque introduce una dependencia externa (APCu) y tiene el riesgo de fragmentación entre workers PHP-FPM, el fallback graceful a queries directas y la migración SQL de respaldo mitigan esto adecuadamente.

La razón principal por la que C-E1 supera a A-E1 y B-E1 es **eliminación de queries vs consolidación de queries**: C-E1 elimina queries (94-96% menos), mientras B-E1 solo las consolida (~73% menos por request) y A-E1 las reemplaza por un heartbeat ligero (97.7% menos pero con piso constante). Para una BD PostgreSQL con 20 admins concurrentes haciendo polling, la diferencia entre 50 queries/min (C-E1) y 356 queries/min (B-E1) es la diferencia entre un sistema relajado y uno bajo presión constante.

**Recomendaciones para implementación de C-E1:**
1. Verificar que APCu esté disponible y configurado con suficiente memoria (`apcu.shm_size >= 64M` recomendado).
2. Monitorear hit ratio de APCu en producción (`Cache::getStats()`) — si cae por debajo de 90%, ajustar TTLs.
3. Implementar la migración SQL de dirty flags como respaldo ANTES del deploy para evitar inconsistencias multi-worker.
4. Agregar monitoreo de fragmentación APCu (el reporte `num_entries_hits` vs `num_entries_misses`).
5. Si se confirma que APCu no comparte entre workers, considerar Redis como capa alternativa (mayor complejidad pero caché compartido real).

## Observaciones Generales

1. **Ninguna solución es perfecta aisladamente.** La combinación ideal sería: C-E1 como capa de caché primaria + B-E1 para consolidación de queries + índices. Si el equipo tuviera recursos, recomendaría implementar C-E1 primero (máximo impacto con mínimo esfuerzo) y luego B-E1 incrementalmente.

2. **B-E1 merece implementación independientemente del ranking.** Los índices compuestos, la CTE recursiva en locations, y la corrección de session_regenerate_id() son mejoras estructurales que deberían implementarse aunque se elija C-E1 o A-E1. No son mutuamente excluyentes.

3. **A-E1 es la solución más elegante arquitectónicamente.** Si el equipo prioriza pureza arquitectónica sobre impacto inmediato en BD, A-E1 es la mejor opción a largo plazo. Sin embargo, el riesgo de bloqueo de sesión PHP y la latencia de cURL interno son preocupaciones reales que requieren mitigación adicional.

4. **La medición de baseline es crítica.** Las estimaciones de reducción (1,320 queries/min → 50-80) deben validarse con `pg_stat_statements` antes y después de la implementación. Sin datos reales, todas las proyecciones son especulativas.

5. **Recomendación final al meta-juez:** Aprobar C-E1 (80.50) como implementación primaria, con la condición de que los índices de B-E1 y la corrección de session_regenerate_id() se implementen como parte del mismo sprint. Esto maximiza el impacto en BD con el mínimo riesgo.
