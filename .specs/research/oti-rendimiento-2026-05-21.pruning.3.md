# Pruning — Juez 3

## Tabla de Puntuaciones

| Propuesta | Impacto(25) | Factibilidad(25) | Riesgo(20) | Esfuerzo(15) | Reversibilidad(10) | Alineación(5) | Total |
|-----------|:-----------:|:----------------:|:----------:|:------------:|:------------------:|:-------------:|:-----:|
| A-E1 | 95 | 85 | 90 | 75 | 90 | 100 | 88.25 |
| A-E2 | 85 | 80 | 85 | 70 | 85 | 85 | 81.50 |
| A-E3 | 75 | 90 | 90 | 75 | 90 | 100 | 84.50 |
| A-E4 | 80 | 50 | 55 | 55 | 65 | 80 | 62.25 |
| A-E5 | 85 | 40 | 40 | 40 | 50 | 60 | 53.25 |
| A-E6 | 60 | 35 | 40 | 30 | 70 | 85 | 47.50 |
| B-E1 | 80 | 95 | 90 | 80 | 95 | 100 | 88.25 |
| B-E2 | 90 | 75 | 80 | 70 | 85 | 100 | 81.25 |
| B-E3 | 80 | 75 | 80 | 65 | 80 | 85 | 76.75 |
| B-E4 | 85 | 35 | 35 | 30 | 45 | 50 | 48.50 |
| B-E5 | 55 | 40 | 50 | 35 | 65 | 60 | 48.50 |
| B-E6 | 50 | 25 | 25 | 20 | 25 | 40 | 31.25 |
| C-E1 | 80 | 95 | 95 | 95 | 95 | 85 | 90.75 |
| C-E2 | 70 | 90 | 85 | 85 | 85 | 100 | 83.25 |
| C-E3 | 55 | 95 | 95 | 100 | 100 | 100 | 86.50 |
| C-E4 | 80 | 35 | 35 | 30 | 45 | 50 | 47.25 |
| C-E5 | 75 | 60 | 55 | 50 | 65 | 75 | 62.50 |
| C-E6 | 50 | 45 | 50 | 55 | 60 | 70 | 51.50 |

## Top 3

1. **C-E1 «Cache First, Polling Last — APCu + Throttling» (90.75)**
   Justificación: La propuesta mejor balanceada de todo el conjunto. Logra reducir drásticamente la carga de polling (~80% menos queries) mediante APCu con TTLs escalonados y throttling triple, todo implementable en 1-2 días. El riesgo es mínimo gracias a cache stampede protection y fallback directo a BD. Como Juez #3 —exigente con factibilidad y bajo riesgo— esta es la propuesta más sólida. El único pero: requiere activar la extensión APCu en PHP, pero es un cambio trivial y reversible.

2. **B-E1 «Consolidación de Queries + Estrategia de Índices» (88.25)**
   Justificación: Máxima factibilidad técnica (95): SQL puro y DDL, cero dependencias, 3-5 días de trabajo. Los índices GIN compuestos y COUNT(*) FILTER son técnicas probadas con riesgo mínimo (índices CONCURRENTLY). Reduce significativamente queries en páginas críticas (stats.php 11→2, analisis.php 10→3). Es la propuesta más segura y predecible del lote. Se complementa perfectamente con C-E1.

3. **A-E1 «Unificación de Canales en Tiempo Real + Cache Condicional» (88.25)**
   Justificación: El mayor impacto individual (97.7% de reducción: 1,320→30 queries/min) gracias a fusionar 3 canales de polling en SSE + ETag/304. Factibilidad sólida (85) sin dependencias externas. Riesgo muy bajo (90) por tener fallback automático a polling. El tiempo estimado (1-2 semanas) es la principal desventaja frente a C-E1 y B-E1. Aun así, su promesa de rendimiento es inigualable.

## Descartes

- **A-E2 (81.50)** — Buena pero eclipsada por C-E1 que cubre APCu de forma más limpia y rápida. Las Materialized Views añaden complejidad innecesaria en esta fase.
- **A-E3 (84.50)** — Sólida, pero B-E1 y C-E2 cubren el mismo terreno con mayor factibilidad y menor esfuerzo respectivamente.
- **A-E4 (62.25)** — Dependencia de cron y JSON estáticos. Alto riesgo de datos obsoletos. Demasiado compleja para el beneficio.
- **A-E5 (53.25)** — LISTEN/NOTIFY requiere pgbouncer + conexiones persistentes. Alto riesgo de consumo de conexiones. Implementación frágil.
- **A-E6 (47.50)** — Reemplazar Chart.js por SVG server-side y rediseñar con Web Components es excesivo. Mucho esfuerzo para mejora marginal en servidor.
- **B-E2 (81.25)** — Buena propuesta de despollution, pero A-E1 cubre lo mismo con más impacto y menos complejidad.
- **B-E3 (76.75)** — Tres capas de caché es sobrediseño para la escala actual. C-E1 ofrece 80% del beneficio con 20% del esfuerzo.
- **B-E4 (48.50)** — Worker PHP persistente es frágil en Apache compartido. FIFO/Redis introduce dependencias no autorizadas.
- **B-E5 (48.50)** — Pipeline de build (PurgeCSS, terser) viola restricción de "sin frameworks". Beneficio principalmente frontend.
- **B-E6 (31.25)** — Read/write splitting + PgBouncer es cambio de arquitectura. Injustificable para la escala actual. Riesgo altísimo.
- **C-E2 (83.25)** — Refactor quirúrgico sólido pero incremental. Menor impacto que B-E1 que cubre las mismas optimizaciones.
- **C-E3 (86.50)** — Excelente factibilidad y esfuerzo (1 día), pero el impacto en rendimiento backend es marginal. Buena para fase 2.
- **C-E4 (47.25)** — Event-driven con NOTIFY + demonio PHP. Frágil, complejo, riesgo alto. Violenta las restricciones del proyecto.
- **C-E5 (62.50)** — Materialized Views con refresco periódico. Útil pero introduce latencia de datos y complejidad de schema.
- **C-E6 (51.50)** — Service Worker requiere HTTPS y tiene comportamiento impredecible en caché. Violación de restricciones.

## Observaciones Generales

1. **Patrón dominante:** Las 3 mejores propuestas (C-E1, B-E1, A-E1) siguen el mismo patrón: bajo riesgo, implementación incremental, sin nuevas dependencias externas. Esto confirma que el meta-juez #3 prioriza correctamente la estabilidad sobre el impacto teórico.

2. **Sinergias claras:** C-E1 (APCu) + B-E1 (índices) son complementarios y pueden implementarse en paralelo por distintos desarrolladores. A-E1 (SSE + ETag) sería la fase 2 natural tras estabilizar la caché y las queries.

3. **Propuestas sobreingenierizadas:** 7 de 18 propuestas (A-E4, A-E5, A-E6, B-E4, B-E5, B-E6, C-E4, C-E5, C-E6) proponen cambios arquitectónicos mayores. Ninguna entra al Top 5. Esto sugiere que el sistema OTI no necesita rearquitectura sino optimización focalizada.

4. **Recomendación para expansión:** Implementar C-E1 (día 1-2) → B-E1 (día 3-7) → medir → A-E1 (día 8-14). Esta secuencia maximiza beneficio/riesgo en cada paso y permite decidir si A-E1 es necesaria después de ver el impacto real de C-E1 + B-E1.

5. **Deuda técnica:** Ninguna propuesta del Top 3 genera deuda técnica significativa. Todas son completamente reversibles. Esto es consistente con el perfil conservador del Juez #3.
