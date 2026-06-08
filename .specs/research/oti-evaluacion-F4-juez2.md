# Evaluación Fase 4 — Juez 2

**Fecha:** 2026-05-21
**Evaluador:** Juez de Evaluación #2 (Fase 4) — Especialista en calidad de código y mantenibilidad
**Criterios evaluados:** 8 (según especificación YAML meta-juez v1.0)

## Resumen de Puntuaciones

| Solución | Cobertura(20) | Código(20) | Completitud(15) | Impacto(15) | Integrabilidad(15) | Plan(10) | Riesgos(5) | Testing(5) | **Total** |
|----------|:------------:|:----------:|:---------------:|:-----------:|:------------------:|:--------:|:----------:|:----------:|:---------:|
| **C-E1** | 78 | 93 | 90 | 88 | 80 | 85 | 88 | 90 | **90.30** |
| **A-E1** | 60 | 70 | 76 | 92 | 74 | 78 | 65 | 73 | **77.00** |
| **B-E1** | 82 | 78 | 82 | 68 | 88 | 80 | 60 | 60 | **81.70** |

---

## Evaluación Detallada

### C-E1 — Cache First, Polling Last (APCu + Throttling)

**Puntaje total: 90.30/100**

#### Fortalezas

| Criterio | Score | Justificación |
|----------|:-----:|---------------|
| **Cobertura (20)** | 78 | Cubre ~55-60% de los problemas identificados: cache stampede, SSE ineficiente, polling overhead, árbol de ubicaciones, caché de stats/analisis, banderas dirty. No aborda consolidación de queries individuales ni optimización de índices. |
| **Código (20)** | 93 | Código PHP de **altísima calidad**: strict types, null-safety, degradación graceful, fallback manual con semáforo para cache stampede, verificación programática de disponibilidad APCu (`isAvailable()`). Separación limpia en `App\Cache\Store`. Manejo de excepciones en SSE. Único pero menor: TTLs hardcodeados sin configurabilidad. |
| **Completitud (15)** | 90 | Todos los componentes cubiertos con código completo y ejecutable: wrapper APCu, modificaciones Ticket/Location/SSE/stats/analisis, migración SQL opcional con triggers, JS frontend con throttling, plan de pruebas con 7 casos, plan de rollback con tiempo estimado (<30min). |
| **Impacto (15)** | 88 | Reducción estimada de ~1,320 a ~50-80 queries/min (**94-96%**). Polling reducido de ~14 a ~7 requests/min (50%). Cifras realistas con TTLs de 15-60s. En cache miss, aún ejecuta queries completas. |
| **Integrabilidad (15)** | 80 | Dependencia de APCu (extensión PHP verificable con `extension_loaded`). Fallback automático si no disponible. Mismos endpoints, mismos formatos de respuesta. ~12 archivos modificados. Sin tiempo de inactividad esperado. |
| **Plan (10)** | 85 | Plan de 2 días realista, desglosado por AM/PM con archivos específicos. Rollback por componente con pasos concretos. Tiempo de rollback estimado (<30min). |
| **Riesgos (5)** | 88 | Cache stampede manejado con `apcu_entry()` + semáforo manual. APCu verificado programáticamente. Degradación graceful con fallback a queries directas. Bandera dirty con verificación post-implementación documentada. |
| **Testing (5)** | 90 | 7 casos de prueba con criterios de éxito cuantificables ("Segunda llamada: 0 queries SQL"). Scripts CLI PHP para verificación de caché y dirty flags. Prueba de concurrencia (stampede con 10 requests). Medición baseline vs post implementación. |

#### Debilidades

1. **No consolida queries individuales** — En cache miss, cada endpoint sigue ejecutando queries separadas sin optimizar (stats.php aún corre 9 queries, solo que cacheadas). Una estrategia híbrida con queries consolidadas + caché sería más efectiva.
2. **Dependencia de APCu** — Si bien hay fallback, APCu no está disponible en entornos compartidos o algunos contenedores Docker. La migración SQL de dirty flags es "opcional", dejando un vacío si APCu falla y no se ejecutó la migración.
3. **TTLs fijos** — Los valores de TTL (60s, 300s, 3600s) están hardcodeados. En producción puede requerir ajuste fino sin re-deploy.

#### Recomendaciones

- Complementar con consolidación de queries (tomar Q1 Master de B-E1) dentro de los callbacks de caché.
- Hacer configurables los TTLs vía constantes o archivo de configuración.
- Hacer la migración SQL de dirty flags **obligatoria**, no opcional, para tener respaldo funcional siempre.

---

### A-E1 — Unificación de Canales en Tiempo Real + Cache Condicional

**Puntaje total: 77.00/100**

#### Fortalezas

