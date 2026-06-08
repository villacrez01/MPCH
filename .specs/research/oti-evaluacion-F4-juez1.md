# Evaluación Fase 4 — Juez 1

**Fecha:** 2026-05-21

## Resumen de Puntuaciones

| Solución | Cobertura(20) | Código(20) | Completitud(15) | Impacto(15) | Integrabilidad(15) | Plan(10) | Riesgos(5) | Total |
|----------|:------------:|:----------:|:---------------:|:-----------:|:------------------:|:--------:|:----------:|:-----:|
| C-E1 | 85 | 90 | 92 | 80 | 88 | 95 | 88 | **87.90** |
| A-E1 | 90 | 88 | 90 | 95 | 80 | 90 | 85 | **88.60** |
| B-E1 | 75 | 92 | 88 | 70 | 95 | 95 | 90 | **85.35** |

## Evaluación Detallada

### C-E1: Cache First, Polling Last (APCu + Throttling)

**Puntaje Total: 87.90**

| Criterio | Puntaje | Justificación |
|----------|:-------:|---------------|
| Cobertura (20) | 85 | Resuelve ~70% de los cuellos de botella identificados: elimina N+1 en Location vía tree cache (100% reducción), cachea stats.php (100% reducción en cache hit), agrega dirty flags a SSE (~95% reducción), throttlea polling JS (50% reducción), cachea analisis.php initial load. No cubre: índices compuestos, regeneración de sesión, User::getAll fallback, changeStatus/isFinalStatus static cache. Sin embargo, los problemas que cubre son los de mayor impacto. |
| Código (20) | 90 | Código PHP real, sintácticamente correcto y completo. `App\Cache\Store` tiene manejo exhaustivo de errores con `isAvailable()` checks, protección contra cache stampede (`apcu_entry` con fallback de semáforo + usleep), try/catch/finally en locks, y estadísticas APCu. Los modelos modificados (Ticket, Location, SSE) son funcionales. Fallback graceful cuando APCu no está disponible devuelve directo del callback. |
| Completitud (15) | 92 | Cubre todos los aspectos: backend PHP (Store.php + 4 modelos), frontend JS (2 archivos con cambios), SQL migration para dirty flags, 7 pruebas con criterios de éxito cuantificables, plan de rollback por componente con tiempo estimado (<30 min), medición de impacto pre/post con estimaciones numéricas detalladas. Único ausente: scripts de prueba ejecutables (solo comandos de verificación). |
| Impacto (15) | 80 | Reporta 94-96% de reducción de queries/min (1,320→50-80). Es una reducción muy alta. Sin embargo, el throttling solo reduce polling en ~50% (14→7 requests/min), no lo elimina. El impacto real depende de la tasa de cache hit. En momentos de mucha escritura (muchos dirty flags), la reducción cae al ~50% en SSE. |
| Integrabilidad (15) | 88 | APCu es extensión PHP nativa, sin dependencias externas nuevas. Cambios localizados (~10 archivos). Interfaz pública 100% compatible: mismos endpoints, mismos formatos de respuesta. Fallback automático si APCu no está disponible (vuelve a queries directas). La migración SQL de dirty flags es opcional. Único riesgo: APCu no está en todas las instalaciones de PHP (requiere `extension=apcu`). |
| Plan (10) | 95 | Plan de 2 días desglosado en bloques AM/PM con archivos específicos por bloque. Orden de dependencias correcto (wrapper cache → modelos → SSE → frontend → migración). Rollback paso a paso con 6 pasos detallados y tiempo estimado (<30 min). Pruebas numeradas T1-T7 con criterios de éxito. |
| Riesgos (5) | 88 | Identifica y mitiga: (1) cache stampede con `apcu_entry()` + semáforo manual con lock timeout de 5s y retry con usleep(50000), (2) degradación graceful sin APCu con `isAvailable()` fallback a callback directo, (3) dirty flag race condition vía triggers SQL opcionales como respaldo. No discute riesgo de memory fragmentation de APCu en caché de location_tree (3600s TTL con datos completos). |

**Fortalezas:**
- Implementación más completa de caché con protección contra stampede (apcu_entry + semáforo manual)
- Cobertura balanceada backend/frontend con fallback graceful en todos los niveles
- Plan de implementación más rápido (2 días) y riesgos mejor mitigados

