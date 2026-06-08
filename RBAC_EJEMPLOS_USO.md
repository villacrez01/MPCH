# 📋 Ejemplos Prácticos de Uso - Sistema RBAC de Botones Dinámicos

**Versión:** 1.0  
**Destinatarios:** Desarrolladores, Integradores, Administradores de Sistema

---

## Tabla de Contenidos

1. [Ejemplos Básicos](#ejemplos-básicos)
2. [Casos de Uso Reales](#casos-de-uso-reales)
3. [Integración en Vistas](#integración-en-vistas)
4. [Implementación en Controladores](#implementación-en-controladores)
5. [Ejemplos Avanzados](#ejemplos-avanzados)

---

## Ejemplos Básicos

### 1.1 Mostrar/Ocultar Botones Basados en Permisos

#### Ejemplo: Módulo de Tickets

```html
<!-- Vista de lista de tickets -->
<div class="tickets-list">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Título</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>#1001</td>
        <td>Pantalla LCD no funciona</td>
        <td><span class="badge badge-open">Abierto</span></td>
        <td>
          <!-- Ver detalles (todos pueden) -->
          <button class="btn btn-secondary" data-permission="tickets.ver">
            👁️ Ver
          </button>

          <!-- Editar (solo técnicos) -->
          <button class="btn btn-secondary" data-permission="tickets.editar">
            ✏️ Editar
          </button>

          <!-- Asignar (solo supervisores) -->
          <button class="btn btn-secondary" data-permission="tickets.asignar">
            👤 Asignar
          </button>

          <!-- Comentar (usuarios con permiso) -->
          <button class="btn btn-secondary" data-permission="tickets.comentar">
            💬 Comentar
          </button>

          <!-- Cerrar (técnicos senior) -->
          <button class="btn btn-secondary" data-permission="tickets.cerrar">
            ✓ Cerrar
          </button>

          <!-- Eliminar (solo admin) -->
          <button class="btn btn-danger" data-permission="tickets.eliminar">
            🗑️ Eliminar
          </button>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<script>
// Los botones se actualizarán automáticamente cuando PermissionManager cargue
// No requiere código adicional si usas el atributo data-permission
</script>
```

**Resultado esperado según rol:**

| Acción | Usuario | Técnico | Admin |
|--------|---------|---------|-------|
| Ver | ✅ | ✅ | ✅ |
| Editar | ❌ | ✅ | ✅ |
| Asignar | ❌ | ❌ | ✅ |
| Comentar | ✅ | ✅ | ✅ |
| Cerrar | ❌ | ✅ | ✅ |
| Eliminar | ❌ | ❌ | ✅ |

---

### 1.2 Flujo Condicional Avanzado

```html
<!-- Mostrar panel solo si usuario tiene CUALQUIERA de estos permisos -->
<div data-permission-group="tickets.crear,tickets.editar,tickets.eliminar">
  <h3>Panel de Gestión</h3>
  <p>Tienes acceso a funciones de gestión</p>
</div>

<!-- Mostrar elemento solo si NO tiene este permiso -->
<div data-permission-hide="tickets.editar">
  <p class="info-text">💡 Solicita permisos de edición a tu supervisor</p>
</div>

<script>
// Estos atributos se procesan automáticamente por PermissionManager
// No requiere código JavaScript adicional
</script>
```

---

## Casos de Uso Reales

### 2.1 Módulo de Tickets - Flujo Completo

#### 2.1.1 Página de Creación de Ticket

```html
<!DOCTYPE html>
<html>
<head>
  <title>Crear Ticket</title>
  <link rel="stylesheet" href="/css/buttons-rbac.css">
</head>
<body>
  <div class="container">
    <h1>Crear Nuevo Ticket</h1>

    <form id="form-crear-ticket" class="form-grid">
      <!-- Campo: Título -->
      <div class="form-group">
        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" required 
               placeholder="Describe brevemente el problema">
        <span class="form-error" id="titulo-error"></span>
      </div>

      <!-- Campo: Descripción -->
      <div class="form-group">
        <label for="descripcion">Descripción Detallada</label>
        <textarea id="descripcion" name="descripcion" rows="4" required
                  placeholder="Proporciona todos los detalles relevantes"></textarea>
      </div>

      <!-- Campo: Categoría (solo técnicos pueden asignar) -->
      <div class="form-group" data-permission-group="tickets.asignar">
        <label for="categoria">Categoría (Requiere Permiso Especial)</label>
        <select id="categoria" name="categoria_id">
          <option>Selecciona categoría...</option>
          <option value="1">Hardware</option>
          <option value="2">Software</option>
          <option value="3">Red</option>
        </select>
        <small>Solo visible para técnicos autorizados</small>
      </div>

      <!-- Botones de acción -->
      <div class="form-actions">
        <!-- Crear (requiere permiso) -->
        <button type="submit" class="btn btn-primary" data-permission="tickets.crear">
          <span class="btn-spinner" style="display:none;"></span>
          ➕ Crear Ticket
        </button>

        <!-- Cancelar (siempre disponible) -->
        <button type="button" class="btn btn-secondary" onclick="history.back()">
          ✕ Cancelar
        </button>

        <!-- Crear y Asignar (solo si tiene ambos permisos) -->
        <button type="button" class="btn btn-secondary" 
                data-permission="tickets.crear"
                data-permission-and="tickets.asignar"
                id="btn-crear-asignar">
          ➕ Crear y Asignar
        </button>
      </div>

      <!-- Mensaje de ayuda si no tiene permiso -->
      <div data-permission-hide="tickets.crear" class="alert alert-info">
        <strong>⚠️ Sin Permiso:</strong> No tienes autorización para crear tickets.
        Contacta a tu supervisor para solicitar el permiso "Crear Tickets".
      </div>
    </form>
  </div>

  <script src="/js/PermissionManager.js"></script>
  <script>
    const form = document.getElementById('form-crear-ticket');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      // Verificar permisos antes de enviar
      if (!window.permissions.can('tickets.crear')) {
        alert('No tienes permiso para crear tickets');
        return;
      }

      // Mostrar loading
      const submitBtn = form.querySelector('[data-permission="tickets.crear"]');
      submitBtn.disabled = true;
      submitBtn.querySelector('.btn-spinner').style.display = 'inline-block';

      try {
        const formData = new FormData(form);
        const response = await fetch('/api/tickets', {
          method: 'POST',
          body: formData
        });

        if (!response.ok) {
          throw new Error('Error al crear ticket');
        }

        const data = await response.json();
        
        // Éxito
        showToast('✓ Ticket creado exitosamente', 'success');
        setTimeout(() => {
          window.location.href = `/tickets/${data.ticket_id}`;
        }, 1500);

      } catch (error) {
        showToast(`✗ Error: ${error.message}`, 'error');
        console.error(error);

      } finally {
        submitBtn.disabled = false;
        submitBtn.querySelector('.btn-spinner').style.display = 'none';
      }
    });

    // Manejo especial para botón "Crear y Asignar"
    document.getElementById('btn-crear-asignar').addEventListener('click', async () => {
      if (!window.permissions.canAll('tickets.crear', 'tickets.asignar')) {
        alert('Necesitas ambos permisos: Crear y Asignar');
        return;
      }

      // Ir a página de creación con parámetro de autoasignación
      window.location.href = '/tickets/new?auto_assign=true';
    });

    function showToast(message, type = 'info') {
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      toast.textContent = message;
      document.body.appendChild(toast);
      
      setTimeout(() => toast.remove(), 3000);
    }
  </script>
</body>
</html>
```

---

#### 2.1.2 Página de Detalles de Ticket

```html
<!DOCTYPE html>
<html>
<head>
  <title>Detalles del Ticket</title>
  <link rel="stylesheet" href="/css/buttons-rbac.css">
</head>
<body>
  <div class="container">
    <!-- Header del ticket -->
    <div class="ticket-header">
      <h1>Ticket #<span id="ticket-id"></span></h1>
      <span id="ticket-status" class="badge"></span>
      <span id="ticket-priority" class="badge"></span>
    </div>

    <!-- Información del ticket -->
    <div class="ticket-info">
      <div class="info-row">
        <strong>Creado por:</strong>
        <span id="ticket-creator"></span>
      </div>
      <div class="info-row">
        <strong>Asignado a:</strong>
        <span id="ticket-assigned-to"></span>
      </div>
      <div class="info-row">
        <strong>Descripción:</strong>
        <p id="ticket-description"></p>
      </div>
    </div>

    <!-- Panel de acciones -->
    <div class="actions-panel">
      <h3>Acciones Disponibles</h3>

      <!-- Editar ticket -->
      <button class="btn btn-secondary" data-permission="tickets.editar"
              onclick="editarTicket()">
        ✏️ Editar Ticket
      </button>

      <!-- Asignar a técnico -->
      <button class="btn btn-secondary" data-permission="tickets.asignar"
              onclick="asignarTicket()">
        👤 Asignar
      </button>

      <!-- Agregar comentario -->
      <button class="btn btn-secondary" data-permission="tickets.comentar"
              onclick="abrirFormularioComentario()">
        💬 Comentar
      </button>

      <!-- Cerrar ticket -->
      <button class="btn btn-primary" data-permission="tickets.cerrar"
              onclick="cerrarTicket()">
        ✓ Cerrar Ticket
      </button>

      <!-- Reabrir ticket (solo si está cerrado) -->
      <button class="btn btn-secondary" data-permission="tickets.reabrir"
              id="btn-reabrir" style="display:none;"
              onclick="reabrirTicket()">
        ↺ Reabrir
      </button>

      <!-- Eliminar (solo admin) -->
      <button class="btn btn-danger" data-permission="tickets.eliminar"
              onclick="eliminarTicket()">
        🗑️ Eliminar
      </button>
    </div>

    <!-- Comentarios -->
    <div class="comments-section">
      <h3>Comentarios</h3>
      <div id="comments-list"></div>

      <!-- Nuevo comentario -->
      <div class="new-comment-form" id="new-comment-form" style="display:none;">
        <textarea id="comment-text" placeholder="Escribe tu comentario..."></textarea>
        <button class="btn btn-primary" data-permission="tickets.comentar"
                onclick="publicarComentario()">
          Publicar
        </button>
      </div>
    </div>
  </div>

  <script src="/js/PermissionManager.js"></script>
  <script>
    const ticketId = new URLSearchParams(window.location.search).get('id');

    // Cargar datos del ticket
    async function cargarTicket() {
      try {
        const response = await fetch(`/api/tickets/${ticketId}`);
        const data = await response.json();
        
        document.getElementById('ticket-id').textContent = data.id;
        document.getElementById('ticket-status').textContent = data.status;
        document.getElementById('ticket-creator').textContent = data.created_by;
        document.getElementById('ticket-assigned-to').textContent = 
          data.assigned_to || 'Sin asignar';
        document.getElementById('ticket-description').textContent = data.description;

        // Mostrar/ocultar botón reabrir según estado
        if (data.status === 'cerrado') {
          document.getElementById('btn-reabrir').style.display = 'inline-block';
        }

      } catch (error) {
        console.error('Error cargando ticket:', error);
      }
    }

    // Funciones de acción
    async function editarTicket() {
      if (!window.permissions.can('tickets.editar')) {
        alert('No tienes permiso para editar');
        return;
      }
      window.location.href = `/tickets/${ticketId}/edit`;
    }

    async function asignarTicket() {
      if (!window.permissions.can('tickets.asignar')) {
        alert('No tienes permiso para asignar');
        return;
      }
      // Abrir modal de asignación
      openAssignModal();
    }

    async function cerrarTicket() {
      if (!window.permissions.can('tickets.cerrar')) {
        alert('No tienes permiso para cerrar');
        return;
      }

      if (confirm('¿Estás seguro de que deseas cerrar este ticket?')) {
        try {
          const response = await fetch(`/api/tickets/${ticketId}/close`, {
            method: 'POST'
          });

          if (response.ok) {
            showToast('Ticket cerrado exitosamente', 'success');
            setTimeout(() => location.reload(), 1500);
          }
        } catch (error) {
          showToast('Error al cerrar ticket', 'error');
        }
      }
    }

    async function eliminarTicket() {
      if (!window.permissions.can('tickets.eliminar')) {
        alert('No tienes permiso para eliminar');
        return;
      }

      if (confirm('⚠️ Esto eliminará el ticket permanentemente. ¿Continuar?')) {
        try {
          const response = await fetch(`/api/tickets/${ticketId}`, {
            method: 'DELETE'
          });

          if (response.ok) {
            showToast('Ticket eliminado', 'success');
            setTimeout(() => window.location.href = '/tickets', 2000);
          }
        } catch (error) {
          showToast('Error al eliminar', 'error');
        }
      }
    }

    // Cargar datos al iniciar
    window.permissions.load().then(() => {
      cargarTicket();
    });
  </script>
</body>
</html>
```

---

### 2.2 Módulo de Usuarios - Gestión de Roles y Permisos

#### 2.2.1 Página de Administración de Usuarios

```html
<!DOCTYPE html>
<html>
<head>
  <title>Gestión de Usuarios</title>
  <link rel="stylesheet" href="/css/buttons-rbac.css">
</head>
<body>
  <div class="container">
    <h1>Gestión de Usuarios</h1>

    <!-- Tabla de usuarios -->
    <table class="users-table">
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Email</th>
          <th>Rol Actual</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="users-tbody">
        <!-- Población dinámica con JavaScript -->
      </tbody>
    </table>
  </div>

  <script src="/js/PermissionManager.js"></script>
  <script>
    // Cargar y mostrar usuarios
    async function cargarUsuarios() {
      try {
        const response = await fetch('/api/usuarios');
        const usuarios = await response.json();

        const tbody = document.getElementById('users-tbody');
        tbody.innerHTML = '';

        usuarios.forEach(usuario => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td>${usuario.nombre} ${usuario.apellidos}</td>
            <td>${usuario.email}</td>
            <td><span class="badge">${usuario.role_name}</span></td>
            <td>
              <span class="badge ${usuario.activo ? 'badge-active' : 'badge-inactive'}">
                ${usuario.activo ? 'Activo' : 'Inactivo'}
              </span>
            </td>
            <td class="actions-cell">
              <!-- Ver detalles -->
              <button class="btn btn-sm btn-secondary" 
                      data-permission="usuarios.ver"
                      onclick="verDetalles(${usuario.id})">
                Ver
              </button>

              <!-- Editar -->
              <button class="btn btn-sm btn-secondary" 
                      data-permission="usuarios.editar"
                      onclick="editarUsuario(${usuario.id})">
                Editar
              </button>

              <!-- Cambiar rol -->
              <button class="btn btn-sm btn-secondary" 
                      data-permission="usuarios.asignar-rol"
                      onclick="cambiarRol(${usuario.id})">
                Rol
              </button>

              <!-- Desactivar/Activar -->
              <button class="btn btn-sm btn-warning" 
                      data-permission="usuarios.cambiar-estado"
                      onclick="cambiarEstado(${usuario.id}, ${!usuario.activo})">
                ${usuario.activo ? 'Desactivar' : 'Activar'}
              </button>

              <!-- Eliminar -->
              <button class="btn btn-sm btn-danger" 
                      data-permission="usuarios.eliminar"
                      onclick="eliminarUsuario(${usuario.id})">
                Eliminar
              </button>
            </td>
          `;
          tbody.appendChild(row);
        });

      } catch (error) {
        console.error('Error cargando usuarios:', error);
      }
    }

    // Cambiar rol de usuario
    async function cambiarRol(usuarioId) {
      if (!window.permissions.can('usuarios.asignar-rol')) {
        alert('No tienes permiso para asignar roles');
        return;
      }

      const rolId = prompt('Ingresa el ID del nuevo rol:');
      if (!rolId) return;

      try {
        const response = await fetch(`/api/usuarios/${usuarioId}/rol`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ rol_id: rolId })
        });

        if (response.ok) {
          showToast('Rol asignado correctamente', 'success');
          
          // Refrescar permisos si es el usuario actual
          const userData = await window.permissions.user;
          if (userData?.id === usuarioId) {
            await window.permissions.refresh();
          }

          // Refrescar tabla
          cargarUsuarios();
        }

      } catch (error) {
        showToast('Error asignando rol', 'error');
      }
    }

    // Cambiar estado (activo/inactivo)
    async function cambiarEstado(usuarioId, activo) {
      if (!window.permissions.can('usuarios.cambiar-estado')) {
        alert('No tienes permiso para cambiar estado');
        return;
      }

      try {
        const response = await fetch(`/api/usuarios/${usuarioId}/estado`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ activo })
        });

        if (response.ok) {
          showToast(`Usuario ${activo ? 'activado' : 'desactivado'}`, 'success');
          cargarUsuarios();
        }

      } catch (error) {
        showToast('Error cambiando estado', 'error');
      }
    }

    // Cargar al iniciar
    window.permissions.load().then(() => {
      cargarUsuarios();
    });
  </script>
