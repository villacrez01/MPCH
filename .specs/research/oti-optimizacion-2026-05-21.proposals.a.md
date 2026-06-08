# Plan de Optimización Integral — Sistema OTI
## Auditoría y Corrección para Producción

**Fecha:** 2026-05-21
**Versión:** 1.0
**Clasificación:** Crítica — Previo a producción
**Archivo:** `.specs/research/oti-optimizacion-2026-05-21.proposals.a.md`

---

## Paso 1: Descomposición del Problema

### Problema Central
Sistema OTI (municipalidad) construido sobre PHP 8.x + PostgreSQL + vanilla JS con **147 issues identificados** (25 críticos, 44 high, 46 medium, 32 low). Debe ser llevado a un estado de producción sin errores, caídas ni vulnerabilidades explotables, respetando las restricciones de arquitectura existentes (sin frameworks, vanilla JS, Apache mod_rewrite, PostgreSQL).

### Restricciones y Requisitos Clave
| Restricción | Impacto |
|---|---|
| PHP 8.x sin frameworks externos | No se puede usar Laravel, Symfony, etc. Soluciones deben ser manuales o con librerías vía Composer |
| PostgreSQL como única BD | Consultas deben usar sintaxis PostgreSQL (`ILIKE`, `FILTER`, `RETURNING`, `TO_CHAR`, `INTERVAL`) |
| Apache con mod_rewrite | .htaccess existente ya maneja rewrite; soluciones deben ser compatibles |
| Vanilla JS (sin frameworks) | No se puede usar React, Vue, Alpine. JS debe mantenerse en ES5/ES6 sin transpilación |
| Preservar funcionalidad existente | No se puede rediseñar el sistema desde cero; cambios deben ser quirúrgicos |

### Subproblemas a Abordar
1. **Seguridad (25 críticos + 12 high):**
   - XSS reflejado y almacenado (entradas de usuario no sanitizadas en vistas y API)
   - CSRF ausente (97% de endpoints POST sin token de verificación)
   - CORS abierto (`Access-Control-Allow-Origin: *` en 8/11 APIs)
   - Auth bypass por role-name string matching (cualquier rol con substring "admin" obtiene acceso admin)
   - Sesiones sin timeout absoluto ni regeneración en cada request
   - HTTP-only ausente en cookies de sesión
   - Contraseña en texto plano en .env (`DB_PASSWORD=123456789`)
   - Error disclosure: `display_errors=0` pero mensajes de error detallados se devuelven en JSON
   - SQL injection potencial (parámetros sin tipar, query dinámico en `search.php` con `?`)
   - `strip_tags` como única defensa contra XSS en comentarios/respuestas
   - Rate limiting ausente en login y APIs

2. **Rendimiento (18 high + 14 medium):**
   - N+1 queries: `Ticket::getStats()` ejecuta 5 queries separadas donde 1 basta
   - `Ticket::getByPriority()` y `getByStatus()` sin paginación ni caching
   - `Location::getStats()` también ejecuta 5 queries separadas
   - SSE (Server-Sent Events) ejecuta query completa cada 5s — sin caching ni ETag
   - `Equipment::getStats()` ejecuta 4 queries separadas
   - `User::getLocationsHierarchy()` loopea en PHP sobre resultados donde SQL `ROW_NUMBER()` podría jerarquizar
   - `buildTree()` en `locations.php` es recursivo O(n²) en el peor caso
   - No hay `EXPLAIN ANALYZE` en queries pesadas
   - No hay índices en columnas frecuentemente filtradas (`status_id`, `user_id`, `created_at`, `code`)
   - Chart.js polling a `stats.php` cada 10s compite con SSE cada 5s — doble carga

3. **CSS (10 high + 8 medium):**
   - `app.css` pesa 69KB/3350 líneas — monolithic, sin code-splitting
   - Layout shifting por ausencia de `width/height` en imágenes SVG inline
   - `overflow: hidden` en body cuando search modal se abre — causa reflow
   - `user-scalable=yes` vs `user-scalable=no` inconsistente entre login y app
   - Media queries duplicadas y no agrupadas por breakpoint
   - `!important` usado ~15 veces — rompe especificidad en cascada
   - Estilos de login duplicados en `login.css` y parcialmente en `app.css`
   - Faltan estilos `prefers-reduced-motion` para animaciones
   - Z-index inconsistente entre sidebar, header, modals, y search modal
   - Sin variables CSS para algunos colores (hardcoded `#0284c7`, `#059669` en JS inline)

