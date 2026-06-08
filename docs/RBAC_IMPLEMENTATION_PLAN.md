# Plan de Acción - Implementación RBAC Botones Dinámicos

## Fase 1: Preparación (30 min)

1. Ejecutar migración SQL: `database/migrations/001_rbac_permissions.sql`
2. Verificar que las tablas existan en la base de datos
3. Confirmar que los roles y permisos están creados

## Fase 2: Backend (45 min)

1. **Implementar API de Permisos** (`app/api/permissions.php`)
   - GET `/api/permissions?action=get-user-permissions`
   - Respuesta JSON con array de permisos del usuario
   - Cache de 5 minutos en sesión

2. **Actualizar AuthService** (`app/Services/AuthService.php`)
   - Método `hasPermission(string $permission)` ya existe
   - Verificar `invalidatePermissionCache` funciona correctamente

3. **Middleware de Seguridad** (`app/Middleware/AuthorizationMiddleware.php`)
   - Ya implementado, usar en endpoints sensibles
   - Patrón: `AuthorizationMiddleware::requirePermission('tickets.editar');`

## Fase 3: Frontend (60 min)

1. **Incluir scripts** en el layout principal:
   ```html
   <script src="/assets/js/rbac-store.js"></script>
   <script src="/assets/js/rbac-button.js"></script>
   <link rel="stylesheet" href="/assets/css/rbac-buttons.css">
   ```

2. **Reemplazar botones estáticos** con componentes dinámicos:
   ```html
   <!-- Antes -->
   <a href="user/reportar" class="btn btn-primary">Nuevo Ticket</a>
   
   <!-- Después -->
   <rbac-button permission="tickets.crear" action="redirect" target="user/reportar" variant="primary" icon="create">
       Nuevo Ticket
   </rbac-button>
   ```

3. **Usar helper PHP** en vistas:
   ```php
   <?= rbac_button('tickets.editar', 'primary', 'Editar', ['onclick' => "edit({$id})"]) ?>
   ```

## Fase 4: Verificación (30 min)

1. Probar login como admin → debe ver todos los botones
2. Probar login como usuario estándar → solo botones permitidos
3. Verificar middleware bloquea peticiones sin permiso backend
4. Validar accesibilidad (navegación con Tab, lectores de pantalla)

## Archivos Modificados/Creados

| Archivo | Acción | Prioridad |
|---------|--------|-----------|
| `database/migrations/001_rbac_permissions.sql` | CREATE | Alta |
| `app/api/permissions.php` | CREATE | Alta |
| `app/Helpers/PermissionHelper.php` | CREATE | Media |
| `public/assets/js/rbac-store.js` | CREATE | Alta |
| `public/assets/js/rbac-button.js` | CREATE | Alta |
| `public/assets/css/rbac-buttons.css` | CREATE | Media |
| `docs/RBAC_GUIDE.md` | CREATE | Baja |
| `docs/RBAC_IMPLEMENTATION_PLAN.md` | CREATE | Baja |