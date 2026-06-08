# Pruning — Juez 1

## Tabla de Puntuaciones

| Propuesta | Impacto(25) | Factibilidad(25) | Riesgo(20) | Esfuerzo(15) | Reversibilidad(10) | Alineación(5) | Total |
|-----------|:-----------:|:----------------:|:----------:|:------------:|:------------------:|:-------------:|:-----:|
| A-E1 | 95 | 85 | 90 | 75 | 90 | 100 | 88.25 |
| A-E2 | 85 | 75 | 85 | 70 | 80 | 80 | 79.50 |
| A-E3 | 80 | 90 | 85 | 85 | 85 | 100 | 85.75 |
| A-E4 | 70 | 50 | 60 | 50 | 70 | 85 | 60.75 |
| A-E5 | 85 | 30 | 30 | 30 | 40 | 50 | 45.75 |
| A-E6 | 65 | 25 | 35 | 25 | 50 | 85 | 42.50 |
| B-E1 | 80 | 95 | 85 | 85 | 85 | 100 | 87.00 |
| B-E2 | 90 | 80 | 85 | 75 | 85 | 95 | 84.00 |
| B-E3 | 85 | 70 | 80 | 65 | 75 | 80 | 76.00 |
| B-E4 | 85 | 25 | 25 | 20 | 35 | 40 | 41.00 |
| B-E5 | 60 | 35 | 50 | 30 | 60 | 60 | 47.25 |
| B-E6 | 70 | 15 | 15 | 10 | 20 | 20 | 28.75 |
| C-E1 | 85 | 95 | 90 | 95 | 90 | 90 | 90.75 |
| C-E2 | 75 | 90 | 85 | 85 | 85 | 100 | 84.50 |
| C-E3 | 65 | 95 | 90 | 95 | 95 | 95 | 86.50 |
| C-E4 | 85 | 20 | 20 | 15 | 30 | 30 | 37.00 |
| C-E5 | 80 | 45 | 55 | 40 | 50 | 70 | 56.75 |
| C-E6 | 65 | 50 | 55 | 50 | 65 | 75 | 57.50 |

## Top 3

1. **C-E1 (90.75)** — Cache First, Polling Last. APCu con TTLs escalonados + throttling de polling triple + bandera dirty para SSE. Máxima puntuación por su excepcional relación impacto/esfuerzo: implementable en 1-2 días por 1 desarrollador, riesgo mínimo (stampede protection incluida), y reduce drásticamente la carga de BD. El enfoque de "caché primero" ataca la causa raíz del problema de rendimiento.

2. **A-E1 (88.25)** — Unificación de Canales en Tiempo Real + Cache Condicional. Fusión de 3 canales de polling en un único endpoint con ETag/304 + SSE ligero. Impacto estimado del 97.7% de reducción de queries de polling (~1,320 → ~30 queries/min). Sin dependencias externas, cambios localizados en PHP + JS. Puntúa perfecto en alineación con restricciones del proyecto.

3. **B-E1 (87.00)** — Consolidación de Queries + Estrategia de Índices. Refactor quirúrgico de queries: stats.php de 11→2 queries con COUNT(*) FILTER, CTE recursiva para Location, 8 índices compuestos CONCURRENTLY. Sin dependencias, 3-5 días de trabajo. Complemento ideal de las propuestas de caché (C-E1, A-E1) para un enfoque combinado.

## Descartes

- **A-E2 (79.50)** — Caché Multinivel. Buena propuesta pero solapada con C-E1 (más simple y mejor puntuada). Requiere APCu + mod_cache, lo que añde dependencias innecesarias cuando C-E1 logra el mismo fin con solo APCu.

- **A-E3 (85.75)** — Optimización Quirúrgica de Queries. Excelente propuesta, muy similar a B-E1 (87.00). Se descarta en favor de B-E1 por tener una estrategia de índices más completa y mejor factibilidad.

- **A-E4 (60.75)** — Dashboard Sin Queries. Arquitectura frágil (JSON estáticos desde cron), alta probabilidad de datos obsoletos. El esfuerzo no justifica el beneficio limitado a solo el dashboard.

- **A-E5 (45.75)** — PostgreSQL LISTEN/NOTIFY. Alta complejidad técnica, requiere pgbouncer, conexiones persistentes. Riesgo alto de regresiones y degradación. No alineado con la restricción de cambios reversibles.