4. **JavaScript (12 high + 10 medium):**
   - `eventSource.onmessage` + `eventSource.addEventListener('update', ...)` doble callback → datos procesados 2x
   - `console.log`/`console.error` expuesto en producción en `realtime.js`
   - `escapeHtml()` usando manipulación DOM (`createElement('div')`) en lugar de regex — puede causar reflow
   - Sin `AbortController` en fetch calls → memory leaks si el usuario navega
   - `fetchNotifications()` cada 30s + SSE cada 5s = notificaciones solicitadas en paralelo
   - Event listener duplicado en `search.js` (dos `keydown` listeners separados)
   - `toLocaleDateString('es-PE')` puede fallar si locale no está disponible
   - Sin manejo de errores granular (catch genérico silencia todo)
   - `updateUserTicketDetail()` no verifica si response es JSON antes de parsear

5. **Accesibilidad (32 low):**
   - Faltan `aria-label` en iconos SVG decorativos
   - Contraste insuficiente en algunos text-muted sobre bg-card
   - Tab index no definido en modales (trampa de foco)
   - Sin `role="alert"` en mensajes de error/success flash
   - Botones sin `aria-pressed` ni `aria-expanded`
   - Sin skip-to-content link

6. **Código Muerto / Deuda Técnica (32 low + 4 medium):**
   - `profile.php` usa `require_once` directo a Model (no usa autoload hasta después)
   - `user_tickets.php` incluye comentario con tabla `inventory.equipos` pero el código usa `oti.equipment`
   - `search.php` referencia `admin.roles r ON u.role_id = r.id` pero la tabla real es `admin.usuario_rol`
   - `BASE_URL` hardcodeada como `http://localhost/OTI/` — no funciona en producción con HTTPS
   - Múltiples `define()` vs `defined()` inconsistente
   - `date_default_timezone_set()` no está definido globalmente
   - `Cookie` sin `Secure` flag (porque BASE_URL es http)

### Criterios de Evaluación
1. **Seguridad:** Sin XSS, CSRF, auth bypass, ni SQL injection explotables
2. **Rendimiento:** TTFB < 200ms en páginas principales, < 100ms en APIs, sin N+1 queries
3. **Estabilidad:** Sin crashes de JS, sin errores 500 no manejados, sin memory leaks en SSE
4. **Mantenibilidad:** CSS organizado, JS modular, código sin dead code
5. **Accesibilidad:** WCAG 2.1 AA mínimo (contraste, aria, navegación teclado)
6. **Producción-ready:** Sin console.log, sin APP_DEBUG=true, sin errores fatales no capturados

---

## Paso 2: Mapeo del Espacio de Soluciones

### Dimensiones de Arquitectura

```
Refactor Progresivo  ──────────────────────────  Rewrite Total
   (mantener estructura)                  (reconstruir desde cero)
   
Solución en Capas  ───────────────────────────────  Monolito Plano
   (separación clara)                        (código mezclado)

Seguridad por Capas  ─────────────────────────  Seguridad Perimetral
   (defense-in-depth)                        (solo firewall/waf)

JS Vanilla Optimizado  ─────────────────────────  JS con Micro-Framework
   (sin dependencias)                        (Alpine.js, htmx, etc.)

CSS Modular  ──────────────────────────────────  CSS Monolítico
   (archivos separados)                     (un solo archivo grande)

ORM / Query Builder  ───────────────────────────  PDO Directo
   (capa de abstracción)                     (queries SQL manuales)

Auth RBAC por BD  ─────────────────────────  Auth por String Parsing
   (roles en tabla)                          (strpos en role_name)
```

### Ejes de Trade-off

| Eje | Trade-off |
|---|---|
| Velocidad de implementación vs Calidad | Más rápido = menos refactor, más deuda técnica residual |
| Seguridad máxima vs Usabilidad | Mayor seguridad (CSP estricto, rate limiting) puede bloquear funcionalidad |
| Rendimiento vs Mantenibilidad | Cache agresivo mejora perf pero añade complejidad |
| Refactor (deuda técnica) vs Nuevas features | Pagar deuda primero retrasa features pero acelera a futuro |
| JS Vanilla vs Micro-framework | Vanilla es más ligero pero Alpine.js/htmx reduce bugs JS |
| CSS monolítico vs modular | Monolítico es simple de deployar; modular es más mantenible |

---

## Paso 3: Seis Enfoques de Alto Nivel

---

### Enfoque 1: «Cirugía de Seguridad + Optimización Selectiva»
**Probabilidad:** 0.92 | **Complejidad:** Media | **Riesgo:** Bajo

**Resumen:** Corrección quirúrgica de los 25 issues críticos y 44 high, preservando al máximo la estructura actual, con optimizaciones específicas de rendimiento donde haya más impacto.