</body>
</html>
```

---

### 2.3 Módulo de Equipos - Gestión y Reasignación

```html
<!-- Botón para reasignar equipo con validación contextual -->
<button class="btn btn-secondary" 
        data-permission="equipos.reasignar"
        onclick="reasignarEquipo(equipoId)">
  ⇄ Reasignar
</button>

<script>
  // Reasignar equipo (solo técnicos pueden)
  async function reasignarEquipo(equipoId) {
    if (!window.permissions.can('equipos.reasignar')) {
      showToast('No tienes permiso para reasignar equipos', 'error');
      return;
    }

    const nuevoUsuarioId = prompt('Ingresa el ID del nuevo usuario:');
    if (!nuevoUsuarioId) return;

    try {
      const response = await fetch(`/api/equipos/${equipoId}/reasignar`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ usuario_id: nuevoUsuarioId })
      });

      if (response.ok) {
        showToast('Equipo reasignado exitosamente', 'success');
        location.reload();
      } else {
        const error = await response.json();
        showToast(`Error: ${error.message}`, 'error');
      }

    } catch (error) {
      showToast('Error al reasignar equipo', 'error');
      console.error(error);
    }
  }
</script>
```

---

## Integración en Vistas

### 3.1 Uso en Plantillas (Ejemplo con PHP)

```php
<?php
// archivo: app/Views/tickets/list.php