**Debilidades:**
- Dependencia de APCu que podría no estar disponible en todos los entornos
- Polling reducido pero no eliminado (sigue habiendo requests cada 30s/60s)
- No aborda índices de base de datos ni consolidación de queries como capa adicional

**Recomendaciones:**
- Combinar con los índices compuestos de B-E1 para maximizar rendimiento en cache misses
- Agregar monitoreo de hit ratio APCu para detectar degradación temprana

---

### A-E1: Unificación de Canales en Tiempo Real + Cache Condicional

**Puntaje Total: 88.60**

| Criterio | Puntaje | Justificación |
|----------|:-------:|---------------|
| Cobertura (20) | 90 | Resuelve ~70% de los problemas con enfoque arquitectónico: elimina completamente el polling frontend (reemplazado por SSE con heartbeat), introduce ETag/304 para cache condicional HTTP, unifica 3 canales en 1, y agrega triggers de heartbeat para invalidación automática. No cubre: optimización de queries individuales, Location N+1, índices, session regeneration, User fallback. Sin embargo, al cambiar la arquitectura de polling a eventos, elimina la causa raíz del exceso de queries. |
| Código (20) | 88 | Código PHP real y completo. El endpoint dashboard-poll.php con ETag está bien implementado (If-None-Match, 304, Cache-Control). SSEClient JS es una clase robusta con reconexión exponencial. Sin embargo: (1) el SSE modificado hace cURL interno al mismo servidor (`fetchDashboardData()`), lo que agrega latencia y complejidad innecesaria — podría simplemente ejecutar la lógica directamente; (2) la presentación de analisis-charts.js es un diff no aplicable directamente sin contexto completo. |
| Completitud (15) | 90 | Cubre todos los componentes: migración SQL (heartbeat table + 4 triggers + function), endpoint PHP con ETag, SSE reescrito, SSEClient JS, realtime.js refactorizado, analisis-charts.js modificado, cambios en vistas y .htaccess. Testing con 12 pruebas organizadas en 3 categorías. Rollback por componente con comandos SQL y git. Sin embargo, los JS changes están presentados como diff, no como archivo final completo, y analisis-charts.js dice "se mantiene igual" refiriendo a funciones no documentadas. |
| Impacto (15) | 95 | Reporta 97.7% de reducción de queries/min (1,320→30). Es la solución con mayor impacto cuantitativo. Elimina completamente el polling de realtime.js y analisis-charts.js (0 requests de polling). SSE pasa de ejecutar 4-6 queries/5s a solo 1 query/5s (heartbeat). La reducción de ancho de banda SSE es de 98.3% (72KB/min→1.2KB/min). Es la única solución que ataca la causa raíz del problema de polling. |
| Integrabilidad (15) | 80 | Sin dependencias externas nuevas (PostgreSQL puro, JS vanilla). Sin embargo: (1) requiere nuevo endpoint `/api/v1/dashboard-poll.php` + regla .htaccess, (2) nuevo archivo JS `sse-client.js` que debe cargarse antes que `realtime.js`, (3) cambios en 2 vistas (footer.php, analisis.php) para inicializar `window.otiSSE`, (4) ~10-12 archivos modificados. La migración SQL requiere función PL/pgSQL que necesita permisos de superusuario o dueño de schema. El SSE interno con cURL local agrega un punto de acoplamiento: si Apache no puede hacer peticiones a sí mismo (loopback bloqueado), el SSE falla. |
| Plan (10) | 90 | Plan de 7 días desglosado por día con archivos específicos. Orden correcto: BD → endpoint → SSE → JS → vistas → frontend. Rollback por componente con comandos SQL y git. El rollback rápido (<5 min) con `git checkout` es práctico. Sin embargo, las estimaciones por día son genéricas (1 día por archivo) sin considerar la integración, y el tiempo total de 7 días parece conservador para la complejidad. |
| Riesgos (5) | 85 | Identifica: (1) reconexión SSE con backoff exponencial 3s→30s, (2) manejo de conexión abortada en SSE loop, (3) error handling en cURL interno. Sin embargo: no discute el riesgo de que el trigger `FOR EACH STATEMENT` no capture cambios en tablas no monitoreadas, no hay discusión sobre cache stampede (no aplica al no usar APCu, pero el heartbeat cache podría tener race conditions), no menciona riesgo de ETag collision (md5 es suficientemente único pero no se discute). |