**Descripción detallada:**
Este enfoque opera como una intervención de "capa delgada" sobre el código existente. Se implementa un `SecurityMiddleware` que se ejecuta en el front controller (`index.php`) antes de cualquier ruta, aplicando: CSP header real (el existente en `Security.php` no se usa en `index.php`), regeneración de ID de sesión por request, verificación de CSRF en todos los POST, y validación de roles por base de datos (columna `es_admin` real) en lugar de `strpos(role_name)`. Se añade un rate limiter simple por IP usando archivos temporales o Redis si está disponible.

En las vistas, se implementa un output escaping wrapper (`e()`) que reemplaza el uso inconsistente de `htmlspecialchars()` vs `strip_tags()`. Se agrega un Content Security Policy nonce que cubre todos los scripts inline. Los endpoints API reciben validación de entrada centralizada y se elimina `Access-Control-Allow-Origin: *` reemplazándolo con el origen específico o simplemente eliminándolo (mismo origen).

Para rendimiento, se consolidan las consultas N+1: `Ticket::getStats()` pasa de 5 queries a 1 usando `COUNT(*) FILTER (WHERE ...)`, lo mismo para `Location::getStats()` y `Equipment::getStats()`. Se añaden índices compuestos en `oti.tickets(status_id, created_at)`, `oti.tickets(user_id, created_at)`, y `oti.equipment(location_id, status)`. Se implementa un cache de resultados de 30 segundos para las queries del dashboard usando `apcu` o un archivo temporal.

**Decisiones clave de diseño:**
- Modificar `index.php` para que ejecute `SecurityMiddleware` antes de cualquier ruta, unificando headers, sesión, y CSRF en un solo punto.
- Crear `App\Helpers\SessionManager` que maneje regeneración de ID, timeout absoluto (12h) y de inactividad (30min).
- Crear `App\Helpers\InputValidator` con métodos estáticos `string()`, `int()`, `email()`, `uuid()` que unifiquen la validación.
- Los índices de BD se implementan en una migración SQL separada `database/migrations/001_performance_indexes.sql`.
- `BASE_URL` se vuelve dinámica: `(isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/OTI/'`.

**Trade-offs:**
- ✅ Ganas: Alta velocidad de implementación (2-3 semanas), mínimo riesgo de regression, preserva 100% de funcionalidad
- ❌ Sacrificas: Deuda técnica de CSS/JS permanece mayormente, no hay mejora de mantenibilidad a largo plazo
- Los cambios son localizados y reversibles uno por uno

**Riesgos potenciales:**
- CSP nonce requiere modificar TODOS los scripts inline en vistas (20+ archivos) — si se omite uno, el script deja de funcionar
- Si el rate limiter es demasiado agresivo, usuarios legítimos pueden ser bloqueados
- Los índices nuevos pueden afectar rendimiento de writes en tablas grandes

---

### Enfoque 2: «Refactor del Front Controller con Middleware Pipeline»
**Probabilidad:** 0.85 | **Complejidad:** Media-Alta | **Riesgo:** Medio

**Resumen:** Reestructurar `index.php` como un pipeline de middleware PSR-15 ligero y migrar la lógica de routing a un router discreto, separando preocupaciones y eliminando el espagueti de `if/switch` anidado.

**Descripción detallada:**
El `index.php` actual contiene 217 líneas con lógica de autenticación, verificación de roles, routing, y hasta creación de tickets inline. Este enfoque extrae todo a clases separadas: `Router`, `Request`, `Response`, y `MiddlewarePipeline`. El front controller se reduce a ~20 líneas que instancian el pipeline y ejecutan middleware en orden: `SessionMiddleware` → `CsrfMiddleware` → `AuthMiddleware` → `RoleMiddleware` → `RouterMiddleware`.

Cada ruta se define como un array asociativo en `routes/web.php`:
```php
return [
    'GET /login' => ['controller' => AuthController::class, 'action' => 'showLogin'],
    'POST /login' => ['controller' => AuthController::class, 'action' => 'login'],
    'GET /admin/dashboard' => ['controller' => AdminController::class, 'action' => 'dashboard', 'middleware' => ['auth', 'admin']],
    // ...
];
```

Esto permite que cada API endpoint también sea una ruta manejada por un controller, eliminando los 11 archivos `app/api/*.php` independientes y reemplazándolos por métodos en controllers. La autenticación se unifica: no más repetir `if (!isset($_SESSION['user']))` en cada API.

La lógica de `isOtiAdmin` por `strpos(role_name)` se reemplaza por una verificación real en BD del campo `es_admin` o una tabla `role_permissions`. Los roles se resuelven en el middleware y se almacenan en el objeto Request, disponible para todos los controllers subsiguientes.