use App\Services\AuthService;
use App\Middleware\AuthorizationMiddleware;
?>

<div class="tickets-container">
  <h1>Mis Tickets</h1>

  <!-- Botón crear (validación en servidor) -->
  <button class="btn btn-primary" data-permission="tickets.crear"
          onclick="window.location.href='/tickets/new'">
    ➕ Crear Nuevo Ticket
  </button>

  <!-- Tabla de tickets -->
  <table class="tickets-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Título</th>
        <th>Estado</th>
        <th>Asignado a</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tickets as $ticket): ?>
        <tr>
          <td>#<?php echo htmlspecialchars($ticket['id']); ?></td>
          <td><?php echo htmlspecialchars($ticket['titulo']); ?></td>
          <td>
            <span class="badge badge-<?php echo $ticket['status']; ?>">
              <?php echo ucfirst($ticket['status']); ?>
            </span>
          </td>
          <td><?php echo htmlspecialchars($ticket['assigned_to'] ?? 'Sin asignar'); ?></td>
          <td>
            <!-- Ver -->
            <a href="/tickets/<?php echo $ticket['id']; ?>" 
               class="btn btn-sm btn-secondary" 
               data-permission="tickets.ver">
              Ver
            </a>

            <!-- Editar -->
            <button class="btn btn-sm btn-secondary" 
                    data-permission="tickets.editar"
                    onclick="editarTicket(<?php echo $ticket['id']; ?>)">
              Editar
            </button>

            <!-- Asignar -->
            <button class="btn btn-sm btn-secondary" 
                    data-permission="tickets.asignar"
                    onclick="asignarTicket(<?php echo $ticket['id']; ?>)">
              Asignar
            </button>

            <!-- Eliminar -->
            <button class="btn btn-sm btn-danger" 
                    data-permission="tickets.eliminar"
                    onclick="confirmarEliminacion(<?php echo $ticket['id']; ?>)">
              Eliminar
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="/js/PermissionManager.js"></script>
<script src="/js/RealtimeSync.js"></script>

