# OTI — Plan Estratégico de Correcciones (Auditoría Técnica)

**Fecha:** 2026-05-21  
**Versión:** 1.0  
**Clasificación:** Interno — Departamento de TI  
**Archivo:** `.specs/research/oti-optimizacion-2026-05-21.proposals.c.md`

---

## Paso 1: Descomposición del Problema

### Problema Central

El sistema OTI es una aplicación PHP 8.x monolítica sin framework, con 147 issues identificados que comprometen su puesta en producción. Los problemas abarcan seguridad (XSS, CSRF, SQL injection potencial, auth bypass), rendimiento (N+1 queries, falta de caché), calidad de código (dead code, error disclosure), CSS (layout breaking, 69KB sin optimizar), JS (errores que crashean, falta de manejo de errores), y accesibilidad.

### Restricciones y Requisitos Clave

1. **PHP 8.x puro** — Sin frameworks externos (Laravel, Symfony, etc.)
2. **PostgreSQL como motor de BD** — Compatibilidad con queries avanzadas (FILTER, CTEs)
3. **Apache + mod_rewrite** — SEO-friendly URLs mediante `.htaccess`
4. **Preservar funcionalidad existente** — No regresiones en flujos críticos (login, creación de tickets, asignación, notificaciones SSE)
5. **Vanilla JS** — Sin jQuery, React, Vue, etc.
6. **Presupuesto realista** — Priorizar ~25 issues CRÍTICOS y ~44 HIGH sobre ~46 MEDIUM y ~32 LOW

### Subproblemas a Abordar

| # | Subproblema | Severidad | Archivos Afectados |
|---|-------------|-----------|-------------------|
| S1 | **Auth Bypass + Sesión insegura**: Roles determinados por `strpos()` en nombre del rol, sin CSPRNG en session ID, sin SameSite cookie | CRÍTICO | `index.php:34-40`, `AuthService.php:107-114`, `AuthController.php:35-42` |
| S2 | **CSRF total**: Ninguna operación POST verifica token CSRF | CRÍTICO | `index.php`, `app/api/*.php`, `app/Views/*.php` |
| S3 | **XSS Reflejado/Almacenado**: Datos de usuario se renderizan sin escape consistente | CRÍTICO | `app/api/tickets.php:236`, `app/api/equipos.php`, vistas |
| S4 | **Error Disclosure**: Excepciones PDO/Exception se devuelven como JSON sin sanitizar | HIGH | `app/api/*.php` (todos los catch) |
| S5 | **SQL Injection Potencial**: LIKE queries sin bound params adecuados, IDs no tipificados | HIGH | `app/Models/*.php`, `app/api/*.php` |
| S6 | **N+1 Queries**: `getStats()` ejecuta 5+ queries separadas en lugar de una sola | MEDIUM | `app/Models/Ticket.php:341-386`, `Equipment.php:99-117` |
| S7 | **Hard-coded credentials en fallback**: DB_PASSWORD en Database.php línea 19 | HIGH | `app/Core/Database.php:19` |
| S8 | **BASE_URL hard-coded + CORS abierto**: `Access-Control-Allow-Origin: *` en todas las APIs | HIGH | `index.php:16`, `app/api/*.php` |
| S9 | **CSS monolítico**: 3350 líneas/69KB sin purga, sin variables consistentes, duplicación | MEDIUM | `public/assets/css/app.css` |
| S10 | **JS sin manejo de errores**: fetch() failures silenciosos, SSE puede colgar | CRÍTICO | `public/assets/js/realtime.js`, `search.js` |
| S11 | **SSE bloqueante**: Bucle infinito sin heartbeat check, fuga de conexiones | HIGH | `app/api/sse.php` |
| S12 | **Falta de rate limiting / account lockout**: Ataque de fuerza bruta posible | HIGH | `app/Controller/AuthController.php` |
| S13 | **Contraseña por defecto predecible**: `OTI2026` en `usuarios.php:76` | HIGH | `app/api/usuarios.php:76` |
| S14 | **Sesiones sin regeneración robusta**: `session_regenerate_id(true)` solo en login exitoso | MEDIUM | `app/Services/AuthService.php:33` |

### Criterios de Evaluación