- **A-E6 (42.50)** — Frontend Ultraligero. Reemplazar Chart.js + Font Awesome por SVG server-side es un cambio masivo. Esfuerzo desproporcionado para el beneficio. Riesgo de romper funcionalidad visual existente.

- **B-E2 (84.00)** — Despollution. Buena propuesta pero redundante con A-E1 (88.25) que cubre el mismo problema con mejor impacto y menor complejidad. La tabla change_log añade complejidad innecesaria.

- **B-E3 (76.00)** — Caché en Tres Capas. Similar a A-E2 pero con menor puntuación. La triple capa (APCu + HTTP + Materialized Views) es sobrediseñada para las necesidades actuales. C-E1 es más pragmático.

- **B-E4 (41.00)** — PostgreSQL Listen/Notify + Worker. El worker PHP persistente con FIFO/Redis es una desviación significativa de la arquitectura actual. Alto riesgo, difícil reversibilidad. Violenta la restricción de infraestructura mínima.

- **B-E5 (47.25)** — Assets Ninja. Requiere pipeline de build (PurgeCSS, terser) que no existe actualmente. Cambio cultural además de técnico. C-E3 logra beneficios similares con PHP puro sin build step.

- **B-E6 (28.75)** — Particionamiento Vertical + Balanceo. La propuesta más compleja y riesgosa. Requiere PgBouncer, reescritura de Database.php, consistencia eventual. Puntúa más bajo en casi todos los criterios.

- **C-E2 (84.50)** — Query Slim Down. Propuesta sólida pero superada por B-E1 (87.00) que ofrece un refactor más completo con mejor estrategia de índices.

- **C-E3 (86.50)** — Frontend Slim. Excelente relación esfuerzo/beneficio (1 día de trabajo). Se descarta del Top 3 porque su impacto es puramente frontend (Lighthouse, payload) y no ataca el problema principal de rendimiento del servidor/queries. Sin embargo, se recomienda como implementación complementaria.

- **C-E4 (37.00)** — Event-Driven Backend con NOTIFY. La más compleja de las propuestas NOTIFY. pcntl_fork/ReactPHP son tecnologías no utilizadas en el proyecto. Riesgo muy alto.

- **C-E5 (56.75)** — CQRS Lite con Materialized Views. REFRESH CONCURRENTLY cada 30-60s introduce latencia no determinista. La complejidad de mantener 6-8 MV sincronizadas no se justifica.

- **C-E6 (57.50)** — Service Worker + API Gateway. Service Worker requiere HTTPS, que puede no estar configurado. API Gateway añade una capa que no existe. Beneficio marginal comparado con el esfuerzo.

## Observaciones Generales

**Patrón dominante:** Las 3 propuestas del Top 3 (C-E1, A-E1, B-E1) comparten un enfoque pragmático: cambios localizados, sin nuevas dependencias, implementables en ≤1 semana. Esto valida que la estrategia óptima para OTI es la optimización incremental y reversible, no la rearquitectura.

**Sinergias detectadas:** C-E1 + A-E1 + B-E1 forman una combinación perfecta: C-E1 ataca el cuello de botella principal (falta de caché), A-E1 elimina el polling redundante, y B-E1 optimiza las queries existentes. Implementados en conjunto podrían lograr una reducción del ~95% en queries/minuto y una mejora de Lighthouse de +30-40 puntos.

**Propuestas a evitar (Alto Riesgo):** Las 4 propuestas basadas en LISTEN/NOTIFY (A-E5, B-E4, C-E4) y el balanceo RW (B-E6) fueron consistentemente las peor evaluadas. Requieren cambios fundamentales en la arquitectura, dependencias externas, y presentan alta probabilidad de regresiones.

**Recomendación de implementación:** C-E3 (Frontend Slim, 1 día) debería implementarse como "quick win" inicial mientras se planifican C-E1 + A-E1 + B-E1. Esta secuencia maximiza el ROI y permite medir resultados incrementales.

**Área no cubierta:** Ninguna propuesta aborda específicamente la configuración de Apache (compresión Brotli, KeepAlive, mod_expires) ni PostgreSQL (shared_buffers, work_mem). Esto podría ser considerado en la fase de expansión como complemento de bajo esfuerzo.
