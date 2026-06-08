# Pruning — Juez 2

## Tabla de Puntuaciones

| Propuesta | Impacto(25) | Factibilidad(25) | Riesgo(20) | Esfuerzo(15) | Reversibilidad(10) | Alineación(5) | Total |
|-----------|:-----------:|:----------------:|:----------:|:------------:|:------------------:|:-------------:|:-----:|
| A-E1 | 95 | 85 | 90 | 70 | 90 | 100 | 87.50 |
| A-E2 | 80 | 70 | 75 | 55 | 70 | 75 | 71.50 |
| A-E3 | 75 | 92 | 75 | 80 | 80 | 100 | 81.75 |
| A-E4 | 60 | 40 | 50 | 40 | 45 | 85 | 49.75 |
| A-E5 | 95 | 25 | 25 | 30 | 35 | 50 | 45.50 |
| A-E6 | 65 | 20 | 30 | 20 | 35 | 65 | 37.00 |
| B-E1 | 72 | 95 | 80 | 85 | 85 | 100 | 84.00 |
| B-E2 | 90 | 80 | 85 | 65 | 80 | 95 | 82.00 |
| B-E3 | 80 | 68 | 75 | 50 | 65 | 75 | 69.75 |
| B-E4 | 92 | 22 | 20 | 20 | 25 | 35 | 39.75 |
| B-E5 | 60 | 35 | 50 | 30 | 45 | 65 | 46.00 |
| B-E6 | 55 | 20 | 15 | 15 | 20 | 25 | 27.25 |
| C-E1 | 85 | 95 | 92 | 90 | 95 | 90 | 90.90 |
| C-E2 | 70 | 95 | 82 | 85 | 85 | 100 | 83.90 |
| C-E3 | 50 | 98 | 95 | 95 | 95 | 100 | 84.75 |
| C-E4 | 88 | 18 | 20 | 18 | 25 | 35 | 37.45 |
| C-E5 | 78 | 50 | 50 | 40 | 45 | 70 | 56.00 |
| C-E6 | 50 | 40 | 45 | 40 | 55 | 55 | 45.75 |

## Top 3

1. **C-E1 (90.90) — Cache First, Polling Last — APCu + Throttling**
   La propuesta con mejor relación impacto/esfuerzo del conjunto completo. APCu con TTLs escalonados elimina la mayoría de queries repetitivas a BD. El throttling triple de polling reduce el volumen de peticiones HTTP. La protección stampede es un detalle de calidad que evita picos. Implementable en 1-2 días con wrapper simple. Cache tree de Location y bandera dirty para SSE mejoran la arquitectura sin complejidad. Único punto débil: requiere APCu (extensión PHP), pero es mínimo.

2. **A-E1 (87.50) — Unificación de Canales en Tiempo Real + Cache Condicional**
   Ataque directo al problema principal: ~1,320 → ~30 queries/min (97.7%). Fusionar 3 canales de polling en un endpoint con ETag/304 es quirúrgico. SSE con tabla cache_heartbeat evita conexiones persistentes complejas. Sin dependencias externas. Riesgo mínimo por ser cambios localizados y completamente reversibles. La justificación probabilística (0.92) es la más alta del conjunto y está bien sustentada.

3. **C-E3 (84.75) — Frontend Slim — Compresión de assets + eliminación de bloqueos**
   Ganancia rápida con mínimo esfuerzo (1 día). Minificar CSS/JS con PHP puro, Critical CSS inline, Google Fonts async, Chart.js lazy load y skeleton loading mejoran significativamente Lighthouse y UX percibida. Aunque no ataca el cuello de botella principal (queries BD), complementa perfectamente las propuestas #1 y #2. Console.log eliminado es un detailas. Riesgo casi nulo.

## Descartes