| Criterio | Score | Justificación |
|----------|:-----:|---------------|
| **Cobertura (20)** | 60 | Enfocado principalmente en eficiencia de polling/SSE y caché condicional. No aborda: consolidación de queries, índices, N+1 en locations, problema de sesión, caché estático de status names. Cubre ~40-45% de los problemas. |
| **Código (20)** | 70 | **Problemas significativos:** (1) `fetchDashboardData()` usa CURL con forwarding de cookie de sesión — frágil, propenso a errores y difícil de debuggear. (2) Conexión BD creada y destruida en cada ciclo SSE (cada 5s) — patrón ineficiente. (3) `timeAgo()` duplicado en dashboard-poll.php. (4) Código mostrado como diffs parciales con "...resto se mantiene igual" — imposible verificar completitud. (5) Sin strict types. Puntos fuertes: clase SSEClient.js bien diseñada, eventos limpios. |
| **Completitud (15)** | 76 | Migración SQL completa con triggers. Endpoint nuevo completo. SSEClient.js completo. Pero realtime.js y analisis-charts.js tienen cambios incompletos (formato diff). Sin scripts de prueba ejecutables. Rollback detallado con comandos git. |
| **Impacto (15)** | 92 | **Mayor reducción estimada:** ~1,320 a ~30 queries/min (**97.7%**). Heartbeat (12 queries/min) + cambios esporádicos. Elimina 2 de 3 canales de polling. Cifras agresivas pero plausibles con el modelo heartbeat. |
| **Integrabilidad (15)** | 74 | Nuevo endpoint + regla .htaccess. Nuevo archivo JS (sse-client.js) debe cargarse antes que realtime.js — orden de carga crítico. Triggers en 5+ tablas (overhead en escrituras no cuantificado). ~12 archivos. Compatible hacia atrás. |
| **Plan (10)** | 78 | Plan de 7 días. Desglose claro por día y archivo. Rollback con comandos git exactos y script SQL. 7 días parece conservador para el alcance. |
| **Riesgos (5)** | 65 | Reconexión SSE con backoff exponencial bien implementada. **No discute:** overhead de triggers en operaciones de escritura, modos de fallo de CURL en SSE, punto único de fallo (tabla heartbeat puede estar corrupta). |
| **Testing (5)** | 73 | Pruebas de carga con 20 conexiones SSE concurrentes. Verificación SQL con `pg_stat_activity`. Criterios de éxito claros. Pero **sin scripts ejecutables** (a diferencia de C-E1). |

#### Debilidades

1. **CURL en SSE es frágil** — La comunicación vía HTTP interno con forwarding de cookies de sesión agrega complejidad innecesaria y un punto de fallo. Si el servidor web tiene problemas de routing interno o timeout, el SSE se degrada silenciosamente.
2. **Conexión BD en cada ciclo** — `Database::connect()` + `Database::disconnect()` cada 5s en SSE es un antipatrón. Una conexión persistente o reutilizable sería más eficiente.
3. **Cobertura estrecha** — Es la solución con menor cobertura de problemas. No toca optimización de queries, índices, ni problemas estructurales del modelo de datos.
4. **Código incompleto** — Las secciones mostradas como diffs no permiten verificar que el código final sea correcto. Esto es particularmente grave para `realtime.js` donde se eliminaron funciones críticas (`initSSE`, `closeSSE`).

#### Recomendaciones

- Reemplazar CURL interno con llamada directa a función PHP (reutilizar lógica de dashboard-poll como servicio, no como endpoint HTTP).
- Mantener conexión BD persistente en el ciclo SSE en lugar de conectar/desconectar.
- Complementar con al menos los índices compuestos de B-E1 para que las queries de dashboard-poll sean eficientes.
- Escribir el código completo (no diffs) antes de implementación.

---

### B-E1 — Consolidación de Queries + Estrategia de Índices

**Puntaje total: 81.70/100**

#### Fortalezas

| Criterio | Score | Justificación |
|----------|:-----:|---------------|
| **Cobertura (20)** | 82 | **Mayor cobertura de problemas de queries:** stats.php (12→3), analisis.php (12→2), Location N+1 → CTE recursiva, Location getById() self-JOIN, User::getAll() fallback eliminado, caché estático de status names, session_regenerate_id() condicional, 8 índices compuestos + pg_trgm, PDO named params. Cubre ~65-70% de los problemas. |
| **Código (20)** | 78 | SQL correcto y avanzado (CTE recursiva, JSON_BUILD_OBJECT, FILTER). **Pero:** (1) `FULL JOIN ON 1=0` + `CROSS JOIN` en Q1 Master es un patrón confuso y puede tener implicaciones de rendimiento no evidentes. (2) Caché estático `$statusNames` sin mecanismo de invalidación — puede servir datos obsoletos. (3) Subqueries JSON_BUILD_OBJECT anidadas agregan complejidad de mantenimiento. (4) Sin strict types. |
| **Completitud (15)** | 82 | Código PHP completo para todos los componentes. Migración SQL completa con script de rollback. Sin cambios frontend (apropiado). Testing mencionado pero con poco detalle. Rollback bien documentado con script SQL específico. |
| **Impacto (15)** | 68 | **Impacto en queries/min más limitado.** ~73% de reducción de queries por request. Pero sin capa de caché ni throttling, 20 admins siguen generando ~360 queries/min (vs 1,320 original). Las queries son más eficientes, pero se ejecutan con la misma frecuencia. |
| **Integrabilidad (15)** | 88 | **Mejor integrabilidad.** Solo 7-8 archivos modificados. Sin dependencias externas nuevas (pg_trgm es contrib de PostgreSQL, normalmente disponible). Índices CONCURRENTLY no bloquean escrituras. 100% compatible hacia atrás. Riesgo de integración mínimo. |
| **Plan (10)** | 80 | Plan de 5 días realista. Rollback por componente con script SQL de reversión incluido. Estrategia de backups obligatorios (.bak). Buen orden de dependencias. |
| **Riesgos (5)** | 60 | **Identificación de riesgos más débil.** Caché estático sin invalidación documentada. No hay registro de riesgos explícito. Las queries JSON_BUILD_OBJECT anidadas pueden tener rendimiento inesperado en tablas grandes. No se discute el impacto del CTE recursivo en jerarquías profundas. |
| **Testing (5)** | 60 | **Estrategia de pruebas más débil.** Menciona EXPLAIN ANALYZE y comparación de tiempos pero sin scripts, sin CLI commands, sin metodología de baseline. Sin pruebas de concurrencia. Sin verificación cuantitativa documentada. |