1. **Efectividad en seguridad** — ¿Cubre los 25 CRÍTICOS y reduce los 44 HIGH?
2. **Esfuerzo de implementación** — Días-hombre estimados vs. presupuesto disponible
3. **Riesgo de regresión** — Probabilidad de romper funcionalidad existente
4. **Mantenibilidad futura** — Facilidad para agregar nuevas features post-corrección
5. **Rendimiento** — Reducción de queries, tiempo de carga, memoria
6. **Portabilidad** — Capacidad de migrar a servidor diferente sin cambios

---

## Paso 2: Mapeo del Espacio de Soluciones

### Dimensiones Principales

```
Refactorización     ○─────────────────●  Intervención mínima
  profunda                                (solo parches)

Seguridad           ○─────────────────●  Funcionalidad
  first                                   first

CSS/JS              ○─────────────────●  Backend-only
  completo                                (dejar assets)

Arquitectura        ○─────────────────●  Capa delgada
  en capas                                (index.php monolítico)

Monitoreo           ○─────────────────●  Sin logging
  completo                                adicional

Pruebas             ○─────────────────●  Sin tests
  automatizadas
```

### Ejes de Trade-off

| Eje | Opción A | Opción B |
|-----|----------|----------|
| **Enfoque** | Refactorización total | Parcheo quirúrgico |
| **Seguridad** | CSP + CSRF + XSS completo | Solo CSRF + XSS en inputs |
| **Rendimiento** | Caché + Query optimizadas | Solo índices BD |
| **CSS** | Purga + variables + critical CSS | Solo minificación |
| **JS** | Módulos ES6 + error boundary | Solo try/catch adicionales |
| **Auth** | RBAC completo con base de datos | strpos() con sanitización |

---

## Paso 3: Enfoques de Alto Nivel

---

### ENFOQUE 1: «Escudo Defensivo» — Parcheo Quirúrgico de Seguridad + Estabilización

**Probabilidad: 0.92 | Complejidad: Baja | Riesgo: Bajo**

#### Resumen
Intervención mínima enfocada exclusivamente en cerrar los 25 issues CRÍTICOS y los 44 HIGH mediante parches localizados sin alterar la arquitectura existente.

#### Descripción Detallada
Este enfoque trata el sistema como una aplicación heredada que necesita llegar a producción rápidamente. No se refactoriza nada; solo se añaden las protecciones mínimas indispensables en los puntos de entrada. Consiste en: (1) implementar CSRF tokens en todos los formularios y endpoints POST mediante un middleware central en `index.php`; (2) sanitizar todas las salidas con `htmlspecialchars()` donde falte (especialmente en las APIs que devuelven datos ingresados por usuarios); (3) añadir validación de tipos estrictos en todos los parámetros de ruta; (4) eliminar el fallback de contraseña hard-coded en `Database.php` y usar exclusivamente `.env`; (5) cambiar el sistema de roles de `strpos()` a una consulta directa a BD; (6) agregar rate limiting por IP simple en el login; (7) cerrar el CORS abierto (`Access-Control-Allow-Origin: *`) reemplazándolo con `SameOrigin` o lista blanca.

Cada cambio es localizado, fácil de revertir, y no modifica la lógica de negocio. Se implementa en orden de criticidad: primero los CRÍTICOS (sesión, CSRF, XSS), luego los HIGH (error disclosure, SQL injection, CORS). El CSS y JS se dejan para una fase posterior.

#### Decisiones Clave de Diseño
- **No tocar las vistas** — Solo se protege el backend; las vistas PHP se dejan como están (excepto añadir `csrf_token()` en forms)
- **Middleware global único —** Se crea `SecurityMiddleware::protect()` que se ejecuta al inicio de `index.php` y verifica CSRF en todas las rutas POST
- **Lista blanca de CORS** — Se define `ALLOWED_ORIGINS` en `.env` con el origen exacto de producción
- **Rate limiting por archivo temporal** — Se usa `sys_get_temp_dir()/oti_login_attempts_{ip}` con TTL en lugar de Redis

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Implementación rápida (3-5 días) | CSS/JS sigue siendo frágil |
| Riesgo de regresión mínimo | Rendimiento no mejora |
| Cobertura del 100% de CRÍTICOS | N+1 queries persisten |
| Fácil de auditar (commits pequeños) | Código muerto no se limpia |

