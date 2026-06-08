# Especificación Meta-Juez — Fase 3.5 → Fase 4

**Fecha:** 2026-05-21
**Propósito:** Preparar la rúbrica, plantillas, hoja de agregación y checklist para que 3 jueces evalúen las soluciones expandidas de Fase 3.

---

## 1. Especificación de Evaluación para Fase 4

### 1.1 Criterio 1: Alineación con requisitos (20%)

**Sub-criterios:**
- CR1.1 Cobertura de issues CRÍTICOS: ¿La solución aborda los 25 issues marcados como críticos?
- CR1.2 Preservación funcional: ¿La solución mantiene toda la funcionalidad existente del sistema?
- CR1.3 Fidelidad a restricciones: ¿Respeta las restricciones (sin frameworks, vanilla JS, PostgreSQL, sin librerías externas)?
- CR1.4 Completitud de requisitos: ¿Cubre requisitos secundarios y no funcionales además de los críticos?

**Escala 0–10:**
| Valor | Descriptor |
|-------|-----------|
| 0 | Ignora requisitos, rompe funcionalidad crítica |
| 3 | Cubre <50% de críticos, pierde funcionalidad |
| 6 | Cubre ≥75% de críticos, preserva lo esencial |
| 9 | Cubre 100% críticos, preserva todo, respeta restricciones |

**Preguntas guía:**
- ¿Hay algún issue CRÍTICO que esta solución no toca?
- ¿Alguna funcionalidad actual se perdería con esta solución?
- ¿Introduce dependencias externas o frameworks prohibidos?
- ¿La solución aborda issues no críticos que deberían diferirse?

---

### 1.2 Criterio 2: Factibilidad técnica (25%)

**Sub-criterios:**
- FT2.1 Claridad de implementación: ¿Los pasos son concretos y accionables?
- FT2.2 Estimación realista: ¿Los tiempos y esfuerzos asignados son creíbles?
- FT2.3 Dependencias y orden: ¿Las tareas están bien secuenciadas?
- FT2.4 Compatibilidad con base actual: ¿Se integra sin requerir reescritura total?

**Escala 0–10:**
| Valor | Descriptor |
|-------|-----------|
| 0 | Imposible de implementar, depende de tecnología no disponible |
| 3 | Implementación vaga, pasos ambiguos, integración dudosa |
| 6 | Plan claro, estimaciones razonables, integración factible |
| 9 | Plan detallado, estimaciones precisas, integración directa y limpia |

**Preguntas guía:**
- ¿Un desarrollador podría seguir la implementación sin ambigüedad?
- ¿Las estimaciones consideran pruebas, revisión y rollout?
- ¿Hay pasos que requieren conocimiento experto no documentado?
- ¿La solución requiere cambios en infraestructura o terceros?

---

### 1.3 Criterio 3: Potencial de calidad (20%)

**Sub-criterios:**
- PQ3.1 Mejora de seguridad: ¿Reduce significativamente la superficie de ataque?
- PQ3.2 Mejora de rendimiento: ¿Optimiza cuellos de botella identificados?
- PQ3.3 Calidad de código: ¿Mejora mantenibilidad, legibilidad, testing?
- PQ3.4 UX y DX: ¿Mejora experiencia de usuario y desarrollador?

**Escala 0–10:**
| Valor | Descriptor |
|-------|-----------|
| 0 | Degrada calidad, introduce deuda técnica |
| 3 | Mejora marginal en 1-2 áreas, ignora el resto |
| 6 | Mejora significativa en seguridad y rendimiento, código más limpio |
| 9 | Transformación integral: seguro, rápido, mantenible, testeable |

**Preguntas guía:**
- ¿Cuántos de los 25 issues críticos quedan resueltos versus mitigados?
- ¿Las optimizaciones de rendimiento tienen métricas objetivo?
- ¿La solución mejora la testabilidad o añade cobertura?
- ¿El código resultante es más fácil de entender y modificar?

---

### 1.4 Criterio 4: Gestión de riesgo (20%)

**Sub-criterios:**
- GR4.1 Identificación de riesgos: ¿La solución documenta riesgos conocidos?
- GR4.2 Mitigación: ¿Propone contramedidas concretas para cada riesgo?
- GR4.3 Rollback / reversibilidad: ¿Se puede deshacer parcial o totalmente?
- GR4.4 Deuda técnica residual: ¿Qué problemas quedan sin resolver?

**Escala 0–10:**
| Valor | Descriptor |
|-------|-----------|
| 0 | Sin análisis de riesgo, cambios irreversibles |
| 3 | Riesgos mencionados pero sin plan de mitigación |
| 6 | Riesgos identificados con mitigaciones, rollout gradual posible |
| 9 | Riesgos documentados, mitigaciones probadas, rollback garantizado, deuda residual aceptable |