**Fortalezas:**
- Mayor impacto en reducción de queries/min (97.7%) — ataca la causa raíz del polling excesivo
- Arquitectura más moderna: event-driven en lugar de poll-driven
- SSEClient.js reutilizable para futuros módulos en tiempo real
- Heartbeat con triggers de BD garantiza detección inmediata de cambios

**Debilidades:**
- El SSE con cURL interno es frágil y agrega latencia (~1-2ms extra por ciclo)
- No optimiza queries individuales — cuando hay un cambio real, sigue ejecutando 6+ queries pesadas
- No toca índices ni Location N+1 (que contribuyen significativamente cuando se ejecutan las queries)
- Dependencia de triggers PL/pgSQL que requieren permisos especiales

**Recomendaciones:**
- Reemplazar cURL interno por llamada directa a función PHP compartida entre dashboard-poll.php y SSE
- Combinar con índices de B-E1 para optimizar las queries que aún se ejecutan en cambios reales

---

### B-E1: Consolidación de Queries + Estrategia de Índices

**Puntaje Total: 85.35**

| Criterio | Puntaje | Justificación |
|----------|:-------:|---------------|
| Cobertura (20) | 75 | Resuelve ~60% de los problemas, enfocado exclusivamente en backend: stats.php 12→3 queries (75% menos), analisis.php 12→2 queries (83% menos), Location::getPath() N+1→1 (100%), Location::getById() 3→1 (~67%), User::getAll() 2→1 (50%), changeStatus/isFinalStatus N→1 (~100%), session_regenerate_id condicional, PDO named params. No cubre NINGÚN problema de frontend: polling excesivo, SSE no optimizado, falta de caché. Los problemas de frontend representan ~40% de los cuellos de botella. |
| Código (20) | 92 | Código SQL más sólido de las 3 soluciones. Uso experto de: COUNT(*) FILTER(WHERE) para múltiples agregados en 1 pasada, JSON_BUILD_OBJECT + JSON_AGG para datos semiestructurados, CTE RECURSIVE con depth limit para jerarquías, self-JOIN con LEFT JOINs encadenados para árbol de locations, static cache con guard clause. La Q1 MASTER con FULL JOIN ON 1=0 es un hack ingenioso aunque cuestionable en legibilidad. session_regenerate_id() condicional es correcto. Las migraciones SQL con CREATE INDEX CONCURRENTLY son producción-ready. |
| Completitud (15) | 88 | Backend PHP completo (stats.php, analisis.php, Location.php, Ticket.php, User.php, index.php). Migración SQL con 8 índices compuestos + pg_trgm. Rollback con script de DROP INDEX exacto. Sin embargo: (1) no hay cambios en frontend JS (realtime.js, analisis-charts.js, SSE), (2) no hay estrategia de pruebas con scripts ejecutables, (3) no hay medición de baseline para comparación pre/post en queries/min, solo se reporta reducción de queries por request. |
| Impacto (15) | 70 | Reporta ~73% de reducción de queries por endpoint, pero esto es por REQUEST, no por minuto. Dado que no reduce la frecuencia de polling, el impacto real en queries/min es significativamente menor (~19-30% estimado). Por ejemplo: stats.php 12→3 queries pero sigue ejecutándose 4 veces/min (ahorro: 36 queries/min de 1,320 ≈ 2.7%). El mayor impacto viene de eliminar Location N+1 y static cache en changeStatus. La solución no mide queries/min en su resumen de impacto. |
| Integrabilidad (15) | 95 | La solución más integrable: 0 dependencias nuevas, 0 cambios en interfaz pública, mismos endpoints, mismos formatos de respuesta JSON, misma API. Los índices usan CREATE INDEX CONCURRENTLY que no bloquea escrituras. Los cambios en modelos son backward-compatible. Sólo session_regenerate_id() condicional requiere verificar que no hay dependencias de regeneración frecuente en otros módulos. Los backups obligatorios antes de modificar archivos son una buena práctica. |
| Plan (10) | 95 | Plan de 5 días bien desglosado con archivos específicos. Incluye día de EXPLAIN ANALYZE para verificación. Rollback detallado por componente con script SQL de rollback de índices incluido (DROP INDEX CONCURRENTLY IF EXISTS). Política de backups obligatorios (.bak) antes de modificar. Ventana de rollback de 24 horas. El plan es realista y bien estructurado. |
| Riesgos (5) | 90 | Identifica: (1) CREATE INDEX CONCURRENTLY no bloquea pero toma más tiempo, (2) índices GIN pg_trgm requieren superusuario para CREATE EXTENSION, (3) FULL JOIN ON 1=0 puede ser confuso para mantenimiento futuro, (4) session_regenerate_id() condicional no rompe funcionalidad existente, (5) static cache en Ticket es thread-safe por ser por-request (no shared). Sin embargo, no discute el riesgo principal: que al no cambiar la frecuencia de polling, el impacto real en queries/min es bajo. |