#### Riesgos Potenciales
- **CSRF implementado incorrectamente** puede bloquear todos los POST si no se actualizan los forms a tiempo → mitigación: implementar primero los tokens en el backend, luego actualizar las vistas
- **Rate limiting mal configurado** puede bloquear usuarios legítimos → mitigación: usar umbrales conservadores (10 intentos/15 min)
- **CORS restrictivo** puede romper el SSE si el frontend está en puerto diferente → mitigación: incluir `localhost` en la lista blanca para desarrollo

---

### ENFOQUE 2: «Refactorización Estructural» — Arquitectura en Capas con Router, DI y Error Handling

**Probabilidad: 0.85 | Complejidad: Alta | Riesgo: Medio-Alto**

#### Resumen
Reestructuración completa del sistema hacia una arquitectura MVC con front controller robusto, inyección de dependencias básica, manejo centralizado de errores, y autoloading PSR-4 optimizado.

#### Descripción Detallada
Se reescribe el `index.php` como un router formal que reemplaza el `switch`/`if` anidado por un sistema de rutas registradas. Se introduce un `Router` class que mapea `[METHOD, PATH]` a controladores específicos. Se extrae la lógica de validación de sesión a un middleware de autenticación que se ejecuta antes de cada ruta protegida. Se crea un `ErrorHandler` global con `set_exception_handler()` y `set_error_handler()` que sanitiza errores en producción (nunca muestra stack traces) y los loguea estructuradamente.

Las APIs se refactorizan de archivos sueltos (`app/api/*.php`) a controladores con métodos específicos. Los modelos se mantienen pero se les añade type hints y return types estrictos. Se implementa un `QueryBuilder` mínimo para evitar concatenación de strings SQL. Se centraliza la configuración en `Config` con tipado estricto.

#### Decisiones Clave de Diseño
- **Router basado en arrays —** En lugar de expresiones regulares complejas, se usa un array `[method=>[path=>handler]]` con parámetros named `{id}` reemplazados por regex
- **PSR-7 no —** Se evita la implementación completa de PSR-7; se usa un Request/Response wrapper mínimo
- **Inyección de dependencias manual —** No se usa contenedor; las dependencias se pasan por constructor en los controladores
- **Error handler con template —** En producción, los errores 404/403/500 renderizan una vista de error sin fugas de información

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Código mantenible y testeable | Tiempo significativo (2-3 semanas) |
| Error handling profesional | Alto riesgo de regresiones |
| Arquitectura preparada para crecimiento | Cambio masivo en index.php |
| Desacoplamiento API/Controlador | Curva de aprendizaje para el equipo |

#### Riesgos Potenciales
- **Regresiones en rutas —** Si el regex del router no cubre todos los casos, algunas rutas pueden dar 404 → mitigación: suite de pruebas de rutas antes del deploy
- **Las vistas existentes dependen de variables globales** (`$pdo`, `$_SESSION`) → mitigación: wrapper de compatibilidad temporal
- **Error handler puede tragar errores fatales** que antes se veían → mitigación: logging agresivo en desarrollo

---

### ENFOQUE 3: «Optimización Progresiva» — Refactor por Módulos con Feature Flags

**Probabilidad: 0.78 | Complejidad: Media | Riesgo: Medio**

#### Resumen
Enfoque híbrido y pragmático: se aplican parches de seguridad inmediatos (como en enfoque 1) mientras se refactoriza módulo por módulo (tickets, usuarios, equipos, estructura) utilizando feature flags que permiten activar/desactivar cada módulo refactorizado independientemente.

#### Descripción Detallada
Se divide el trabajo en 4 sprints de 1 semana cada uno. Sprint 0: parches de seguridad urgentes (CSRF, XSS, CORS, auth bypass). Sprint 1: refactor del módulo de Tickets (migrar `app/Views/admin/tickets.php`, `app/api/tickets.php`, `app/Models/Ticket.php` a controladores limpios con validación). Sprint 2: refactor del módulo de Usuarios. Sprint 3: refactor de Equipos y Estructura.

Cada módulo refactorizado tiene su propio feature flag en `.env` (`MODULE_TICKETS_REFACTORED=true`). Si un módulo refactorizado falla, se desactiva el flag y el sistema cae al código legacy automáticamente. Esto permite desplegar en producción con seguridad. Los módulos comparten un nuevo `BaseController` con métodos de validación, sanitización, y respuesta JSON/HTML.