**Preguntas guía:**
- ¿Qué pasa si una fase falla en producción?
- ¿La solución permite feature flags o despliegue gradual?
- ¿El plan incluye monitoreo post-despliegue?
- ¿La deuda técnica remanente está documentada y priorizada?

---

### 1.5 Criterio 5: Esfuerzo vs impacto (15%)

**Sub-criterios:**
- EI5.1 Relación beneficio/costo: ¿El impacto justifica el esfuerzo?
- EI5.2 Tiempo hasta valor: ¿Cuándo se ven los primeros resultados?
- EI5.3 Esfuerzo total estimado (días-hombre): ¿Es proporcionado?
- EI5.4 Riesgo de esfuerzo desbordado: ¿Hay tareas con alta incertidumbre?

**Escala 0–10:**
| Valor | Descriptor |
|-------|-----------|
| 0 | Esfuerzo enorme para impacto mínimo |
| 3 | Esfuerzo alto, resultados lentos, beneficio marginal |
| 6 | Buen balance: impacto significativo con esfuerzo moderado |
| 9 | Máximo impacto con mínimo esfuerzo, quick wins evidentes |

**Preguntas guía:**
- ¿Cuántos issues críticos se resuelven por día-hombre invertido?
- ¿Hay quick wins que generan valor temprano?
- ¿El esfuerzo estimado es completo (incluye testing, docs, despliegue)?
- ¿Hay tareas con incertidumbre >50% que podrían explotar el cronograma?

---

## 2. Plantilla de evaluación para cada juez

Cada juez de Fase 4 debe producir un archivo en:
- `.specs/research/oti-evaluacion-F4-juez1.md`
- `.specs/research/oti-evaluacion-F4-juez2.md`
- `.specs/research/oti-evaluacion-F4-juez3.md`

### 2.1 Estructura del archivo

```markdown
# Evaluación Fase 4 — Juez [N]

**Fecha:** 2026-05-21

## Solución A-E1: Cirugía de Seguridad

### Criterio 1: Alineación con requisitos (20%) — Puntaje: X/10
- ¿Cubre los 25 CRÍTICOS?: [Sí/No/Parcial] — Explicación breve
- ¿Preserva funcionalidad existente?: [Sí/No/Parcial] — Explicación breve
- ¿Respeta restricciones (sin frameworks, vanilla JS, PostgreSQL)?: [Sí/No/Parcial] — Explicación breve
- **Subtotal ponderado:** X × 20% = X

### Criterio 2: Factibilidad técnica (25%) — Puntaje: X/10
- ¿Los pasos de implementación son concretos?: [Sí/No/Parcial] — Explicación
- ¿Las estimaciones de esfuerzo son realistas?: [Sí/No/Parcial] — Explicación
- ¿La integración con la base existente es limpia?: [Sí/No/Parcial] — Explicación
- **Subtotal ponderado:** X × 25% = X

### Criterio 3: Potencial de calidad (20%) — Puntaje: X/10
- ¿Mejora significativamente la seguridad?: [Sí/No/Parcial] — Explicación
- ¿Mejora el rendimiento en cuellos de botella?: [Sí/No/Parcial] — Explicación
- ¿Mejora mantenibilidad y testabilidad?: [Sí/No/Parcial] — Explicación
- **Subtotal ponderado:** X × 20% = X

### Criterio 4: Gestión de riesgo (20%) — Puntaje: X/10
- ¿Documenta riesgos conocidos?: [Sí/No/Parcial] — Explicación
- ¿Propone mitigaciones concretas?: [Sí/No/Parcial] — Explicación
- ¿Permite rollback o despliegue gradual?: [Sí/No/Parcial] — Explicación
- **Subtotal ponderado:** X × 20% = X

### Criterio 5: Esfuerzo vs impacto (15%) — Puntaje: X/10
- ¿El impacto justifica el esfuerzo?: [Sí/No/Parcial] — Explicación
- ¿Hay quick wins visibles?: [Sí/No/Parcial] — Explicación
- ¿El esfuerzo estimado es completo y realista?: [Sí/No/Parcial] — Explicación
- **Subtotal ponderado:** X × 15% = X

### Puntaje Total: X/10

### Fortalezas:
- ...

### Debilidades:
- ...

### Voto:
[1er lugar / 2do lugar / 3er lugar]

---

## Solución B-E1: Hardening Dirigido

### Criterio 1: Alineación con requisitos (20%) — Puntaje: X/10
... (mismo formato que A-E1)

### Criterio 2: Factibilidad técnica (25%) — Puntaje: X/10
...

### Criterio 3: Potencial de calidad (20%) — Puntaje: X/10
...

### Criterio 4: Gestión de riesgo (20%) — Puntaje: X/10
...

### Criterio 5: Esfuerzo vs impacto (15%) — Puntaje: X/10
...

### Puntaje Total: X/10

### Fortalezas:
- ...

### Debilidades:
- ...

### Voto:
[1er lugar / 2do lugar / 3er lugar]

---

## Solución C-E3: Optimización Progresiva

... (mismo formato que las anteriores)

---

## Ranking Final

1. **[Propuesta]** — X/10
2. **[Propuesta]** — X/10
3. **[Propuesta]** — X/10
```

