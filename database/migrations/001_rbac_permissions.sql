-- Migración RBAC - Permisos Granulares
-- Ejecutar: psql -f database/migrations/001_rbac_permissions.sql

-- Permisos granulares del sistema
INSERT INTO admin.permisos (codigo, nombre, modulo, accion, categoria, nivel) VALUES
  ('usuarios.crear', 'Crear Usuarios', 'usuarios', 'crear', 'administracion', 3),
  ('usuarios.editar', 'Editar Usuarios', 'usuarios', 'editar', 'administracion', 3),
  ('usuarios.eliminar', 'Eliminar Usuarios', 'usuarios', 'eliminar', 'administracion', 3),
  ('usuarios.ver', 'Ver Usuarios', 'usuarios', 'ver', 'administracion', 2),
  ('tickets.crear', 'Crear Tickets', 'tickets', 'crear', 'operativo', 1),
  ('tickets.editar', 'Editar Tickets', 'tickets', 'editar', 'operativo', 2),
  ('tickets.eliminar', 'Eliminar Tickets', 'tickets', 'eliminar', 'operativo', 3),
  ('tickets.asignar', 'Asignar Tickets', 'tickets', 'asignar', 'operativo', 2),
  ('tickets.resolver', 'Resolver Tickets', 'tickets', 'resolver', 'operativo', 2),
  ('tickets.cambiar-status', 'Cambiar Estado', 'tickets', 'cambiar-status', 'operativo', 2),
  ('equipos.ver', 'Ver Equipos', 'equipos', 'ver', 'operativo', 1),
  ('equipos.crear', 'Crear Equipos', 'equipos', 'crear', 'administracion', 2),
  ('equipos.editar', 'Editar Equipos', 'equipos', 'editar', 'administracion', 2),
  ('equipos.eliminar', 'Eliminar Equipos', 'equipos', 'eliminar', 'administracion', 3),
  ('equipos.asignar', 'Asignar Equipos', 'equipos', 'asignar', 'administracion', 2),
  ('reportes.ver', 'Ver Reportes', 'reportes', 'ver', 'administracion', 1),
  ('reportes.exportar', 'Exportar Reportes', 'reportes', 'exportar', 'administracion', 2),
  ('configuracion.ver', 'Ver Configuración', 'configuracion', 'ver', 'administracion', 3),
  ('configuracion.editar', 'Editar Configuración', 'configuracion', 'editar', 'administracion', 3),
  ('categorias.ver', 'Ver Categorías', 'categorias', 'ver', 'administracion', 1),
  ('categorias.editar', 'Editar Categorías', 'categorias', 'editar', 'administracion', 2),
  ('notificaciones.enviar', 'Enviar Notificaciones', 'notificaciones', 'enviar', 'operativo', 2),
  ('roles.ver', 'Ver Roles', 'roles', 'ver', 'administracion', 3),
  ('roles.editar', 'Editar Roles', 'roles', 'editar', 'administracion', 3);

-- Roles con permisos predefinidos
-- Admin: todos los permisos
INSERT INTO admin.rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id 
FROM admin.roles r
CROSS JOIN admin.permisos p
WHERE r.nombre IN ('superadmin', 'admin');

-- Gestor: permisos operativos y administrativos básicos
INSERT INTO admin.rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id 
FROM admin.roles r, admin.permisos p
WHERE r.nombre = 'gestor' 
AND p.codigo IN (
  'tickets.crear', 'tickets.editar', 'tickets.asignar', 'tickets.resolver', 'tickets.cambiar-status',
  'equipos.ver', 'equipos.editar', 'equipos.asignar',
  'reportes.ver', 'reportes.exportar'
);

-- Usuario estándar: solo operaciones básicas
INSERT INTO admin.rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id 
FROM admin.roles r, admin.permisos p
WHERE r.nombre = 'usuario'
AND p.codigo IN ('tickets.crear', 'tickets.ver', 'equipos.ver', 'categorias.ver');