#### Decisiones Clave de Diseño
- **Feature flags en .env —** Cada módulo tiene `MODULE_{NAME}_V2` que determina si se usa el código nuevo o legacy
- **BaseController —** Clase abstracta con métodos `json()`, `view()`, `validate()`, `redirect()`, `csrf()` que todos los controladores extienden
- **Fallback automático —** Si el módulo V2 lanza una excepción no manejada, se loguea y se redirige al V1
- **Autoloading de vistas —** Las vistas V2 se almacenan en `app/Views/v2/` para coexistir con las originales

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Deploy seguro por módulo | Complejidad adicional de feature flags |
| Seguridad desde el día 1 | Duplicación temporal de código |
| El equipo aprende gradualmente | Mantener dos versiones simultáneas |
| Rollback inmediato por flag | Pruebas duplicadas |

#### Riesgos Potenciales
- **Feature flags olvidados en producción —** Código legacy nunca se limpia → mitigación: cada flag debe tener una fecha de expiración en el código
- **Inconsistencia entre V1 y V2 —** Si se arregla un bug en V1 pero no se porta a V2 → mitigación: checklist de portabilidad por sprint
- **Los feature flags añaden paths de ejecución no probados** → mitigación: CI debe probar ambas ramas (flag on/off)

---

### ENFOQUE 4: «Zero Trust Rewrite» — Reescribir el Sistema Desde Cero con PHP Moderno

**Probabilidad: 0.08 | Complejidad: Muy Alta | Riesgo: Muy Alto**

#### Resumen
Reconstrucción completa del sistema OTI desde cero utilizando PHP 8.x con tipado fuerte, traits reutilizables, y patrones de diseño modernos, manteniendo solo el esquema de base de datos existente como contrato inmutable.

#### Descripción Detallada
Se descarta todo el código PHP, JS y CSS existente. Se rediseña la arquitectura comenzando desde la base de datos hacia arriba (Database-First). Se implementa un kernel mínimo con: (1) `HttpKernel` que maneja requests/responses; (2) `ServiceContainer` con autowiring básico por reflexión; (3) `ValidationService` con reglas declarativas; (4) `SecurityProvider` con autenticación, autorización RBAC por BD, CSRF automático, y rate limiting por Redis (o archivo como fallback); (5) `TemplateEngine` que extiende PHP nativo con layouts, partials, y escape automático (`{{ $var }}` → `htmlspecialchars()`); (6) `AssetPipeline` que compila, minifica y versiona CSS/JS.

El JS se reescribe usando ES modules nativos con una arquitectura de componentes funcionales: `StateManager`, `SSEClient`, `Router` SPA-lite, y `Component` base. El CSS se reescribe con CSS custom properties, diseño system, y un archivo por componente que se concatenan en build.

#### Decisiones Clave de Diseño
- **Template engine con escape automático —** Usar `render()` que escapa todas las variables por defecto, con sintaxis `{!! $raw !!}` para excepciones
- **API RESTful —** Las APIs existentes se reescriben como REST endpoints con versionado (`/api/v1/tickets`) y validación mediante Value Objects
- **DTOs para todas las transferencias —** Cada endpoint devuelve un Data Transfer Object tipado, nunca un array asociativo
- **Middleware pipeline —** Request pasa por: AuthMiddleware → CsrfMiddleware → RateLimitMiddleware → ValidationMiddleware → Controller

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Código limpio, moderno, testeable | Tiempo enorme (2-3 meses) |
| Seguridad por diseño, no por parches | Congela todas las otras features |
| Rendimiento óptimo desde el inicio | Costo de desarrollo muy alto |
| Documentación y estándares | El negocio puede no esperar |

#### Riesgos Potenciales
- **El negocio no puede esperar 2-3 meses** sin nuevas features → mitigación: probar con un prototipo funcional en 2 semanas para demostrar progreso
- **Pérdida de funcionalidad no documentada** — El sistema legacy puede tener comportamientos que no se descubren hasta producción → mitigación: sesiones de discovery con usuarios finales antes de empezar
- **El equipo puede no tener experiencia con PHP moderno** (tipado, DTOs, middlewares) → mitigación: pair programming con arquitecto senior
- **Costo de oportunidad alto** — Recursos dedicados a la reescritura no pueden usarse para otras iniciativas

---

### ENFOQUE 5: «Cirugía Digital» — Micro-fixes Automatizados + KPI de Calidad

**Probabilidad: 0.06 | Complejidad: Media | Riesgo: Bajo-Medio**