- **A-E2 (71.50)** — Caché Multinivel: 3 capas es sobrediseño para el problema actual. APCu + MV + HTTP Cache genera 3 puntos de falla. MV requieren mantenimiento DDL. Preferir C-E1 que es más simple y cubre el 80% del beneficio.
- **A-E3 (81.75)** — Quirúrgico Queries: Bueno pero superado por B-E1 y C-E2 que cubren lo mismo con menos complejidad. Queda fuera del Top 3 por margen estrecho.
- **A-E4 (49.75)** — Datos Precalculados: Complejidad alta para beneficio localizado (solo dashboard). Datos stale si cron falla. Injustificable versus C-E1 que da resultados similares con 1/10 del esfuerzo.
- **A-E5 (45.50)** — LISTEN/NOTIFY: Alta probabilidad (0.06) por buena razón. Requiere pgbouncer, worker persistente, triggers BD. Sobredimensionado para el volumen actual del sistema.
- **A-E6 (37.00)** — Static Shell + Islands: Cambio masivo en frontend. Chart.js → SVG server-side es reescritura completa. Riesgo alto de romper funcionalidad existente. No justificado.
- **B-E1 (84.00)** — Consolidación Queries: Sólido pero cubierto en parte por C-E2. Preferir C-E2 por ser más quirúrgico (2-3 días vs 3-5).
- **B-E2 (82.00)** — Despollution: Excelente propuesta. Similar a A-E1 pero con ligeramente menos impacto (no cuantifica reducción). A-E1 gana por precisión.
- **B-E3 (69.75)** — Caché 3 Capas: Misma crítica que A-E2. Sobrediseño. C-E1 cubre la capa APCu que es la de mayor impacto.
- **B-E4 (39.75)** — LISTEN/NOTIFY + Worker: Versión más compleja que A-E5. FIFO/Redis añade dependencia externa. No viable para restricciones del proyecto.
- **B-E5 (46.00)** — Assets Ninja: Pipeline de build es overkill para Vanilla JS. PurgeCSS + terser requieren Node.js, violando restricción de "sin frameworks externos".
- **B-E6 (27.25)** — Particionamiento Vertical: Solución prematura de escalabilidad horizontal. Consistencia eventual incompatible con expectativas del sistema OTI. Costo altísimo.
- **C-E2 (83.90)** — Query Slim Down: Bien ejecutado pero el impacto se limita a queries existentes. No elimina polling. Queda fuera del Top 3 por no atacar el problema principal.
- **C-E4 (37.45)** — Event-Driven + NOTIFY: pcntl_fork/ReactPHP son dependencias externas pesadas. Worker demonio es operacionalmente complejo. No recomendado.
- **C-E5 (56.00)** — CQRS Lite: 6-8 MV es carga de mantenimiento alta. REFRESH CONCURRENTLY compite con escrituras. Datos eventualmente consistentes pueden confundir usuarios.
- **C-E6 (45.75)** — Service Worker: Stale-while-revalidate es útil pero Service Worker requiere HTTPS (posible restricción). Cache API del navegador no reduce carga BD. Mejor priorizar backend primero.

## Observaciones Generales

1. **Patrón claro**: Las 3 propuestas ganadoras atacan problemas diferentes y complementarios: backend cache (C-E1), polling/realtime (A-E1), y frontend assets (C-E3). Implementadas en conjunto cubren los 3 cuellos de botella principales del sistema OTI.

2. **Línea divisoria en ~80 puntos**: Hay un quiebre natural entre propuestas de alto impacto/bajo riesgo (>80) y propuestas complejas/arriesgadas (<70). Recomiendo que la fase de expansión se enfoque exclusivamente en el cluster superior.

3. **Problema de las propuestas de "Tiempo Real"**: Todas las basadas en LISTEN/NOTIFY (A-E5, B-E4, C-E4) obtienen puntajes bajos consistentemente. La tecnología es atractiva pero la complejidad operativa y las dependencias (pgbouncer, workers persistentes) las hacen inviables para un equipo PHP/Apache tradicional. El SSE condicional con cache (A-E1) es la alternativa pragmática.

4. **Sorpresa de C-E3**: Una propuesta puramente frontend (50 en impacto) logra colocarse en Top 3 gracias a puntajes perfectos en factibilidad, riesgo, esfuerzo, reversibilidad y alineación. Esto refleja la importancia de considerar el perfil completo de riesgo-retorno, no solo el impacto bruto.

5. **Reserva sobre C-E5 (CQRS Lite)**: Esta propuesta tiene potencial pero está infravalorada en su probabilidad original (0.07). Las MV con REFRESH CONCURRENTLY podrían ser útiles en una fase posterior, pero no ahora.

6. **Recomendación de orden**: Fase 1 → C-E3 (1 día), Fase 2 → C-E1 (2 días), Fase 3 → A-E1 (5 días). Las tres en ~8 días-hombre totales, implementables por 1 desarrollador sin riesgo significativo.
