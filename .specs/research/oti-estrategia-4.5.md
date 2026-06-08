# Estrategia Adaptativa — Fase 4.5

**Fecha:** 2026-05-21
**Proyecto:** Optimización de Rendimiento OTI

## Resultados de Evaluación (Fase 4)

| Solución | Juez 1 | Juez 2 | Juez 3 | Promedio |
|----------|:------:|:------:|:------:|:--------:|
| **C-E1** — Cache First, Polling Last (APCu + Throttling) | 87.90 | 90.30 | 80.50 | **86.23** |
| **B-E1** — Consolidación de Queries + Índices | 85.35 | 81.70 | 75.50 | **80.85** |
| **A-E1** — Unificación de Canales + ETag | 88.60 | 77.00 | 76.25 | **80.62** |

## Análisis de Varianza

| Solución | Rango | Varianza | Interpretación |
|----------|:-----:|:--------:|----------------|
| C-E1 | 9.80 | Moderada | Todos los jueces la rankean #1, pero Juez 3 (backend/DB expert) fue más conservador con 80.50 |
| A-E1 | 12.35 | Alta | Juez 1 la favoreció (88.60), Jueces 2-3 la penalizaron por riesgo SSE con cURL |
| B-E1 | 9.85 | Moderada | Evaluación consistente, rango estrecho, puntajes sólidos |

## Decisión Estratégica

**Estrategia: FULL_SYNTHESIS**

C-E1 (APCu + Throttling) es el claro ganador con el mejor balance impacto/factibilidad/riesgo. Sin embargo, las 3 soluciones atacan problemas ortogonales del mismo sistema. La síntesis final combinará:

| Componente | Fuente | Prioridad | Justificación |
|------------|--------|:---------:|---------------|
| **APCu** cache wrapper con `remember()` y dirty flags | C-E1 | **Alta** | Núcleo de la solución. Elimina ~90% de queries repetitivas. Implementación en 1-2 días. |
| **Throttling de polling** (realtime.js 30s, analisis 60s) | C-E1 | **Alta** | Complementa APCu. Sin esto, el cache se satura igual. |
| **8 índices compuestos** + pg_trgm GIN | B-E1 | **Alta** | Beneficia a TODAS las queries. Los índices son independientes del cache. |
| **Consolidación de queries** (COUNT FILTER, CTE) | B-E1 | **Media** | Reduce queries individuales. Se puede hacer progresivamente. |
| **ETag/304 en dashboard-poll** | A-E1 | **Media** | Evita enviar payload cuando no hay cambios. Complementa APCu en el endpoint. |
| **SSE con heartbeat ligero** (solo consulta dirty flag) | A-E1/C-E1 | **Media** | Elimina la ejecución de getStats() en el bucle SSE. |
| **session_regenerate_id() condicional** | B-E1 | **Baja** | Cambio simple, bajo impacto. |
| **SSEClient JS unificado** | A-E1 | **Baja** | Refactor frontend, mejora mantenibilidad. |

## Componentes Excluidos y Razón

| Componente | Fuente | Razón de Exclusión |
|------------|--------|---------------------|
| Cache tree de Location en APCu | C-E1 | La CTE recursiva de B-E1 es más segura (datos jerárquicos no deben cachearse sin invalidación precisa) |
| Carga única + Chart.js lazy | A-E1 | Es un cambio frontend significativo; se pospone a fase 2 |
| Service Worker | C-E6 | Requiere HTTPS, no se justifica dado que APCu + ETag logran el mismo resultado |
| LISTEN/NOTIFY | E4/E5 | Complejidad operativa innecesaria; el heartbeat vía dirty flag es suficiente |

## Plan de Síntesis (Fase 5)

La síntesis final combinará las 3 soluciones en un PLAN DE OPTIMIZACIÓN unificado con:
1. **Roadmap por semanas**: Semana 1 (índices + queries) → Semana 2 (APCu + dirty) → Semana 3 (ETag + SSE)
2. **Dependencias entre componentes**: Los índices no dependen de nada; APCu se beneficia de las queries consolidadas
3. **Estimación de impacto acumulativo**: ~1,320 → ~30 queries/min combinando las 3 soluciones
4. **Plan de rollback por fase**: Cada semana es reversible independientemente
5. **Pruebas de verificación**: EXPLAIN ANALYZE antes/después, Lighthouse, monitoreo de queries/min