**Decisiones clave de diseño:**
- Router ligero en `App\Core\Router` con matching de método + path, sin dependencias externas (~150 líneas)
- `Request` y `Response` wrappers sobre `$_SERVER`, `$_POST`, `$_GET`, etc. (implementación progresiva — no todo de una vez)
- Migración de APIs a controllers en orden de criticidad: primero `tickets.php`, `usuarios.php`, luego el resto
- `AuthController` asume las responsabilidades de `AuthService` y `User::findByIdentifier`
- Los endpoints API antiguos se mantienen como redirects temporales para no romver frontend

**Trade-offs:**
- ✅ Ganas: Código mantenible, testeable, con separación de responsabilidades; middleware reutilizable
- ❌ Sacrificas: Tiempo de implementación más largo (4-6 semanas); riesgo de regression en rutas menos usadas
- Mayoría de issues de seguridad se resuelven por diseño (middleware pipeline)
- El refactor puede introducir bugs sutiles en la lógica de autorización

**Riesgos potenciales:**
- Si el Router no se prueba exhaustivamente, rutas pueden fallar silenciosamente (404)
- La migración de APIs debe coordinarse con el frontend JS que llama a esas URLs
- Los desarrolladores existentes necesitarán capacitación en el nuevo patrón

---

### Enfoque 3: «Optimización Progresiva de Rendimiento + Caching Estratégico»
**Probabilidad:** 0.88 | **Complejidad:** Media | **Riesgo:** Bajo-Medio

**Resumen:** Aplicar una capa de caché inteligente con Redis o APCu, consolidar todas las consultas N+1 en queries analíticas eficientes, optimizar SSE para que no consulte BD en cada ciclo, y reducir el payload de JS/CSS.

**Descripción detallada:**
Se implementan tres niveles de caché: (1) cache en aplicación con APCu para datos de referencia (statuses, priorities, tipos de servicio) que cambian raramente; (2) cache de resultados de consultas pesadas (dashboard stats, análisis) con TTL de 30-60s; (3) cache HTTP con ETags para APIs de polling.

Las consultas N+1 críticas se reescriben. `Ticket::getStats()` que actualmente hace 5 queries separadas se consolida en una sola:
```sql
SELECT COUNT(*) as total,
       COUNT(*) FILTER (WHERE status_id = 1) as abiertos,
       COUNT(*) FILTER (WHERE status_id = 2) as en_proceso,
       COUNT(*) FILTER (WHERE status_id = 3) as resueltos,
       COUNT(*) FILTER (WHERE status_id = 4) as cerrados
FROM oti.tickets
```

El endpoint SSE se modifica para usar `pg_last_notify()` / `LISTEN/NOTIFY` de PostgreSQL, eliminando el polling cada 5s. Alternativamente, se implementa un `last_modified` timestamp en una tabla de metadatos que SSE consulta solo cuando hay cambios.

Se implementa `response_compression` en Apache (mod_deflate) para JS/CSS/JSON — ya está en .htaccess pero posiblemente no activo. Se añade `versioning` automático a assets (query string con hash del archivo).

El JS se optimiza: (1) se eliminan console.logs en producción mediante un build step simple (sed/replace); (2) se unifica `realtime.js` y `analisis-charts.js` para que compartan la conexión SSE y no compitan; (3) se implementa `AbortController` en todas las fetch calls.

**Decisiones clave de diseño:**
- Usar APCu en lugar de Redis para simplicidad (no requiere servicio externo), Redis como opción futura
- SSE modificado para usar `LISTEN/NOTIFY` de PostgreSQL — requiere trigger en tablas `tickets`, `equipment`
- Si LISTEN/NOTIFY es demasiado complejo, implementar polling condicional con `If-Modified-Since` y `ETag`
- Consolidar archivos JS: `realtime.js` + `analisis-charts.js` → `dashboard.js` (para reducir requests)
- `app.css` se divide en `base.css` (tokens, reset, tipografía) y `components.css` (cards, tablas, modales)

**Trade-offs:**
- ✅ Ganas: Mejora dramática de rendimiento (estima 60-80% menos queries), reducción de payload, SSE eficiente
- ❌ Sacrificas: APCu no es persistente (pierde cache al reiniciar PHP); LISTEN/NOTIFY añade complejidad a BD
- La división de CSS puede romper estilos si no se hace con cuidado
- El SSE vía LISTEN/NOTIFY requiere triggers en BD — operación de riesgo en producción

**Riesgos potenciales:**
- Si APCu no está instalado, el sistema fallará si no hay fallback — requiere verificar `extension_loaded('apcu')`
- LISTEN/NOTIFY de PostgreSQL requiere conexión persistente — incompatible con el singleton `Database::connect()`
- Cache de 30s puede hacer que los datos en dashboard no se sientan "en tiempo real"
- La consolidación de JS puede causar conflictos de nombres si las funciones no están correctamente namespaced

---

