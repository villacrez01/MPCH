# Poda #1 — Juez de Evaluación y Ranking

**Juez:** Pruning Judge #1  
**Artefacto:** `proposals` (18 propuestas de 3 exploradores)  
**Fecha:** 2026-05-21  
**Top-N solicitado:** 3  
**Criterio de desempate:** `impacto_riesgo_residual`

---

## VOTO

VOTE:
  FIRST: "Cirugía de Seguridad + Optimización Selectiva" (Explorer A - E1)
  SECOND: "Hardening + Optimización Dirigida" (Explorer B - E1)
  THIRD: "Optimización Progresiva — Refactor por Módulos + Feature Flags" (Explorer C - E3)

---

## PUNTUACIONES GLOBALES

SCORES:
  A_E1_Cirugia_Seguridad: 77.5/100
  A_E2_Refactor_Front_Controller: 64.75/100
  A_E3_Optimizacion_Rendimiento: 56.25/100
  A_E4_HTMX: 33.0/100
  A_E5_Seguridad_Perimetral: 31.25/100
  A_E6_ADR_PHP84: 44.5/100
  B_E1_Hardening: 74.5/100
  B_E2_MVC_Servicios: 64.25/100
  B_E3_Performance_Asset: 45.75/100
  B_E4_Framework_Propio: 44.0/100
  B_E5_API_SPA: 29.0/100
  B_E6_DDD_EventSourcing: 25.0/100
  C_E1_Escudo_Defensivo: 65.5/100
  C_E2_Refactorizacion_Estructural: 62.5/100
  C_E3_Optimizacion_Progresiva_Modulos: 71.75/100
  C_E4_Zero_Rewrite: 43.25/100
  C_E5_Cirugia_Digital: 51.5/100
  C_E6_Hibrido_Empresarial: 34.25/100

---

## PUNTUACIONES POR CRITERIO

### Explorer A — Propuestas

| Criterio (peso) | A-E1 | A-E2 | A-E3 | A-E4 | A-E5 | A-E6 |
|---|---|---|---|---|---|---|
| alineacion_requisitos (20) | 80 | 50 | 45 | 30 | 35 | 80 |
| factibilidad_tecnica (25) | 90 | 85 | 80 | 40 | 30 | 20 |
| potencial_calidad (20) | 65 | 65 | 50 | 40 | 30 | 80 |
| gestion_riesgo (20) | 70 | 65 | 60 | 30 | 35 | 15 |
| esfuerzo_impacto (15) | 80 | 50 | 35 | 20 | 25 | 30 |
| **Total ponderado** | **77.5** | **64.75** | **56.25** | **33.0** | **31.25** | **44.5** |

### Explorer B — Propuestas

| Criterio (peso) | B-E1 | B-E2 | B-E3 | B-E4 | B-E5 | B-E6 |
|---|---|---|---|---|---|---|
| alineacion_requisitos (20) | 75 | 55 | 40 | 70 | 40 | 30 |
| factibilidad_tecnica (25) | 90 | 80 | 50 | 35 | 20 | 15 |
| potencial_calidad (20) | 60 | 70 | 45 | 60 | 50 | 55 |
| gestion_riesgo (20) | 65 | 55 | 55 | 20 | 15 | 10 |
| esfuerzo_impacto (15) | 80 | 55 | 35 | 35 | 20 | 15 |
| **Total ponderado** | **74.5** | **64.25** | **45.75** | **44.0** | **29.0** | **25.0** |

### Explorer C — Propuestas

| Criterio (peso) | C-E1 | C-E2 | C-E3 | C-E4 | C-E5 | C-E6 |
|---|---|---|---|---|---|---|
| alineacion_requisitos (20) | 60 | 55 | 70 | 85 | 50 | 55 |
| factibilidad_tecnica (25) | 95 | 80 | 75 | 15 | 50 | 25 |
| potencial_calidad (20) | 40 | 70 | 70 | 80 | 55 | 55 |
| gestion_riesgo (20) | 60 | 50 | 65 | 10 | 45 | 15 |
| esfuerzo_impacto (15) | 65 | 50 | 80 | 30 | 60 | 20 |
| **Total ponderado** | **65.5** | **62.5** | **71.75** | **43.25** | **51.5** | **34.25** |

---

## RANKING COMPLETO (18 propuestas ordenadas)