#### Debilidades

1. **Sin capa de caché** — Aunque las queries son más eficientes, cada solicitud sigue hitting la base de datos. Para un dashboard que se consulta cada 15s por 20 admins, esto sigue siendo ~360 queries/min. La solución sería más potente combinada con APCu.
2. **FULL JOIN ON 1=0 + CROSS JOIN** — El patrón en Q1 Master para combinar tickets + equipos + usuarios en una sola query es ingenioso pero frágil. Cualquier cambio en las tablas puede romper la query silenciosamente. Un enfoque más mantenible sería aceptar 3 queries separadas.
3. **Caché estático sin invalidación** — `$statusNames` se cachea en memoria por request, pero esto significa que si un admin cambia los nombres de los status, los workers PHP existentes pueden servir datos obsoletos hasta el próximo request.
4. **Testing insuficiente** — Es la solución con la estrategia de pruebas más débil. Para cambios tan profundos en las queries (especialmente las JSON_BUILD_OBJECT), se necesitan pruebas de regresión.

#### Recomendaciones

- Reemplazar `FULL JOIN ON 1=0` con queries separadas (3 queries simples vs 1 compleja) para mejorar mantenibilidad.
- Agregar mecanismo de invalidación para `$statusNames` (TTL por tiempo o hook en actualización de statuses).
- Complementar con capa de caché APCu (tomar wrapper de C-E1) para reducir queries/min significativamente.
- Agregar scripts de prueba con EXPLAIN ANALYZE automatizado y comparación de tiempos antes/después.

---

## Ranking Final

| Posición | Solución | Puntaje | Veredicto |
|:--------:|----------|:-------:|-----------|
| **1º** | **C-E1**: Cache First, Polling Last (APCu + Throttling) | **90.30** | ✅ **Aprobar** |
| **2º** | **B-E1**: Consolidación de Queries + Estrategia de Índices | **81.70** | ✅ **Aprobar** |
| **3º** | **A-E1**: Unificación de Canales en Tiempo Real + Cache Condicional | **77.00** | ✅ **Aprobar** |

**Decisión:** Las 3 soluciones superan el umbral de 75 puntos, por lo tanto las 3 son aprobadas para consideración. Sin embargo, se recomienda priorizar C-E1 por su excelente calidad de código, completitud y balance entre impacto y mantenibilidad.

---

## Observaciones Generales

1. **Dominio claro de C-E1 en calidad de código** — Como Juez #2 (exigente en calidad de código), C-E1 destaca significativamente. El wrapper `App\Cache\Store` está escrito con los más altos estándares: strict types, manejo exhaustivo de errores, protección contra cache stampede, degradación graceful. Es código que se puede poner en producción con confianza.

2. **Sinergia entre soluciones** — Las 3 soluciones son complementarias:
   - **C-E1** aporta la capa de caché y el patrón de dirty flags.
   - **B-E1** aporta la optimización estructural de queries y la estrategia de índices.
   - **A-E1** aporta el modelo heartbeat/ETag para SSE eficiente.
   
   La combinación C-E1 + B-E1 sería la estrategia óptima: consolidación de queries + caché APCu + índices, que podría superar 95 puntos.

3. **A-E1 tiene el mayor impacto pero el código más frágil** — Si bien su reducción estimada (97.7%) es la más alta, los problemas de calidad de código (CURL en SSE, conexión BD cíclica, código incompleto) la hacen la solución de mayor riesgo de implementación.

4. **B-E1 es la más integrable pero la menos probada** — Su integrabilidad (88) es la mejor, pero su estrategia de pruebas (60) es insuficiente para cambios tan profundos en las queries. Sin pruebas de regresión, el riesgo de introducir bugs silenciosos es alto.

5. **Recomendación final:** Implementar **C-E1 como prioridad** por su relación impacto/riesgo, seguido de los índices de **B-E1** (bajo riesgo, alto beneficio), y finalmente considerar el modelo heartbeat de **A-E1** solo si el polling sigue siendo un cuello de botella después de la caché.