#### Resumen
Automatización del 80% de las correcciones mediante scripts de transformación de código (PHP-Parser, AST transforms, y linting con reglas personalizadas) combinados con un dashboard de calidad que mide la evolución de los 147 issues en cada commit.

#### Descripción Detallada
Se construye un pipeline de CI/CD que ejecuta transforms automáticos sobre el código fuente. Estos transforms incluyen: (1) añadir `htmlspecialchars()` en todas las salidas de variables en vistas PHP; (2) reemplazar `echo json_encode($data)` por `json_encode_safe($data)` que sanitiza automáticamente; (3) añadir type hints faltantes en parámetros y returns de métodos; (4) eliminar código muerto (funciones/variables no usadas); (5) convertir `strpos($role, 'admin')` por `in_array($role, $adminRoles)`; (6) reemplazar `=` query params en SQL concatenados por prepared statements con named params.

Cada transform se implementa como un script PHP standalone que usa `nikic/php-parser` para parsear, transformar, y volver a imprimir el código fuente. Los transforms se ejecutan en un orden específico (seguridad primero, calidad después) y cada uno produce un diff revisable antes de commit.

El dashboard de calidad se implementa como un script que parsea los issues, los categoriza, y genera un reporte HTML con la tendencia histórica. Cada commit debe reducir el contador de issues CRÍTICOS a 0 y HIGH a menos de 10 para ser aceptado.

#### Decisiones Clave de Diseño
- **AST transforms —** Se usa `nikic/php-parser` con `PhpParser\NodeVisitor` para modificar el código programáticamente
- **No tocar lógica de negocio —** Los transforms solo afectan patrones estructurales (sanitización, types, prepared statements), nunca algoritmos
- **Pre-commit hook —** Se configura un hook de git que ejecuta los transforms y verifica que no queden issues CRÍTICOS
- **Baseline de calidad —** El primer commit establece el baseline de 147 issues; cada commit debe reducir ese número

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Velocidad de corrección (80% en días) | Transformaciones imperfectas (20% manual) |
| Consistencia (todos los archivos igual) | Dependencia de php-parser |
| Auditabilidad (cada cambio es un diff) | No mejora arquitectura |
| Dashboard motiva al equipo | CSS/JS no se cubren automáticamente |

#### Riesgos Potenciales
- **Los transforms pueden introducir bugs sutiles** — Ej: añadir `htmlspecialchars()` donde ya se aplicaba doble escape → mitigación: cada transform produce diff que debe ser revisado manualmente
- **php-parser puede fallar con código sintácticamente inválido** → mitigación: ejecutar `php -l` antes y después de cada transform
- **El equipo puede ignorar el dashboard** → mitigación: integrar el dashboard en el PR review como check obligatorio
- **JS/CSS no se pueden transformar con AST** → mitigación: complementar con ESLint + Stylelint autofix

---

### ENFOQUE 6: «Híbrido Empresarial» — Framework Propietario Mínimo + Contrato de API

**Probabilidad: 0.03 | Complejidad: Alta | Riesgo: Alto**

#### Resumen
Construcción de un micro-framework propietario (mínimo viable) que abstrae la seguridad, validación y persistencia, combinado con una separación total Frontend/Backend mediante una API REST documentada, permitiendo que el frontend (JS vanilla) sea completamente independiente y reemplazable.

#### Descripción Detallada
Se diseña e implementa un micro-framework interno con 4 componentes: (1) `Router` — mapeo de rutas con middlewares; (2) `ORM` mínimo — Query Builder fluido con protección contra SQL injection; (3) `Auth` — guard con autenticación, autorización RBAC, CSRF automático, rate limiting; (4) `Validation` — reglas declarativas (`required|email|max:255`). Este framework se empaqueta como un directorio `app/Framework/` y se usa como dependencia interna.

Paralelamente, se reescriben todas las APIs (`app/api/*.php`) como endpoints RESTful que devuelven JSON con estructura consistente (`{success, data, error, meta}`). Se implementa versionado de API (`/api/v1/...`) y documentación OpenAPI 3.0 generada desde anotaciones PHP. El frontend JS existente (`realtime.js`, `search.js`, `analisis-charts.js`) se refactoriza para consumir exclusivamente estas APIs, eliminando toda la lógica de renderizado del lado del servidor.

Las vistas PHP se mantienen solo para las páginas iniciales (login, layout shell), pero el contenido dinámico se carga vía fetch() contra las APIs. Esto permite que eventualmente el frontend pueda ser reemplazado por cualquier tecnología (React, Vue, etc.) sin tocar el backend.

