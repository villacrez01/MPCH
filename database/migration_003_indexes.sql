-- Sprint 3: Índices compuestos para optimizar queries frecuentes
-- Ejecutar: psql -U user -d dbname -f database/migration_003_indexes.sql

BEGIN;

-- ============================================================
-- Índices para oti.tickets (tabla más consultada)
-- ============================================================

-- Cubre: filtros por status + fecha (dashboard stats, listados)
CREATE INDEX IF NOT EXISTS idx_tickets_status_created
    ON oti.tickets (status_id, created_at DESC);

-- Cubre: filtros por usuario + status (mis tickets)
CREATE INDEX IF NOT EXISTS idx_tickets_user_status
    ON oti.tickets (user_id, status_id);

-- Cubre: asignación + filtro por status (admin tickets asignados)
CREATE INDEX IF NOT EXISTS idx_tickets_assigned_status
    ON oti.tickets (assigned_admin_id, status_id);

-- Cubre: búsqueda por código (búsqueda exacta en search)
CREATE INDEX IF NOT EXISTS idx_tickets_code
    ON oti.tickets (code);

-- Cubre: prioridad + status (filtros avanzados)
CREATE INDEX IF NOT EXISTS idx_tickets_priority_status
    ON oti.tickets (priority_id, status_id);

-- ============================================================
-- Índices para admin.usuarios
-- ============================================================

-- Cubre: búsqueda por email (login)
CREATE INDEX IF NOT EXISTS idx_usuarios_email
    ON admin.usuarios (email);

-- Cubre: listado de usuarios activos/inactivos
CREATE INDEX IF NOT EXISTS idx_usuarios_activo
    ON admin.usuarios (activo);

-- Cubre: usuarios admin (isAdmin check)
CREATE INDEX IF NOT EXISTS idx_usuarios_es_admin
    ON admin.usuarios (es_admin);

-- ============================================================
-- Índices para oti.user_profiles
-- ============================================================

-- Cubre: conteo de usuarios por ubicación (stats dashboard)
CREATE INDEX IF NOT EXISTS idx_user_profiles_location
    ON oti.user_profiles (location_id);

-- Cubre: búsqueda por DNI
CREATE INDEX IF NOT EXISTS idx_user_profiles_dni
    ON oti.user_profiles (dni);

-- ============================================================
-- Índices para oti.equipment
-- ============================================================

-- Cubre: filtro por estado + is_deleted (listado equipment)
CREATE INDEX IF NOT EXISTS idx_equipment_status_active
    ON oti.equipment (status, is_deleted)
    WHERE is_deleted = false;

-- Cubre: equipo asignado a usuario
CREATE INDEX IF NOT EXISTS idx_equipment_assigned_user
    ON oti.equipment (assigned_user_id)
    WHERE assigned_user_id IS NOT NULL AND is_deleted = false;

-- Cubre: equipos disponibles por ubicación
CREATE INDEX IF NOT EXISTS idx_equipment_location_available
    ON oti.equipment (location_id)
    WHERE assigned_user_id IS NULL AND is_deleted = false AND status = 'active';

-- ============================================================
-- Índices para oti.notifications
-- ============================================================

-- Cubre: notificaciones no leídas por usuario
CREATE INDEX IF NOT EXISTS idx_notifications_user_unread
    ON oti.notifications (user_id, is_read)
    WHERE is_read = false;

-- ============================================================
-- Índices para oti.ticket_comments
-- ============================================================

-- Cubre: comentarios por ticket
CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket
    ON oti.ticket_comments (ticket_id, created_at ASC);

-- ============================================================
-- Índices para oti.locations
-- ============================================================

-- Cubre: ubicaciones activas por tipo (stats)
CREATE INDEX IF NOT EXISTS idx_locations_active_type
    ON oti.locations (type, active)
    WHERE active = true;

-- ============================================================
-- Índices RBAC - admin.permisos, admin.roles, admin.usuario_rol
-- ============================================================

-- Cubre: búsqueda de permiso por código
CREATE INDEX IF NOT EXISTS idx_permisos_codigo
    ON admin.permisos (codigo);

-- Cubr: permisos por rol (rol_permiso join)
CREATE INDEX IF NOT EXISTS idx_rol_permiso_rol_permiso
    ON admin.rol_permiso (rol_id, permiso_id);

-- Cubre: rol_permiso expiración
CREATE INDEX IF NOT EXISTS idx_rol_permiso_expira
    ON admin.rol_permiso (expira_en)
    WHERE expira_en IS NOT NULL;

-- Cubre: usuario_rol por usuario y sistema
CREATE INDEX IF NOT EXISTS idx_usuario_rol_user_sistema
    ON admin.usuario_rol (usuario_id, sistema_id);

-- Cubre: usuario_rol por rol
CREATE INDEX IF NOT EXISTS idx_usuario_rol_rol
    ON admin.usuario_rol (rol_id);

-- Cubre: permisos especiales por usuario
CREATE INDEX IF NOT EXISTS idx_usuario_permiso_especial_user
    ON admin.usuario_permiso_especial (usuario_id, permiso_id);

COMMIT;