### 2.2 Reglas para jueces

- Los puntajes son enteros del 0 al 10 (sin decimales).
- Cada juez debe asignar votos de 1er, 2do y 3er lugar. No puede haber empates en el ranking.
- Las explicaciones deben ser concretas y referenciar partes específicas de los archivos de expansión.
- Los jueces deben leer los 3 archivos de expansión antes de emitir cualquier voto.
- El subtotal ponderado se calcula como: `puntaje × peso`. El puntaje total es la suma de los 5 subtotales dividida por 10 (para normalizar a escala 0-10).

---

## 3. Hoja de cálculo de agregación

### 3.1 Método de agregación primario: Promedio simple

Para cada solución, se calcula el promedio simple de los puntajes totales de los 3 jueces:

```
Puntaje_Final_A = (J1_A + J2_A + J3_A) / 3
Puntaje_Final_B = (J1_B + J2_B + J3_B) / 3
Puntaje_Final_C = (J1_C + J2_C + J3_C) / 3
```

### 3.2 Método de agregación secundario: Voto rankeado

Cada voto rankeado se convierte a puntos:
- 1er lugar = 3 puntos
- 2do lugar = 2 puntos
- 3er lugar = 1 punto

Se suman los puntos de los 3 jueces para cada solución:
```
Puntos_A = Puntos_J1_A + Puntos_J2_A + Puntos_J3_A
Puntos_B = Puntos_J1_B + Puntos_J2_B + Puntos_J3_B
Puntos_C = Puntos_J1_C + Puntos_J2_C + Puntos_J3_C
```

Rango de puntos posible por solución: 3–9 (mínimo si los 3 jueces la ponen en 3er lugar, máximo si los 3 la ponen en 1ro).

### 3.3 Regla de decisión

| Condición | Resultado |
|-----------|-----------|
| Una misma solución gana en ambos métodos | **Ganadora declarada** → Fase 4.5 ejecuta SELECT_AND_POLISH |
| Gana diferente en cada método | **Discrepancia** → Fase 4.5 revisa y decide estrategia adaptativa |
| Hay empate en el 1er lugar en ambos métodos | **Discrepancia** → Fase 4.5 ejecuta REDESIGN |
| Las 3 soluciones tienen puntajes cercanos (diferencia < 0.5) | **Caso complementario** → Fase 4.5 ejecuta FULL_SYNTHESIS |

### 3.4 Estrategias adaptativas (Fase 4.5)

```
SELECT_AND_POLISH:
  - Tomar la solución ganadora
  - Identificar debilidades señaladas por jueces
  - Producir plan de polishing para resolverlas
  - Output: oti-final-select.md

REDESIGN:
  - Analizar causas de divergencia entre jueces
  - Identificar puntos de conflicto en las evaluaciones
  - Producir nueva ronda de refinamiento híbrido
  - Output: oti-redesign.md + nueva iteración si es necesario

FULL_SYNTHESIS:
  - Extraer fortalezas de cada solución
  - Construir matriz de compatibilidad entre componentes
  - Producir plan integrado que fusione lo mejor de cada una
  - Output: oti-synthesis.md
```

---

## 4. Checklist de verificación técnica

Cada juez DEBE verificar estos items en el código/plan de las soluciones expandidas. Marcar con `[X]` si se cumple, `[ ]` si no, `[~]` si es parcial.

### 4.1 Seguridad

| # | Item | Cómo verificar |
|---|------|----------------|
| [ ] | CSRF token generado y verificado en todos los POST | Buscar `csrf_token` + `verify_csrf` en formularios y handlers |
| [ ] | Eliminado `strpos` para determinar admin | Grep `strpos` en archivos de auth |
| [ ] | `Access-Control-Allow-Origin` ya no es `*` | Grep en archivos de API |
| [ ] | Eliminada contraseña hardcodeada | Revisar `Database.php` y `usuarios.php` |
| [ ] | `$_GET['id']` sanitizado en `ticket-detalle.php` | Buscar sanitización (intval, ctype_digit, prepared statements) |
| [ ] | `SET NAMES 'UTF8'` cambiado a sintaxis PostgreSQL | Grep en archivos de conexión DB |
| [ ] | Headers de seguridad (CSP, X-Frame-Options, HSTS) propuestos | Buscar en middleware o configuración |
| [ ] | Prepared statements en todas las consultas SQL | Revisar queries en PHP |