<script>
  // Monitorear cambios en tiempo real
  window.realtimeSync.on('permission_changed', (message) => {
    console.log('Permisos actualizados:', message);
    location.reload(); // Recargar para reflejar cambios
  });

  function editarTicket(id) {
    window.location.href = `/tickets/${id}/edit`;
  }

  function asignarTicket(id) {
    const tecnicoId = prompt('ID del técnico:');
    if (tecnicoId) {
      fetch(`/api/tickets/${id}/assign`, {
        method: 'POST',
        body: JSON.stringify({ tecnico_id: tecnicoId })
      }).then(r => r.json()).then(data => {
        if (data.success) {
          location.reload();
        }
      });
    }
  }

  function confirmarEliminacion(id) {
    if (confirm('¿Eliminar este ticket?')) {
      fetch(`/api/tickets/${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
          if (data.success) location.reload();
        });
    }
  }
</script>
```

---

## Implementación en Controladores

### 4.1 Controlador de Tickets con RBAC

```php
<?php
// archivo: app/Controller/TicketController.php

namespace App\Controller;

use App\Services\AuthService;
use App\Middleware\AuthorizationMiddleware;
use App\Models\Ticket;
use App\Core\View;

class TicketController
{
    /**
     * GET /tickets
     * Lista de tickets (con validación de permisos)
     */
    public function index()
    {
        try {
            AuthorizationMiddleware::requirePermission('tickets.ver');

            $tickets = Ticket::all();
            View::render('tickets/list', ['tickets' => $tickets]);

        } catch (\Exception $e) {
            http_response_code(403);
            View::render('errors/forbidden', ['message' => $e->getMessage()]);
        }
    }

    /**
     * GET /tickets/new
     * Formulario para crear ticket
     */
    public function create()
    {
        try {
            AuthorizationMiddleware::requirePermission('tickets.crear');
            View::render('tickets/create');

        } catch (\Exception $e) {
            http_response_code(403);
            View::render('errors/forbidden', ['message' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/tickets
     * Crear nuevo ticket
     */
    public function store()
    {
        try {
            AuthorizationMiddleware::requirePermission('tickets.crear');

            $data = json_decode(file_get_contents('php://input'), true);

            // Validar datos
            if (empty($data['titulo']) || empty($data['descripcion'])) {
                http_response_code(400);
                View::json(['error' => 'Campos requeridos faltantes']);
                return;
            }

            // Crear ticket
            $ticket = Ticket::create([
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'],
                'usuario_id' => AuthService::getCurrentUserId(),
                'status' => 'abierto'
            ]);

            http_response_code(201);
            View::json([
                'success' => true,
                'ticket_id' => $ticket->id,
                'message' => 'Ticket creado exitosamente'
            ]);

        } catch (\Exception $e) {
            http_response_code(403);
            View::json(['error' => $e->getMessage()]);
        }
    }

    /**
     * PUT /api/tickets/:id
     * Editar ticket (solo si es propietario o tiene permiso admin)
     */
    public function update($id)
    {
        try {
            $ticket = Ticket::find($id);
            if (!$ticket) {
                http_response_code(404);
                View::json(['error' => 'Ticket no encontrado']);
                return;
            }

            // Validación con callback customizado
            AuthorizationMiddleware::requirePermission('tickets.editar', function($user) use ($ticket) {
                // Admin puede editar cualquier ticket
                if (AuthService::isAdmin($user['id'])) {
                    return true;
                }
                // Usuario solo puede editar sus propios tickets
                return $ticket->usuario_id === $user['id'];
            });

            $data = json_decode(file_get_contents('php://input'), true);
            $ticket->update($data);

            View::json(['success' => true, 'message' => 'Ticket actualizado']);

        } catch (\Exception $e) {
            http_response_code(403);
            View::json(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/tickets/:id/assign
     * Asignar ticket a técnico
     */
    public function assign($id)
    {
        try {
            AuthorizationMiddleware::requirePermission('tickets.asignar');

            $ticket = Ticket::find($id);
            if (!$ticket) {
                http_response_code(404);
                View::json(['error' => 'Ticket no encontrado']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $ticket->assigned_to = $data['tecnico_id'];
            $ticket->save();

            // Notificar cambio en tiempo real
            $this->notifyPermissionChange($data['tecnico_id']);

            View::json(['success' => true, 'message' => 'Ticket asignado']);

        } catch (\Exception $e) {
            http_response_code(403);
            View::json(['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/tickets/:id
     * Eliminar ticket (solo admin)
     */
    public function destroy($id)
    {
        try {
            AuthorizationMiddleware::requirePermission('tickets.eliminar');

            $ticket = Ticket::find($id);
            if (!$ticket) {
                http_response_code(404);
                View::json(['error' => 'Ticket no encontrado']);
                return;
            }

            $ticket->delete();
            View::json(['success' => true, 'message' => 'Ticket eliminado']);

        } catch (\Exception $e) {
            http_response_code(403);
            View::json(['error' => $e->getMessage()]);
        }
    }

    /**
     * Notificar cambios a través de WebSocket
     */
    private function notifyPermissionChange($userId)
    {
        // Esto se implementaría en el servidor WebSocket
        // Enviaría notificación al cliente del usuario
    }
}
```

---

## Ejemplos Avanzados

### 5.1 Permisos Condicionales por Contexto

```javascript
/**
 * Verificar permisos contextualmente
 * Ejemplo: Usuario puede editar solo sus propios tickets
 */

class ContextualPermissions {
  static canEditTicket(userId, ticket) {
    // Debe tener permiso base
    if (!window.permissions.can('tickets.editar')) {
      return false;
    }

    const currentUser = window.permissions.user;
    const isAdmin = window.permissions.isAdmin;

    // Admin siempre puede editar
    if (isAdmin) {
      return true;
    }

    // Usuario normal solo puede editar sus propios tickets
    return ticket.usuario_id === currentUser.id;
  }

  static canDeleteTicket(userId, ticket) {
    // Solo admin puede eliminar
    if (!window.permissions.can('tickets.eliminar')) {
      return false;
    }

    return window.permissions.isAdmin;
  }

  static canAssignToUser(supervisorId, targetUserId) {
    // Validar que no pueda asignarse a sí mismo
    if (!window.permissions.can('usuarios.asignar-rol')) {
      return false;
    }

    return supervisorId !== targetUserId;
  }
}

// Uso:
if (ContextualPermissions.canEditTicket(userId, ticket)) {
  // Mostrar botón editar
}
```

---

### 5.2 Sistema de Auditoría de Cambios de Permisos

```php
<?php
// archivo: app/Services/PermissionAuditService.php

namespace App\Services;

use App\Core\Database;

class PermissionAuditService
{
    /**
     * Registra cambio de permisos en auditoría
     */
    public static function logChange(
        int $userId,
        string $action,
        array $changes,
        ?string $reason = null
    ): void {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT INTO admin.permiso_audit_log 
            (usuario_id, accion, tabla_afectada, cambios, ip_address, user_agent)
            VALUES (:usuario_id, :accion, 'usuarios', :cambios, :ip, :user_agent)
        ");

        $stmt->execute([
            'usuario_id' => AuthService::getCurrentUserId(),
            'accion' => $action,
            'cambios' => json_encode($changes),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        // Invalidar caché del usuario afectado
        AuthService::invalidatePermissionCache($userId);
    }

    /**
     * Obtiene historial de cambios de permisos de un usuario
     */
    public static function getUserHistory(int $userId, int $limit = 50): array
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT * FROM admin.permiso_audit_log
            WHERE usuario_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
```

---

### 5.3 Migración a Nuevo Rol con Permisos Temporales

```php
<?php
// Asignar permiso temporal a usuario (ej: durante transición de rol)

$pdo = Database::connect();

$stmt = $pdo->prepare("
    INSERT INTO admin.usuario_permiso_especial 
    (usuario_id, permiso_id, razon, expira_en)
    VALUES (:usuario_id, :permiso_id, :razon, NOW() + INTERVAL '7 days')
");

$stmt->execute([
    'usuario_id' => 42,
    'permiso_id' => 5, // tickets.eliminar
    'razon' => 'Permiso temporal para migración de rol - Expira en 7 días'
]);

// Notificar usuario
email_user(42, 'Permiso temporal otorgado', 
  'Tienes permiso temporal de eliminar tickets hasta ' . date('Y-m-d H:i'));
?>
```

---

### 5.4 Dashboard de Auditoría de Permisos

```html
<!DOCTYPE html>
<html>
<head>
  <title>Auditoría de Permisos</title>
  <link rel="stylesheet" href="/css/buttons-rbac.css">
</head>
<body>
  <div class="container">
    <h1>Auditoría de Cambios de Permisos</h1>

    <!-- Filtros -->
    <div class="filter-group">
      <input type="text" id="filter-user" placeholder="Usuario...">
      <input type="text" id="filter-action" placeholder="Acción...">
      <button class="btn btn-secondary" onclick="aplicarFiltros()">Filtrar</button>
    </div>

    <!-- Tabla de auditoría -->
    <table class="audit-table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Usuario</th>
          <th>Acción</th>
          <th>Cambios</th>
          <th>IP Address</th>
          <th>User Agent</th>
        </tr>
      </thead>
      <tbody id="audit-tbody"></tbody>
    </table>
  </div>

  <script src="/js/PermissionManager.js"></script>
  <script>
    async function cargarAuditoria() {
      try {
        const response = await fetch('/api/admin/audit-log');
        const logs = await response.json();

        const tbody = document.getElementById('audit-tbody');
        tbody.innerHTML = '';

        logs.forEach(log => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td>${new Date(log.created_at).toLocaleString()}</td>
            <td>${log.usuario_email}</td>
            <td><span class="badge">${log.accion}</span></td>
            <td>
              <details>
                <summary>Ver cambios</summary>
                <pre>${JSON.stringify(JSON.parse(log.cambios), null, 2)}</pre>
              </details>
            </td>
            <td><code>${log.ip_address}</code></td>
            <td><small>${log.user_agent.substring(0, 50)}...</small></td>
          `;
          tbody.appendChild(row);
        });

      } catch (error) {
        console.error('Error cargando auditoría:', error);
      }
    }

    window.permissions.load().then(() => {
      cargarAuditoria();
    });
  </script>
</body>
</html>
```

---

## Resumen de Patrones

| Patrón | Código | Caso de Uso |
|--------|--------|------------|
| Permiso simple | `data-permission="tickets.crear"` | Mostrar botón si tiene permiso |
| Permiso grupo | `data-permission-group="accion1,accion2"` | Mostrar si tiene CUALQUIERA de los permisos |
| Permiso oculto | `data-permission-hide="admin.ver"` | Mostrar solo si NO tiene permiso |
| Verificación JS | `window.permissions.can('ticket.editar')` | Lógica condicional en JavaScript |
| Validación PHP | `AuthorizationMiddleware::requirePermission()` | Proteger endpoints del servidor |

---

**Fin de Ejemplos Prácticos**

*Última actualización: 2026-05-27*