#### Decisiones Clave de Diseño
- **API First —** Toda la lógica de negocio se expone como API; las vistas PHP son solo shells HTML
- **OpenAPI desde anotaciones —** Se usa un parser de docblocks para generar `openapi.json` automáticamente
- **ORM mínimo con Query Builder fluido —** `DB::table('tickets')->where('status_id', 1)->get()` con protección automática de inyección
- **Frontend como consumidor API —** Todo renderizado dinámico usa `fetch()` y templates HTML generados por JS

#### Trade-offs
| Ganas | Sacrificas |
|-------|-----------|
| Desacoplamiento total Frontend/Backend | Tiempo de desarrollo muy largo (1-2 meses) |
| API documentada reusable | JS se vuelve más complejo (SPA patterns) |
| Framework adaptado exactamente al dominio | Reescribir componentes del framework internamente |
| Preparado para migración a SPA | Doble mantenimiento (vistas PHP + JS) hasta migrar |

#### Riesgos Potenciales
- **El micro-framework puede tener bugs no descubiertos** que afecten toda la app → mitigación: pruebas unitarias exhaustivas del framework antes de usarlo
- **JS refactorizado puede ser complejo de mantener sin un framework frontend** → mitigación: usar patrones de componentes vanilla con estado centralizado
- **La documentación OpenAPI puede desincronizarse del código real** → mitigación: generar la documentación desde las anotaciones en cada build
- **El equipo puede resistirse al cambio de paradigma** (de servidor a SPA) → mitigación: capacitación y pair programming durante la transición

---

## Paso 4: Verificación de Diversidad

### ¿Son los enfoques genuinamente diferentes?

| Dimensión | Enfoque 1 | Enfoque 2 | Enfoque 3 | Enfoque 4 | Enfoque 5 | Enfoque 6 |
|-----------|-----------|-----------|-----------|-----------|-----------|-----------|
| **Profundidad** | Superficial | Profunda | Media | Total | Automatizada | Arquitectural |
| **Riesgo** | Bajo | Medio-Alto | Medio | Muy Alto | Bajo-Medio | Alto |
| **Tiempo** | ~1 semana | ~3 semanas | ~4 semanas | ~10 semanas | ~2 semanas | ~6 semanas |
| **Esfuerzo** | Bajo | Alto | Medio | Muy Alto | Medio | Alto |
| **Cobertura CSS/JS** | No | Parcial | Parcial | Total | Parcial | Total (JS) |
| **Deuda técnica** | No se reduce | Se reduce | Se reduce parcialmente | Se elimina | Se reduce | Se transforma |
| **Dependencias** | Ninguna | Ninguna | Ninguna | Ninguna | php-parser | Ninguna |
| **Mantenibilidad** | No mejora | Mejora mucho | Mejora | Excelente | Mejora poco | Mejora mucho |

### Regiones del Espacio de Soluciones Cubiertas

```
                         +-- Enfoque 6 (API-First)
                        /
                       /  Enfoque 4 (Full Rewrite)
                      /
        Enfoque 2 (Arquitectura)
       /
      /  Enfoque 3 (Híbrido)
     /
Seguridad ---- Enfoque 1 (Parcheo)
     \
      \  Enfoque 5 (Automation)
       \
        \
         +-- Low-touch, High-automation
```

### Recomendación Integrada

Dado el perfil de riesgo y las restricciones del proyecto, la **estrategia óptima** combina elementos de los enfoques 1, 3 y 5:

1. **Fase 0 (Inmediata — Días 1-3):** Parches de seguridad del **Enfoque 1** (CSRF, XSS, CORS, auth bypass, rate limiting)
2. **Fase 1 (Semana 1-2):** Automatización del **Enfoque 5** para limpiar el 80% de issues MEDIUM/LOW y aplicar type hints consistentes
3. **Fase 2 (Semanas 3-6):** Refactorización por módulos del **Enfoque 3** (Tickets → Usuarios → Equipos → Estructura) con feature flags

Esta combinación permite: seguridad inmediata (días), limpieza automatizada (semanas), y mejora arquitectural sostenible (meses), minimizando el riesgo de regresiones en cada etapa.

---

*Fin del documento — Próximo paso: Implementación de Fase 0 (Enfoque 1)*