### Enfoque 4: «Reescritura Progresiva con HTMX (Hypermedia-Driven)»
**Probabilidad:** 0.08 | **Complejidad:** Alta | **Riesgo:** Alto

**Resumen:** Migrar progresivamente la interfaz de usuario de vanilla JS/SPA a HTMX, eliminando JavaScript complejo (Chart.js, SSE manual, fetch APIs) y reemplazándolo con hipermedia manejada por el servidor, manteniendo la misma arquitectura PHP.

**Descripción detallada:**
HTMX permite construir interfaces dinámicas sin JavaScript personalizado, intercambiando fragmentos HTML en lugar de JSON. El enfoque reemplaza los 11 endpoints API JSON con endpoints que devuelven HTML parcial (fragmentos de tabla, tarjetas, formularios). El SSE se reemplaza con `hx-trigger="every 5s"` que actualiza parciales específicos.

Chart.js (3 archivos JS, 548 líneas) se reemplaza con gráficos generados en servidor usando una librería PHP como `CpChart` o SVG inline generado desde las queries. Alternativamente, se mantiene Chart.js pero se reduce su uso a 1-2 gráficos clave.

El proceso de migración es progresivo: (1) primero se migran los componentes que no requieren Chart.js (tablas de tickets, listas de usuarios, perfiles); (2) luego el dashboard y análisis; (3) finalmente, el modal de búsqueda global.

Cada endpoint HTMX requiere una vista parcial reutilizable (`app/Views/partials/ticket_row.php`, `app/Views/partials/stats_cards.php`, etc.). Los controllers se modifican para detectar `HX-Request` header y devolver solo el fragmento en lugar de la página completa.

**Decisiones clave de diseño:**
- HTMX se carga desde CDN con SRI: `<script src="https://unpkg.com/htmx.org@2.0.4" integrity="..."></script>`
- No se usa `hx-vals` para datos sensibles (CSRF token se pasa via header personalizado con `hx-headers`)
- Los endpoints HTMX mantienen la misma validación de sesión/rol que los endpoints JSON actuales
- Chart.js se conserva temporalmente para los gráficos de análisis y se reemplaza al final
- Todas las vistas actuales se mantienen intactas durante la migración

**Trade-offs:**
- ✅ Ganas: Elimina ~1200 líneas de JS complejo; elimina bugs de sincronización SSE/API; HTML es inherentemente seguro contra XSS si se escapa; reduce significativamente el payload de red
- ❌ Sacrificas: Dependencia externa (HTMX CDN); experiencia de usuario puede sentirse menos "nativa"; Chart.js no tiene equivalente HTMX nativo; requiere reescribir todas las vistas que consumen APIs JSON
- Mayoría de bugs JS actuales desaparecen porque el código JS se simplifica drásticamente

**Riesgos potenciales:**
- HTMX v2 cambia significativamente respecto a v1 — si el CDN cambia, el sistema se rompe
- Migrar 20+ vistas es un esfuerzo de 8-12 semanas; durante la migración, el sistema tendrá dos modos operando
- Los administradores están acostumbrados a la sensación "en tiempo real" del dashboard — HTMX con polling puede sentirse más lento
- Si el servidor no maneja bien los requests parciales (sesiones, transacciones), puede haber race conditions
- La comunidad HTMX es más pequeña — encontrar soporte para bugs específicos puede ser difícil para un sistema municipal

---

### Enfoque 5: «Sistema de Seguridad Perimetral + Micro-Segmentación de APIs»
**Probabilidad:** 0.06 | **Complejidad:** Alta | **Riesgo:** Medio-Alto

**Resumen:** Implementar una capa de seguridad completa que envuelve todo el sistema existente sin modificarlo internamente, usando un proxy inverso (nginx o Apache como reverse proxy), Web Application Firewall (WAF), autenticación por token JWT externa, y headers de seguridad aplicados a nivel de servidor web.

**Descripción detallada:**
En lugar de modificar el código PHP existente (que es frágil y tiene 147 issues), este enfoque construye un "cinturón de seguridad" externo. Un proxy nginx se coloca frente a Apache, manejando: (1) terminación SSL/TLS; (2) WAF con reglas OWASP ModSecurity que bloquean XSS, SQLi, path traversal; (3) rate limiting por IP (40 req/min por endpoint); (4) headers de seguridad (CSP, HSTS, X-Frame-Options) aplicados a nivel de servidor, no de aplicación.

La autenticación se externaliza: se implementa un pequeño servicio PHP (`auth.php`) que emite tokens JWT firmados. Cada request a cualquier API pasa por verificación del JWT antes de llegar al código existente. El JWT contiene el rol y permisos reales (desde BD), eliminando el string parsing.

La sesión PHP se reemplaza con JWT stateless para las APIs, mientras que las páginas web mantienen sesión PHP pero validan el JWT en cada request del frontend. El CSRF se maneja con `SameSite=Strict` y tokens dobles (doble submit cookie).