### 4.2 Rendimiento

| # | Item | Cómo verificar |
|---|------|----------------|
| [ ] | `getStats` usa `FILTER` en una sola query | Buscar refactor de getStats |
| [ ] | Índices compuestos definidos en SQL | Buscar `CREATE INDEX` compuestos |
| [ ] | SSE sin doble callback ni crash en error | Revisar lógica de SSE |
| [ ] | Consultas N+1 eliminadas | Buscar evidencia de eager loading o joins |
| [ ] | Cache propuesto (redis/file/opcache) | Buscar estrategia de caché |
| [ ] | Lazy loading de assets | Buscar carga diferida de JS/CSS |
| [ ] | Consultas lentas identificadas y optimizadas | Buscar análisis de queries lentas |

### 4.3 JavaScript / CSS

| # | Item | Cómo verificar |
|---|------|----------------|
| [ ] | Canvas recibe `context2D`, no el elemento canvas | Revisar canvas.js o archivos de gráficos |
| [ ] | `console.log` eliminado de producción | Grep `console.log` en JS |
| [ ] | `search.js` sanitiza campos de entrada | Buscar sanitización en search.js |
| [ ] | Código JS modularizado o con IIFE | Buscar patrones de módulo |
| [ ] | CSS sin duplicación de reglas | Propuesta de limpieza CSS |
| [ ] | Event listeners no fugan memoria | Buscar removeEventListener o delegación |
| [ ] | JS vanilla sin dependencias de jQuery/Lodash | Verificar imports/scripts cargados |

### 4.4 Arquitectura

| # | Item | Cómo verificar |
|---|------|----------------|
| [ ] | Feature flags o módulos V2 propuestos | Buscar sistema de flags o versionado |
| [ ] | `BaseController` o middleware de seguridad | Buscar clase base o middleware |
| [ ] | División de `app.css` propuesta | Buscar plan de splitting CSS |
| [ ] | Separación de concerns (modelo/vista/controlador) | Evaluar estructura de archivos propuesta |
| [ ] | Autoloading (PSR-4 o composer) propuesto | Buscar autoload configuration |
| [ ] | Logging estructurado | Buscar monólogo, logger o PSR-3 |
| [ ] | Pruebas unitarias/integración propuestas | Buscar PHPUnit o similar en plan |
| [ ] | Documentación de API (endpoints) | Buscar docs de API |

### 4.5 Proceso y entregables

| # | Item | Cómo verificar |
|---|------|----------------|
| [ ] | Roadmap con fases claras y dependencias | Buscar timeline o milestones |
| [ ] | Estimación en días-hombre por fase | Buscar tabla de esfuerzo |
| [ ] | Criterios de aceptación por fase | Buscar definition of done |
| [ ] | Plan de pruebas post-despliegue | Buscar smoke tests o QA plan |
| [ ] | Estrategia de rollback documentada | Buscar plan de contingencia |

---

## 5. Flujo de trabajo de Fase 4

```
Fase 3 (Expansión)
    ↓
[3 archivos de expansión generados]
    ↓
Fase 3.5 (Meta-Juez) ← ESTAMOS AQUÍ
    ↓
[Esta especificación lista]
    ↓
Fase 4 (Evaluación por 3 jueces)
    ↓
Cada juez lee los 3 archivos de expansión
    ↓
Cada juez produce su evaluación individual
    ↓
[oti-evaluacion-F4-juez1.md]
[oti-evaluacion-F4-juez2.md]
[oti-evaluacion-F4-juez3.md]
    ↓
Fase 4.5 (Meta-Juez agrega resultados)
    ↓
Aplica regla de decisión (3.3)
    ↓
Ejecuta estrategia adaptativa (3.4)
    ↓
Output final
```

---

## 6. Notas para los jueces

1. **Sean críticos pero justos.** No inflen puntajes. Un 6 es "bueno", un 9 es "excepcional".
2. **Fundamenten cada puntaje.** La explicación es más importante que el número.
3. **Usen el checklist técnico.** Verifiquen cada item contra los archivos de expansión.
4. **No comparen entre soluciones al puntuar.** Evalúen cada una contra la rúbrica, no contra las otras. El ranking final debe reflejar la evaluación independiente.
5. **Si una solución no aborda un criterio**, puntúen 0 y expliquen por qué.
6. **El voto (1er/2do/3er lugar) puede diferir del orden por puntaje total** si consideran que hay factores cualitativos no capturados por la rúbrica. Si lo hacen, expliquen la discrepancia.
7. **Plazo:** Cada juez tiene 24 horas desde que recibe los archivos de expansión para entregar su evaluación.
