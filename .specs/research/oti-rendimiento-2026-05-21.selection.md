# Selección de Propuestas — Tree of Thoughts (Fase 2b)

**Fecha:** 2026-05-21
**Proyecto:** Optimización de Rendimiento OTI

## Resultados del Pruning

Tres jueces independientes evaluaron las 18 propuestas usando 6 criterios ponderados (Impacto 25%, Factibilidad 25%, Riesgo 20%, Esfuerzo 15%, Reversibilidad 10%, Alineación 5%).

### Votación por Ranked Choice (3-2-1 pts)

| Juez | 1º (3 pts) | 2º (2 pts) | 3º (1 pt) |
|------|:----------:|:----------:|:---------:|
| Juez 1 | C-E1 | A-E1 | B-E1 |
| Juez 2 | C-E1 | A-E1 | C-E3 |
| Juez 3 | C-E1 | B-E1 | A-E1 |

### Puntuación Final

| Propuesta | Pts J1 | Pts J2 | Pts J3 | Total |
|-----------|:------:|:------:|:------:|:-----:|
| **C-E1** — Cache First, Polling Last (APCu + Throttling) | 3 | 3 | 3 | **9** |
| **A-E1** — Unificación Canales + Cache Condicional (ETag/304) | 2 | 2 | 1 | **5** |
| **B-E1** — Consolidación Queries + Índices | 1 | 0 | 2 | **3** |
| C-E3 — Frontend Slim (compresión assets) | 0 | 1 | 0 | 1 |

### Top 3 Seleccionadas para Expansión

| # | ID | Propuesta | Puntaje Promedio | Estrategia |
|---|----|-----------|:----------------:|------------|
| 1 | **C-E1** | Cache First, Polling Last — APCu + Throttling | 90.80 | Cache en aplicación + reducción de frecuencia |
| 2 | **A-E1** | Unificación de Canales + Cache Condicional (ETag/304) | 88.00 | Fusión de 3 polls en 1 endpoint con ETag |
| 3 | **B-E1** | Consolidación de Queries + Estrategia de Índices | 86.42 | SQL óptimo + 8 índices compuestos |

### Propuestas Descartadas

| Propuesta | Puntaje Promedio | Razón |
|-----------|:----------------:|-------|
| C-E3 — Frontend Slim | 85.92 | Alta puntuación pero solo afecta frontend; el 80% del problema es backend |
| A-E3 — Quirúrgico Queries | 84.00 | Similar a B-E1 pero menos completa |
| C-E2 — Query Slim Down | 83.88 | Solapamiento con B-E1 |
| B-E2 — Despollution | 82.42 | Similar a A-E1 pero menos detallada |
| A-E2 — Caché Multinivel | 77.50 | Similar a C-E1 pero más compleja |
| B-E3 — Caché 3 Capas | 74.17 | Similar a C-E1 + A-E2 |
| C-E5 — CQRS Lite + MV | 58.42 | Complejidad alta para el impacto marginal |
| C-E6 — Service Worker + Gateway | 51.58 | Requiere HTTPS, depuración compleja |
| A-E4 — Datos Precalculados | 57.58 | Alta complejidad, datos eventualmente consistentes |
| B-E5 — Assets Ninja | 47.25 | Solapamiento con C-E3, requiere Node.js |
| A-E5 — LISTEN/NOTIFY Push | 48.17 | Riesgo alto, complejidad operativa |
| C-E4 — Event-Driven Backend | 40.57 | Similar a A-E5, requiere proceso demonio |
| A-E6 — Static Shell + Islands | 42.33 | Muy alta complejidad, reescritura frontend |
| B-E4 — LISTEN/NOTIFY + Worker | 43.08 | Riesgo alto, pg_get_notify en producción |
| B-E6 — Read/Write Split | 29.08 | Muy alta complejidad, poco impacto directo |