**Fortalezas:**
- Código SQL más refinado de las 3 soluciones — consultas altamente optimizadas con features modernas de PostgreSQL 16
- Mejor integrabilidad — cambios mínimos con máximo impacto en eficiencia de queries individuales
- Índices compuestos benefician a TODOS los accesos a BD, no solo los endpoints modificados
- Rollback mejor documentado con script de DROP INDEX exacto y backups obligatorios

**Debilidades:**
- No aborda el problema de frontend polling — stats.php sigue ejecutándose cada 15s aunque sea con menos queries
- Impacto real en queries/min es limitado (~19-30%) porque la frecuencia de polling no cambia
- No incluye estrategia de pruebas automatizadas ni scripts de verificación
- La Q1 MASTER con FULL JOIN ON 1=0 es frágil y difícil de mantener

**Recomendaciones:**
- Combinar con throttling de polling de C-E1 (realtime.js 30s, analisis-charts.js 60s) para maximizar el impacto
- Agregar medición de queries/min post-implementación usando `pg_stat_statements` o `pg_stat_activity`

---

## Ranking Final

1. **A-E1 (88.60)** — Unificación de Canales + Cache Condicional. Mayor impacto en reducción de queries/min (97.7%). Elimina completamente el polling frontend, atacando la causa raíz del problema. Arquitectura event-driven más moderna y sostenible. Recomendado como solución primaria combinada con índices de B-E1.

2. **C-E1 (87.90)** — Cache First, Polling Last. Solución balanceada con buen impacto (94-96%) y la implementación más robusta de caché con protección contra stampede. Tiempo de implementación más rápido (2 días). Ideal si se prioriza velocidad de implementación sobre cambio arquitectónico.

3. **B-E1 (85.35)** — Consolidación de Queries + Índices. Excelente calidad de código SQL y la mejor integrabilidad, pero impacto limitado por no abordar el frontend polling. Mejor como solución complementaria que como estrategia independiente.

## Observaciones Generales

1. **Ninguna solución es completa por sí sola.** Las 3 soluciones atacan diferentes capas del problema: A-E1 la arquitectura de comunicación (eventos vs polling), C-E1 la capa de caché, B-E1 la eficiencia de queries e índices. La solución óptima sería una combinación de las 3.

2. **A-E1 vs C-E1:** A-E1 gana en impacto (97.7% vs 94-96%) porque elimina el polling completamente, mientras que C-E1 solo lo reduce. Sin embargo, C-E1 tiene mejor manejo de riesgos (cache stampede, fallback APCu) y puede implementarse en 2 días vs 7 días de A-E1. Para un entorno con 20+ admins concurrentes, A-E1 escala mejor.

3. **B-E1 como complemento ideal:** Los 8 índices compuestos y la consolidación de queries de B-E1 benefician a cualquier solución. Se recomienda implementar B-E1 como capa base, y luego A-E1 o C-E1 como capa de optimización de comunicación.

4. **Sobre el cURL interno en A-E1:** La decisión de hacer cURL desde SSE a dashboard-poll.php agrega complejidad innecesaria y un punto de fallo. Se recomienda refactorizar para compartir la lógica de generación de datos como función común en ambos endpoints.

5. **Métrica de éxito:** Las 3 soluciones carecen de una métrica clara de éxito post-implementación más allá de "reducción de queries/min". Se recomienda agregar monitoreo de: tiempo de renderizado de dashboard (P50/P95), consumo de memoria APCu, y tasa de error SSE.