Para el frontend, se implementa un pequeño `apiClient.js` que intercepta todos los fetch calls, añade el JWT, maneja renovación automática, y centraliza el manejo de errores. El código existente (`realtime.js`, `search.js`, etc.) se modifica mínimamente para usar el apiClient.

**Decisiones clave de diseño:**
- nginx como reverse proxy (puerto 8080 → Apache en 8081) — Apache no se modifica
- ModSecurity con reglas OWASP CRS — requiere compilación del módulo
- JWT implementado con `firebase/php-jwt` vía Composer (única dependencia externa permitida)
- JWT almacenado en `localStorage` (con httpOnly cookie como fallback para páginas)
- Renovación automática de JWT cada 15 minutos con refresh token en cookie httpOnly
- Rate limiting implementado con `ngx_http_limit_req_module` en nginx

**Trade-offs:**
- ✅ Ganas: No se modifica código PHP existente (bajo riesgo de regression); seguridad a nivel de infraestructura (difícil de bypassear incluso si PHP es vulnerable); JWT permite escalar horizontalmente
- ❌ Sacrificas: Latencia adicional (doble proxy); complejidad operativa (nginx + Apache + PHP-FPM); JWT añade overhead a cada request; tokens JWT en localStorage son vulnerables a XSS (aunque el WAF mitiga)
- Sin modificar el código PHP, los issues de N+1 queries y código muerto persisten

**Riesgos potenciales:**
- Configurar nginx como reverse proxy requiere cambios en Apache (Listen, VirtualHost) — puede caer el sitio si se configura mal
- ModSecurity con reglas OWASP CRS puede bloquear requests legítimos (falsos positivos)
- JWT en localStorage: si hay XSS (y el WAF falla), los tokens son robables
- nginx + Apache = doble mantenimiento — el equipo municipal necesitará saber ambas configuraciones
- Si el JWT expira y el refresh token falla, el usuario pierde acceso hasta login manual

---

### Enfoque 6: «Migración a PHP 8.4 Puro con Tipado Estricto y ADR Pattern (Action-Domain-Response)»
**Probabilidad:** 0.04 | **Complejidad:** Muy Alta | **Riesgo:** Muy Alto

**Resumen:** Reescribir el sistema por completo usando lo mejor de PHP 8.x (enums, readonly classes, named arguments, match expression, typed properties) con el patrón ADR en lugar de MVC tradicional, generación de un ORM mínimo tipado, y un sistema de migraciones de BD versionado — todo sin frameworks externos.

**Descripción detallada:**
Este enfoque es el más radical: no se modifica el código existente, se reconstruye el sistema pieza por pieza reemplazando archivos completos. Se implementa:

1. **Domain Layer** (Modelos): Cada entidad es una `readonly class` PHP 8.2+ con propiedades tipadas y un pequeño mapper. Se generan manualmente clases como:
```php
readonly class Ticket {
    public function __construct(
        public int $id,
        public string $code,
        public string $title,
        public string $description,
        public TicketStatus $status,
        public ?User $user,
        public \DateTimeImmutable $createdAt,
        // ...
    ) {}
}
```

2. **Action Layer** (Casos de uso): Cada acción del sistema (CrearTicket, AsignarTicket, CambiarEstado, etc.) es una clase invocable con una única responsabilidad. Reemplazan a Services + Controllers.

3. **Response Layer** (Vistas/JSON): Los actions devuelven objetos Response (HtmlResponse, JsonResponse, RedirectResponse) que el front controller serializa.

4. **Query Objects**: En lugar de métodos de modelo con 5 queries N+1, cada consulta es una clase separada tipada que devuelve colecciones de objetos Domain.

5. **Base de Datos**: Un repositorio por entidad con métodos explícitos. Se generan migraciones SQL versionadas en `database/migrations/`.

6. **Validación**: Un sistema de reglas de validación con `Attributes` de PHP 8.x:
```php
class CreateTicketRequest {
    #[Required, MaxLength(200)]
    public string $title;
    #[Required]
    public string $description;
    #[Exists('oti.ticket_statuses', 'id')]
    public int $statusId;
}
```

7. **Enums** para tipos fijos (TicketStatus, Priority, EquipmentType, LocationType) reemplazan strings mágicas.

Las 20+ vistas PHP actuales se reescriben con un sistema de templates minimalista (PHP puro con `include`, sin herencia compleja). Los 3 archivos JS se reescriben usando módulos ES6 nativos (import/export).