| # | Propuesta | Puntaje | Explorador |
|---|-----------|---------|------------|
| 1 | **Cirugía de Seguridad + Optimización Selectiva** | **77.50** | A-E1 |
| 2 | **Hardening + Optimización Dirigida** | **74.50** | B-E1 |
| 3 | **Optimización Progresiva — Refactor por Módulos + Feature Flags** | **71.75** | C-E3 |
| 4 | Escudo Defensivo — Parcheo Quirúrgico | 65.50 | C-E1 |
| 5 | Refactor Front Controller + Middleware Pipeline | 64.75 | A-E2 |
| 6 | MVC Formal con Capa de Servicios | 64.25 | B-E2 |
| 7 | Refactorización Estructural — Arquitectura en Capas | 62.50 | C-E2 |
| 8 | Optimización Progresiva de Rendimiento + Caching | 56.25 | A-E3 |
| 9 | Cirugía Digital — Micro-fixes Automatizados + KPI Calidad | 51.50 | C-E5 |
| 10 | Performance + Asset Pipeline | 45.75 | B-E3 |
| 11 | Migración a PHP 8.4 Puro con ADR Pattern | 44.50 | A-E6 |
| 12 | Framework Propio con DI y Eventos | 44.00 | B-E4 |
| 13 | Zero Trust Rewrite — Reescribir desde Cero | 43.25 | C-E4 |
| 14 | Híbrido Empresarial — Framework Propietario + API | 34.25 | C-E6 |
| 15 | Reescritura Progresiva con HTMX | 33.00 | A-E4 |
| 16 | Seguridad Perimetral + Micro-Segmentación APIs | 31.25 | A-E5 |
| 17 | API First + SPA Progresivo | 29.00 | B-E5 |
| 18 | DDD Táctico + Event Sourcing Lite | 25.00 | B-E6 |

---

## EVALUACIÓN DETALLADA

### 🥇 1er Lugar: A-E1 — Cirugía de Seguridad + Optimización Selectiva (77.50)

**Alineación con Requisitos (80/100):** Cubre los 25 CRÍTICOS (XSS, CSRF, auth bypass, SQLi potencial, CORS abierto, sesiones inseguras) y la mayoría de los 44 HIGH (error disclosure, N+1 queries, índices, contraseña hardcodeada). Aborda 4 de 6 dominios (seguridad, bugs, rendimiento) con profundidad y 2 parcialmente (CSS/JS). No cubre accesibilidad. Es la propuesta más completa en cobertura de severidades críticas.

**Factibilidad Técnica (90/100):** 100% realizable con el stack actual. Usa APCu (extensión PHP opcional), PDO sin cambios, sesiones PHP estándar, vanilla JS. Sin npm/webpack. Las migraciones de BD son índices y consultas SQL — sin cambios de esquema. El `SecurityMiddleware` se inyecta en `index.php` sin reestructurar. Puntualiza exactamente qué archivos modificar y cómo.

**Potencial de Calidad (65/100):** La calidad del resultado es predecible para seguridad y rendimiento. Incluye `e()` wrapper para escape consistente, `InputValidator` con métodos tipados, `SessionManager` con timeout absoluto. Sin embargo, no propone estrategia de pruebas, ni linting, ni logging estructurado. Las correcciones CSS/JS mejoran parcialmente pero sin estándar definido.

**Gestión de Riesgo (70/100):** Identifica riesgos específicos: CSP nonce puede romper scripts si se omite uno, rate limiter agresivo puede bloquear usuarios legítimos, índices nuevos afectan writes. Propone mitigaciones concretas. Sin embargo, no contempla feature flags, staging environment, ni plan de monitoreo post-despliegue. Los cambios son reversibles individualmente.

**Esfuerzo/Impacto (80/100):** 2-3 semanas para resolver ~70 issues (25 CRÍTICOS + 44 HIGH parcial). Las correcciones de XSS/CSRF/auth bypass son directas (middleware, no re-arquitectura). Las queries N+1 se consolidan con `FILTER` — cambio localizado. Los índices son migración SQL única. Excelente relación impacto/esfuerzo: resuelve lo más crítico sin tocar lo que funciona.

**Riesgo residual:** Bajo. Los 25 CRÍTICOS se cierran con cambios localizados y reversibles. Persisten issues de accesibilidad y CSS/JS, pero no son bloqueantes para producción.

---

### 🥈 2do Lugar: B-E1 — Hardening + Optimización Dirigida (74.50)

**Alineación con Requisitos (75/100):** Cubre los 25 CRÍTICOS con CSRF middleware, unificación de auth, CORS restrictivo. Incluye N+1 queries vía `FILTER` y error handling sanitizado. Su fase 4 (UX) aborda CSS responsive, dark mode y accesibilidad — pero en fase tardía. Cobertura similar a A-E1 pero con 1 dominio adicional planeado (accesibilidad en fase 4), aunque sin ejecución garantizada.