**Decisiones clave de diseño:**
- ADR en lugar de MVC: `Action` (orquestación), `Domain` (lógica de negocio), `Response` (presentación) — mejor separación que Controllers hinchados
- Repository Pattern: Cada entidad tiene `TicketRepository` con métodos como `findById(int $id): ?Ticket`, `findByUserId(int $userId): TicketCollection` — consultas siempre tipadas
- Query Objects separados para consultas complejas (dashboard stats, análisis, etc.)
- Migration Runner: `bin/migrate.php` que ejecuta archivos SQL en orden desde `database/migrations/`
- Value Objects para tipos como `Email`, `Phone`, `DNI`, `Password` — con validación en construcción
- Unit of Work para operaciones que involucran múltiples repos (crear ticket + actividad + notificación)

**Trade-offs:**
- ✅ Ganas: Sistema completamente tipado y seguro; 0 deuda técnica heredada; documentación viva (tipos = documentación); rendimiento máximo (PHP 8.4 JIT)
- ❌ Sacrificas: Tiempo de implementación (~16-24 semanas); riesgo extremo de regression (reescritura completa); el equipo debe aprender ADR y nuevos patrones; costos de desarrollo 10x respecto a otros enfoques
- Todos los issues (147) se resuelven por diseño, pero se introducen nuevos bugs propios de la reescritura

**Riesgos potenciales:**
- Riesgo #1: La reescritura puede abandonarse a mitad de camino (síndrome del greenfield)
- Riesgo #2: La migración de datos (de esquema legacy a nuevo) requiere mapeo campo por campo
- Riesgo #3: Los reportes/análisis personalizados que los usuarios han creado pueden no tener equivalente en el nuevo sistema
- Riesgo #4: La curva de aprendizaje para el equipo municipal es alta — pueden rechazar el cambio
- Riesgo #5: El presupuesto de 16-24 semanas puede exceder lo disponible para un sistema municipal
- Riesgo #6: Las pruebas de aceptación (UAT) deben cubrir cada funcionalidad existente — esfuerzo enorme

---

## Paso 4: Verificación de Diversidad

### Matriz de Diferenciación

| Dimensión | Enfoque 1 | Enfoque 2 | Enfoque 3 | Enfoque 4 | Enfoque 5 | Enfoque 6 |
|---|---|---|---|---|---|---|
| **Estrategia** | Quirúrgica | Estructural | Óptimización | Sustitución JS | Perimetral | Reescriptura |
| **Riesgo** | Bajo | Medio | Bajo-Medio | Alto | Medio-Alto | Muy Alto |
| **Esfuerzo** | 2-3 sem | 4-6 sem | 3-4 sem | 8-12 sem | 4-6 sem | 16-24 sem |
| **Deuda técnica** | Permanece | Reduce | Permanece | Reduce | Permanece | Elimina |
| **Dependencias nuevas** | APCu | Ninguna | APCu/Redis | HTMX + CDN | nginx + JWT | Ninguna |
| **Skills requeridos** | PHP medio | PHP avanzado | PHP + BD | PHP + HTMX | nginx + JWT | PHP 8.x experto |
| **Seguridad** | 90% resuelto | 95% resuelto | 50% resuelto | 70% resuelto | 95% resuelto | 99% resuelto |
| **Rendimiento** | 40% mejora | 20% mejora | 70% mejora | 30% mejora | 10% mejora | 60% mejora |
| **Mantenibilidad** | Baja mejora | Alta mejora | Sin mejora | Media mejora | Sin mejora | Máxima mejora |
| **Impacto usuario** | Imperceptible | Imperceptible | Positivo | Diferente | Imperceptible | Diferente |

### Análisis de Cobertura

Los 6 enfoques cubren regiones genuinamente diferentes del espacio de soluciones:

1. **Enfoque 1** (Cirugía) → Región de **alto impacto/bajo esfuerzo** — la opción pragmática ideal para producción inmediata
2. **Enfoque 2** (Middleware Pipeline) → Región de **arquitectura** — paga deuda técnica estructural
3. **Enfoque 3** (Optimización + Cache) → Región de **rendimiento** — complementa a cualquier otro enfoque
4. **Enfoque 4** (HTMX) → Región de **UX** — elimina JS complejo pero introduce dependencia
5. **Enfoque 5** (Seguridad Perimetral) → Región de **infraestructura** — no toca código, ideal si el equipo no puede modificar PHP
6. **Enfoque 6** (ADR + PHP 8.4) → Región de **reinicio total** — solución ideal a largo plazo pero inviable a corto

### Recomendación Preliminar

**Enfoque 1 + Enfoque 3 combinados** ofrecen el mejor ratio impacto/esfuerzo para un sistema municipal que necesita estar en producción en el corto plazo:

1. **Fase 1 (Semanas 1-2):** Ejecutar Enfoque 1 (seguridad críticos + high) — esto solo resuelve ~70 issues
2. **Fase 2 (Semanas 3-4):** Ejecutar Enfoque 3 (rendimiento N+1, índices, consolidación de queries) — esto resuelve ~30 issues adicionales
3. **Fase 3 (Semanas 5-6):** Refinar CSS (dividir app.css), mejorar JS (eliminar console.log, unificar SSE), accesibilidad básica — ~30 issues
4. **Deuda técnica restante (~17 issues low):** Se documentan como mejoras futuras

Esta combinación resuelve ~127 de 147 issues en 6 semanas con riesgo controlado.

---

## Resumen de Issues por Archivo

| Archivo | Líneas | Issues Detectados | Prioridad |
|---|---|---|---|
| `index.php` | 217 | Auth bypass, CSRF ausente, BASE_URL hardcodeada, sin CSP, sin timeout de sesión | CRÍTICA |
| `.htaccess` | 68 | CORS no configurado, HSTS ausente | ALTA |
| `app/Core/Database.php` | 69 | Contraseña default hardcodeada, sin prepared statement para search_path | CRÍTICA |
| `app/Controller/AuthController.php` | 54 | Auth por string matching, sin rate limiting | CRÍTICA |
| `app/Services/AuthService.php` | 186 | Auth por string matching en isAdmin, sin 2FA, sin bloqueo por intentos | CRÍTICA |
| `app/Services/TicketService.php` | 217 | TicketStatus definido aquí (code smell) | BAJA |
| `app/Models/Ticket.php` | 545 | N+1 en getStats, generateCode sin transacción, update() con SQL dinámico | ALTA |
| `app/Models/User.php` | 828 | N+1 en findByIdentifier, getAll con fallback query duplicada | ALTA |
| `app/Models/Location.php` | 295 | N+1 en getStats, getPath loop N+1 | MEDIA |
| `app/Models/Equipment.php` | 245 | N+1 en getStats | MEDIA |
| `app/api/tickets.php` | 277 | CORS abierto, SQL dinámico en update, sin CSRF, notificación con datos sin escapar | CRÍTICA |
| `app/api/usuarios.php` | 263 | CORS abierto, delete-permanent sin verificación, error disclosure | CRÍTICA |
| `app/api/equipos.php` | 498 | CORS abierto, update sin validación, assigned_user_name duplicado en BD | ALTA |
| `app/api/analisis.php` | 176 | CORS abierto | MEDIA |
| `app/api/stats.php` | 256 | CORS abierto, N+1 queries | ALTA |
| `app/api/sse.php` | 198 | CORS abierto, polling cada 5s sin cache, conexión persistente | MEDIA |
| `app/api/locations.php` | 380 | buildTree() recursivo O(n²), create sin validación de nombre | MEDIA |
| `app/api/search.php` | 119 | SQL injection potencial (? como placeholder), join a tabla que no existe | CRÍTICA |
| `app/api/profile.php` | 87 | require_once antes de autoload | BAJA |
| `app/api/notifications.php` | 49 | Sin cache, fetch cada 30s compite con SSE | BAJA |
| `app/api/user_tickets.php` | 155 | CORS abierto, error_reporting(0) pero errores se devuelven en JSON | ALTA |
| `app/Views/auth/login.php` | 140 | Sin nonce en scripts inline | ALTA |
| `app/Views/layouts/app.php` | 238 | Sin nonce, CSS inline, función getSvgIcon definida en medio del HTML | MEDIA |
| `app/Views/partials/header.php` | 51 | profile-dropdown no tiene aria, toggleProfileMenu no definida | MEDIA |
| `app/Views/admin/dashboard.php` | 311 | IDs duplicados ("stat-total", etc.), sin manejo de errores en chart rendering | ALTA |
| `app/Views/user/dashboard.php` | 256 | Estilos inline, queries ejecutadas en la vista (viola separación) | MEDIA |
| `public/assets/js/realtime.js` | 564 | Double callback SSE, console.log en prod, memory leaks potenciales | CRÍTICA |
| `public/assets/js/analisis-charts.js` | 548 | Polling duplicado (compite con SSE), console.error en prod | ALTA |
| `public/assets/js/search.js` | 277 | Event listener duplicado, sin manejo de errores granular | MEDIA |
| `public/assets/css/app.css` | 3350 | Monolítico, !important excesivo, layout shifting | ALTA |
| `app/Helpers/security.php` | 39 | CSP definido pero no usado en index.php | CRÍTICA |
| `app/Middleware/SecurityMiddleware.php` | 19 | Middleware definido pero no ejecutado | CRÍTICA |
| `.env` | 37 | APP_DEBUG=true, DB_PASSWORD débil, SMTP_PASSWORD vacío | CRÍTICA |

---

*Fin del documento — 6 enfoques de alto nivel documentados.*