**Factibilidad Técnica (90/100):** Igual que A-E1: sin dependencias externas, PDO preservado, sesiones PHP estándar. Las 4 fases son secuenciales y no requieren cambios de infraestructura. La unificación de `AuthService::isAdmin()` elimina 7 duplicaciones sin cambiar comportamiento.

**Potencial de Calidad (60/100):** Menor que A-E1. El wrapper JSON para errores es bueno (nunca expone `$e->getMessage()`). Propone eliminar código duplicado y agregar tipado en fase 3. Sin embargo, no hay estrategia de pruebas, logging estructurado, ni estándar de código definido. La fase 4 (UX) es ambiciosa pero sin detalles de implementación.

**Gestión de Riesgo (65/100):** Riesgos identificados: CSRF puede romper formularios, CORS whitelist mal configurado puede caer APIs, falsa sensación de seguridad. No propone feature flags, staging, ni plan de monitoreo. Las mitigaciones son conceptuales, no detalladas. El enfoque por fases reduce riesgo de despliegue masivo.

**Esfuerzo/Impacto (80/100):** 1-2 semanas para fase 1+2 (seguridad + optimización). Las 4 fases distribuyen el trabajo en prioridad. Similar a A-E1 en rapidez para CRÍTICOS. La fase 3 (calidad) y 4 (UX) añaden valor incremental. Esfuerzo eficiente para resolución de CRÍTICOS.

**Riesgo residual:** Bajo-medio. Similar a A-E1 pero con menos cobertura de CSS/JS en fases tempranas. La fase 4 podría no ejecutarse, dejando accesibilidad pendiente.

---

### 🥉 3er Lugar: C-E3 — Optimización Progresiva — Refactor por Módulos + Feature Flags (71.75)

**Alineación con Requisitos (70/100):** Sprint 0 cubre los 25 CRÍTICOS (CSRF, XSS, CORS, auth bypass) con parches inmediatos. Sprints 1-3 refactorizan módulos (Tickets, Usuarios, Equipos) abordando HIGH/MEDIUM de código, tipos y validación. CSS/JS se abordan parcialmente dentro de cada módulo. Accesibilidad no se menciona explícitamente. Su fortaleza es la progresividad: seguridad inmediata + mejora estructural gradual.

**Factibilidad Técnica (75/100):** Los feature flags en `.env` añaden complejidad operativa, pero son una técnica probada y liviana. El `BaseController` es una abstracción simple. La coexistencia V1/V2 con vistas en `app/Views/v2/` es pragmática. Sin nuevas dependencias. El fallback automático (`if V2 throws → redirect to V1`) requiere try-catch en cada punto de entrada. Más complejo que A-E1/B-E1 pero factible.

**Potencial de Calidad (70/100):** El `BaseController` con métodos `json()`, `view()`, `validate()`, `csrf()` estandariza el código. La refactorización módulo a módulo permite probar cada unidad. El fallback automático evita que errores en V2 afecten al usuario. Sin embargo, mantener V1 y V2 simultáneamente duplica superficie de prueba. No hay estrategia de pruebas unitarias explícita.

**Gestión de Riesgo (65/100):** Es la propuesta con mejor gestión de riesgo entre las top 3. Los feature flags son el mecanismo de mitigación más sólido: permiten despliegue gradual, rollback instantáneo (cambiar flag), y aislamiento de fallos. Identifica el riesgo de flags olvidados en producción y propone fecha de expiración. El fallback V1→V2 es ingenioso. Checklist de portabilidad por sprint. Podría mejorar con un plan de monitoreo post-despliegue.

**Esfuerzo/Impacto (80/100):** 4 semanas total: Sprint 0 (seguridad) en días, luego 1 semana por módulo. Resuelve CRÍTICOS inmediatamente y ALTO/MEDIO progresivamente. Los feature flags permiten deployar cada módulo independientemente. La duplicación temporal de código (V1+V2) aumenta esfuerzo en mantenimiento, pero el aislamiento compensa. Buena relación impacto/esfuerzo con gestión de riesgo superior.

**Riesgo residual:** Medio. La seguridad se resuelve rápido (menor riesgo que A-E3 que no toca seguridad). La coexistencia V1/V2 introduce riesgo de inconsistencia funcional. Los feature flags olvidados podrían dejar código legacy activo. Sin embargo, los mecanismos de mitigación son superiores a los de A-E1 y B-E1.

---

## ANÁLISIS DE DESEMPATE

El criterio de desempate es `impacto_riesgo_residual`. Entre los tres finalistas:

| Propuesta | Riesgo residual | Justificación |
|-----------|----------------|---------------|
| A-E1 | Bajo | 25 CRÍTICOS resueltos con parches localizados y reversibles. CSS/JS/Accesibilidad no resueltos pero no bloquean producción. |
| B-E1 | Bajo-Medio | Similar a A-E1 pero fase de UX/Accesibilidad planeada (no garantizada). Mayor incertidumbre en fases tardías. |
| C-E3 | Medio | Seguridad resuelta rápido pero la coexistencia V1/V2 introduce riesgos de inconsistencia. Mecanismos de mitigación superiores. |

**A-E1 gana el desempate** por tener el menor riesgo residual: sus cambios son los más localizados y reversibles, y su cobertura de issues CRÍTICOS+HIGH es la más completa sin introducir arquitectura dual.

---

## MAPA DE COBERTURA POR DOMINIO (Top 3)

| Dominio | A-E1 | B-E1 | C-E3 |
|---------|------|------|------|
| **Seguridad** (25 CRÍTICOS) | ✅ Completo | ✅ Completo | ✅ Completo (Sprint 0) |
| **Bugs/Error Handling** (44 HIGH) | ✅ Mayoría | ✅ Mayoría | ✅ Progresivo |
| **Rendimiento** (N+1, caché) | ✅ FILTER + APCu + índices | ✅ FILTER + índices | ⚠️ Parcial (en módulos) |
| **CSS** (app.css 69KB) | ⚠️ División parcial | ⚠️ Fase 4 pendiente | ⚠️ Parcial en módulos |
| **JS** (realtime, charts) | ⚠️ AbortController + console.log | ⚠️ Minificación | ⚠️ Parcial en módulos |
| **Accesibilidad** (32 LOW) | ❌ No cubierto | ⚠️ Fase 4 planeada | ❌ No mencionado |

---

## NOTAS ADICIONALES

1. **A-E1 y B-E1 son convergente-similares:** Ambos proponen esencialmente la misma estrategia (seguridad quirúrgica + optimización N+1). A-E1 gana por su cobertura adicional de APCu cache, división de CSS, y `BASE_URL` dinámica. La diferencia es ~3 puntos, ambos son viables.

2. **C-E3 es el más innovador:** Los feature flags con fallback automático son la mejor gestión de riesgo de toda la tanda. Sin embargo, la probabilidad reportada por su explorador (0.78) vs los otros E1 (0.92) sugiere que el mismo autor reconoce mayor incertidumbre.

3. **Los A-E3 y B-E3 (rendimiento puro) penalizan por omitir seguridad:** Aunque tienen alta probabilidad individual (0.88, 0.88), no abordan ninguno de los 25 CRÍTICOS. Son complementos, no soluciones independientes.

4. **Ninguna propuesta de baja probabilidad (<0.10) supera el umbral de selección:** Todas puntúan por debajo de 52. Esto valida que el meta-juez asignó probabilidades correctamente.

5. **A-E6 (ADR PHP 8.4) tiene la nota más alta en alineación (80) y calidad (80) fuera del top 3** pero su factibilidad (20) y riesgo (15) la hunden. Sería ideal si hubiera tiempo y presupuesto.

---

## CONCLUSIÓN

Las 3 propuestas seleccionadas representan el mejor balance entre cobertura de issues críticos, factibilidad técnica con el stack actual, y gestión de riesgo:

| Orden | Propuesta | Estrategia | Timeline | Riesgo Residual |
|-------|-----------|-----------|----------|-----------------|
| 🥇 | A-E1: Cirugía de Seguridad + Optimización Selectiva | Parche quirúrgico + N+1 fixes + APCu | 2-3 semanas | Bajo |
| 🥈 | B-E1: Hardening + Optimización Dirigida | 4 fases: seguridad → optimización → calidad → UX | 1-2 semanas fase 1-2 | Bajo-Medio |
| 🥉 | C-E3: Refactor por Módulos + Feature Flags | Sprint 0 seguridad + refactor modular con flags | 4 semanas | Medio |

**Recomendación:** Implementar A-E1 como plan base (máxima cobertura con mínimo riesgo). B-E1 como alternativa si el timeline se acorta. C-E3 como plan si se requiere despliegue gradual con capacidad de rollback.
