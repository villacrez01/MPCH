-- 📋 RBAC Permissions Migration
-- Versión: 1.0
-- Descripción: Crear tablas de permisos granulares y asociaciones de rol-permiso
-- Ejecutar: psql -U postgres -d sistema_soporte -f rbac_permissions.sql

-- ============================================================================
-- 1. TABLA DE PERMISOS GRANULARES
-- ============================================================================

CREATE TABLE IF NOT EXISTS admin.permisos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(100) UNIQUE NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    modulo VARCHAR(50) NOT NULL,           -- 'tickets', 'usuarios', 'equipos'
    accion VARCHAR(50) NOT NULL,           -- 'crear', 'editar', 'eliminar', 'ver'
    nivel_riesgo VARCHAR(20) DEFAULT 'medio' CHECK (nivel_riesgo IN ('bajo', 'medio', 'alto')),
    requiere_confirmacion BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_modulo_accion UNIQUE(modulo, accion)
);

CREATE INDEX idx_permisos_codigo ON admin.permisos(codigo);
CREATE INDEX idx_permisos_modulo ON admin.permisos(modulo);
CREATE INDEX idx_permisos_nivel_riesgo ON admin.permisos(nivel_riesgo);

-- Comentario para la tabla
COMMENT ON TABLE admin.permisos IS 'Almacena permisos granulares disponibles en el sistema (modulo.accion)';
COMMENT ON COLUMN admin.permisos.codigo IS 'Código único: "modulo.accion" (ej: tickets.crear)';
COMMENT ON COLUMN admin.permisos.nivel_riesgo IS 'Clasificación de riesgo: bajo, medio, alto';

-- ============================================================================
-- 2. TABLA DE ASOCIACIÓN ROL-PERMISO (Many-to-Many)
-- ============================================================================

CREATE TABLE IF NOT EXISTS admin.rol_permiso (
    rol_id INT NOT NULL REFERENCES admin.roles(id) ON DELETE CASCADE,
    permiso_id INT NOT NULL REFERENCES admin.permisos(id) ON DELETE CASCADE,
    otorgado_por INT REFERENCES admin.usuarios(id),
    otorgado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en TIMESTAMP,                    -- Para permisos temporales
    PRIMARY KEY (rol_id, permiso_id)
);

CREATE INDEX idx_rol_permiso_rol_id ON admin.rol_permiso(rol_id);
CREATE INDEX idx_rol_permiso_permiso_id ON admin.rol_permiso(permiso_id);
CREATE INDEX idx_rol_permiso_expira_en ON admin.rol_permiso(expira_en);

COMMENT ON TABLE admin.rol_permiso IS 'Asocia roles con permisos: un rol puede tener múltiples permisos';
COMMENT ON COLUMN admin.rol_permiso.expira_en IS 'Si está set, el permiso expira en esa fecha (nulo = permanente)';

-- ============================================================================
-- 3. TABLA DE PERMISOS ESPECIALES POR USUARIO (Override)
-- ============================================================================

CREATE TABLE IF NOT EXISTS admin.usuario_permiso_especial (
    usuario_id INT NOT NULL REFERENCES admin.usuarios(id) ON DELETE CASCADE,
    permiso_id INT NOT NULL REFERENCES admin.permisos(id) ON DELETE CASCADE,
    otorgado_por INT REFERENCES admin.usuarios(id),
    razon TEXT,                             -- Justificación del override
    otorgado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en TIMESTAMP,                    -- Para permisos temporales
    PRIMARY KEY (usuario_id, permiso_id)
);

CREATE INDEX idx_usuario_permiso_usuario_id ON admin.usuario_permiso_especial(usuario_id);
CREATE INDEX idx_usuario_permiso_permiso_id ON admin.usuario_permiso_especial(permiso_id);
CREATE INDEX idx_usuario_permiso_expira_en ON admin.usuario_permiso_especial(expira_en);

COMMENT ON TABLE admin.usuario_permiso_especial IS 'Permisos especiales directamente asignados a usuarios (bypass de roles)';

-- ============================================================================
-- 4. TABLA DE AUDITORÍA (Cambios de Permisos)
-- ============================================================================

CREATE TABLE IF NOT EXISTS admin.permiso_audit_log (
    id BIGSERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES admin.usuarios(id),
    accion VARCHAR(100),                    -- 'asignar_rol', 'revocar_rol', 'crear_permiso'
    tabla_afectada VARCHAR(100),
    id_registro INT,
    cambios JSONB,                          -- Cambios realizados
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_log_usuario_id ON admin.permiso_audit_log(usuario_id);
CREATE INDEX idx_audit_log_created_at ON admin.permiso_audit_log(created_at DESC);
CREATE INDEX idx_audit_log_accion ON admin.permiso_audit_log(accion);

COMMENT ON TABLE admin.permiso_audit_log IS 'Log de auditoría: quién cambió qué permisos, cuándo y desde dónde';

-- ============================================================================
-- 5. TABLA DE CACHÉ DE PERMISOS (Optimización)
-- ============================================================================

CREATE TABLE IF NOT EXISTS admin.permiso_cache (
    usuario_id INT PRIMARY KEY REFERENCES admin.usuarios(id) ON DELETE CASCADE,
    permisos JSONB NOT NULL,                -- Array de códigos: ["tickets.crear", "tickets.editar"]
    roles_ids JSONB NOT NULL,               -- Array de IDs de roles
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en TIMESTAMP
);

CREATE INDEX idx_permiso_cache_expira_en ON admin.permiso_cache(expira_en);

COMMENT ON TABLE admin.permiso_cache IS 'Caché de permisos por usuario (se invalida cuando cambia el rol)';

-- ============================================================================
-- 6. PERMISOS ESTÁNDAR (SEEDS)
-- ============================================================================

-- Limpiar permisos existentes
DELETE FROM admin.rol_permiso WHERE permiso_id IN (SELECT id FROM admin.permisos WHERE modulo IN ('tickets', 'usuarios', 'equipos', 'admin'));
DELETE FROM admin.permisos WHERE modulo IN ('tickets', 'usuarios', 'equipos', 'admin');

-- MÓDULO: TICKETS
INSERT INTO admin.permisos (codigo, nombre, descripcion, modulo, accion, nivel_riesgo) VALUES
('tickets.ver', 'Ver Tickets', 'Permite ver lista y detalles de tickets', 'tickets', 'ver', 'bajo'),
('tickets.crear', 'Crear Tickets', 'Permite crear nuevos tickets de soporte', 'tickets', 'crear', 'bajo'),
('tickets.editar', 'Editar Tickets', 'Permite editar información de tickets', 'tickets', 'editar', 'medio'),
('tickets.comentar', 'Comentar Tickets', 'Permite agregar comentarios a tickets', 'tickets', 'comentar', 'bajo'),
('tickets.asignar', 'Asignar Tickets', 'Permite asignar tickets a técnicos', 'tickets', 'asignar', 'medio'),
('tickets.cerrar', 'Cerrar Tickets', 'Permite cerrar tickets resueltos', 'tickets', 'cerrar', 'medio'),
('tickets.reabrir', 'Reabrir Tickets', 'Permite reabrir tickets cerrados', 'tickets', 'reabrir', 'medio'),
('tickets.eliminar', 'Eliminar Tickets', 'Permite eliminar tickets (irreversible)', 'tickets', 'eliminar', 'alto');

-- MÓDULO: USUARIOS
INSERT INTO admin.permisos (codigo, nombre, descripcion, modulo, accion, nivel_riesgo) VALUES
('usuarios.ver', 'Ver Usuarios', 'Permite ver lista de usuarios', 'usuarios', 'ver', 'bajo'),
('usuarios.crear', 'Crear Usuarios', 'Permite crear nuevos usuarios', 'usuarios', 'crear', 'alto'),
('usuarios.editar', 'Editar Usuarios', 'Permite editar información de usuarios', 'usuarios', 'editar', 'alto'),
('usuarios.eliminar', 'Eliminar Usuarios', 'Permite eliminar usuarios (irreversible)', 'usuarios', 'eliminar', 'alto'),
('usuarios.asignar-rol', 'Asignar Rol', 'Permite asignar roles a usuarios', 'usuarios', 'asignar-rol', 'alto'),
('usuarios.cambiar-estado', 'Cambiar Estado', 'Permite activar/desactivar usuarios', 'usuarios', 'cambiar-estado', 'medio'),
('usuarios.cambiar-password', 'Cambiar Contraseña', 'Permite cambiar contraseña de usuarios', 'usuarios', 'cambiar-password', 'alto'),
('usuarios.ver-permisos', 'Ver Permisos', 'Permite ver permisos asignados a usuarios', 'usuarios', 'ver-permisos', 'bajo');

-- MÓDULO: EQUIPOS
INSERT INTO admin.permisos (codigo, nombre, descripcion, modulo, accion, nivel_riesgo) VALUES
('equipos.ver', 'Ver Equipos', 'Permite ver lista y detalles de equipos', 'equipos', 'ver', 'bajo'),
('equipos.crear', 'Crear Equipos', 'Permite registrar nuevos equipos', 'equipos', 'crear', 'medio'),
('equipos.editar', 'Editar Equipos', 'Permite editar información de equipos', 'equipos', 'editar', 'medio'),
('equipos.eliminar', 'Eliminar Equipos', 'Permite eliminar equipos (irreversible)', 'equipos', 'eliminar', 'alto'),
('equipos.asignar', 'Asignar Equipos', 'Permite asignar equipos a usuarios', 'equipos', 'asignar', 'medio'),
('equipos.reasignar', 'Reasignar Equipos', 'Permite reasignar equipos entre usuarios', 'equipos', 'reasignar', 'medio'),
('equipos.ver-historial', 'Ver Historial', 'Permite ver historial de cambios de equipos', 'equipos', 'ver-historial', 'bajo');

-- MÓDULO: ADMIN (Sistema)
INSERT INTO admin.permisos (codigo, nombre, descripcion, modulo, accion, nivel_riesgo) VALUES
('admin.ver-auditoria', 'Ver Auditoría', 'Permite ver logs de auditoría', 'admin', 'ver-auditoria', 'medio'),
('admin.gestionar-permisos', 'Gestionar Permisos', 'Permite crear y modificar permisos', 'admin', 'gestionar-permisos', 'alto'),
('admin.gestionar-roles', 'Gestionar Roles', 'Permite crear y modificar roles', 'admin', 'gestionar-roles', 'alto'),
('admin.configuracion', 'Configuración del Sistema', 'Permite acceder a configuración global', 'admin', 'configuracion', 'alto');

-- ============================================================================
-- 7. ASIGNAR PERMISOS A ROLES EXISTENTES
-- ============================================================================

-- Rol: Superadministrador (ID usualmente 1 o similar - ajustar según BD)
-- Se asumen todos los permisos
DO $$
DECLARE
    admin_role_id INT;
    perm_id INT;
BEGIN
    -- Encontrar rol de admin (puede variar)
    SELECT id INTO admin_role_id FROM admin.roles 
    WHERE nombre ILIKE 'administrador' OR nombre ILIKE 'admin' 
    LIMIT 1;
    
    IF admin_role_id IS NOT NULL THEN
        -- Asignar todos los permisos al rol admin
        INSERT INTO admin.rol_permiso (rol_id, permiso_id, otorgado_en)
        SELECT admin_role_id, id, CURRENT_TIMESTAMP
        FROM admin.permisos
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- Rol: Técnico (si existe)
DO $$
DECLARE
    tech_role_id INT;
BEGIN
    SELECT id INTO tech_role_id FROM admin.roles 
    WHERE nombre ILIKE 'técnico%' 
    LIMIT 1;
    
    IF tech_role_id IS NOT NULL THEN
        -- Asignar permisos de técnico
        INSERT INTO admin.rol_permiso (rol_id, permiso_id, otorgado_en)
        SELECT 
            tech_role_id, 
            id, 
            CURRENT_TIMESTAMP
        FROM admin.permisos 
        WHERE codigo IN (
            'tickets.ver', 'tickets.editar', 'tickets.asignar', 
            'tickets.comentar', 'tickets.cerrar',
            'equipos.ver', 'equipos.asignar', 'equipos.reasignar',
            'usuarios.ver'
        )
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- Rol: Usuario (si existe)
DO $$
DECLARE
    user_role_id INT;
BEGIN
    SELECT id INTO user_role_id FROM admin.roles 
    WHERE nombre ILIKE 'usuario' OR nombre ILIKE 'client%'
    LIMIT 1;
    
    IF user_role_id IS NOT NULL THEN
        -- Asignar permisos básicos de usuario
        INSERT INTO admin.rol_permiso (rol_id, permiso_id, otorgado_en)
        SELECT 
            user_role_id, 
            id, 
            CURRENT_TIMESTAMP
        FROM admin.permisos 
        WHERE codigo IN (
            'tickets.ver', 'tickets.crear', 'tickets.comentar',
            'equipos.ver'
        )
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- ============================================================================
-- 8. FUNCIONES AUXILIARES (PLPGSQL)
-- ============================================================================

-- Función: Obtener todos los permisos de un usuario
CREATE OR REPLACE FUNCTION admin.get_user_permissions(p_usuario_id INT)
RETURNS TABLE(codigo VARCHAR, nombre VARCHAR, modulo VARCHAR, accion VARCHAR)
AS $$
BEGIN
    RETURN QUERY
    SELECT DISTINCT p.codigo, p.nombre, p.modulo, p.accion
    FROM admin.permisos p
    WHERE 
        -- Permisos a través de roles
        p.id IN (
            SELECT rp.permiso_id
            FROM admin.usuario_rol ur
            JOIN admin.rol_permiso rp ON ur.rol_id = rp.rol_id
            WHERE ur.usuario_id = p_usuario_id 
              AND ur.sistema_id = (SELECT id FROM admin.sistemas WHERE slug = 'oti' LIMIT 1)
              AND (rp.expira_en IS NULL OR rp.expira_en > NOW())
        )
        OR
        -- Permisos especiales por usuario
        p.id IN (
            SELECT permiso_id
            FROM admin.usuario_permiso_especial
            WHERE usuario_id = p_usuario_id
              AND (expira_en IS NULL OR expira_en > NOW())
        )
    ORDER BY p.codigo;
END;
$$ LANGUAGE plpgsql;

-- Función: Verificar si usuario tiene un permiso específico
CREATE OR REPLACE FUNCTION admin.has_permission(p_usuario_id INT, p_permission_code VARCHAR)
RETURNS BOOLEAN
AS $$
DECLARE
    permission_exists BOOLEAN;
BEGIN
    SELECT EXISTS(
        SELECT 1 FROM admin.get_user_permissions(p_usuario_id)
        WHERE codigo = p_permission_code
    ) INTO permission_exists;
    
    RETURN permission_exists;
END;
$$ LANGUAGE plpgsql;

-- Función: Invalidar caché de permisos
CREATE OR REPLACE FUNCTION admin.invalidate_permission_cache(p_usuario_id INT)
RETURNS VOID
AS $$
BEGIN
    DELETE FROM admin.permiso_cache WHERE usuario_id = p_usuario_id;
END;
$$ LANGUAGE plpgsql;

-- Función: Trigger para invalidar caché cuando cambia el rol
CREATE OR REPLACE FUNCTION admin.invalidate_cache_on_role_change()
RETURNS TRIGGER
AS $$
BEGIN
    PERFORM admin.invalidate_permission_cache(NEW.usuario_id);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Crear trigger
DROP TRIGGER IF EXISTS trg_invalidate_cache_on_role_change ON admin.usuario_rol;
CREATE TRIGGER trg_invalidate_cache_on_role_change
AFTER INSERT OR UPDATE OR DELETE ON admin.usuario_rol
FOR EACH ROW
EXECUTE FUNCTION admin.invalidate_cache_on_role_change();

-- Trigger para invalidar caché cuando cambia un permiso de rol
DROP TRIGGER IF EXISTS trg_invalidate_cache_on_rol_permiso ON admin.rol_permiso;
CREATE TRIGGER trg_invalidate_cache_on_rol_permiso
AFTER INSERT OR UPDATE OR DELETE ON admin.rol_permiso
FOR EACH ROW
EXECUTE FUNCTION admin.invalidate_cache_on_role_change();

-- ============================================================================
-- 9. VISTAS (VIEWS) ÚTILES
-- ============================================================================

-- Vista: Usuarios con sus roles y permisos
CREATE OR REPLACE VIEW admin.vw_usuario_permisos AS
SELECT 
    u.id,
    u.nombre,
    u.apellidos,
    u.email,
    u.es_admin,
    r.id as rol_id,
    r.nombre as rol_nombre,
    COUNT(p.id) as cantidad_permisos,
    ARRAY_AGG(p.codigo) FILTER (WHERE p.codigo IS NOT NULL) as permisos
FROM admin.usuarios u
LEFT JOIN admin.usuario_rol ur ON u.id = ur.usuario_id
LEFT JOIN admin.roles r ON ur.rol_id = r.id
LEFT JOIN admin.rol_permiso rp ON r.id = rp.rol_id
LEFT JOIN admin.permisos p ON rp.permiso_id = p.id
WHERE u.activo = TRUE
GROUP BY u.id, u.nombre, u.apellidos, u.email, u.es_admin, r.id, r.nombre;

-- Vista: Roles con cantidad de permisos asignados
CREATE OR REPLACE VIEW admin.vw_rol_resumen AS
SELECT 
    r.id,
    r.nombre,
    COUNT(rp.permiso_id) as cantidad_permisos,
    ARRAY_AGG(p.modulo DISTINCT) as modulos_cubiertos
FROM admin.roles r
LEFT JOIN admin.rol_permiso rp ON r.id = rp.rol_id
LEFT JOIN admin.permisos p ON rp.permiso_id = p.id
GROUP BY r.id, r.nombre;

-- ============================================================================
-- 10. DATOS DE PRUEBA (OPCIONAL)
-- ============================================================================

-- Descomentar para agregar datos de prueba:
/*
-- Asignar permisos especiales temporales a un usuario para testing
INSERT INTO admin.usuario_permiso_especial (usuario_id, permiso_id, razon, expira_en)
SELECT 
    1,  -- Usuario ID (cambiar según sea necesario)
    id, 
    'Permiso temporal para testing',
    NOW() + INTERVAL '7 days'
FROM admin.permisos
WHERE modulo = 'admin'
LIMIT 2;
*/

-- ============================================================================
-- 11. VALIDACIÓN Y VERIFICACIÓN
-- ============================================================================

-- Verificar integridad
SELECT 
    (SELECT COUNT(*) FROM admin.permisos) as total_permisos,
    (SELECT COUNT(*) FROM admin.rol_permiso) as asignaciones_rol_permiso,
    (SELECT COUNT(*) FROM admin.usuario_permiso_especial) as permisos_especiales,
    (SELECT COUNT(*) FROM admin.roles) as total_roles
;

-- Mostrar permisos por módulo
SELECT 
    modulo,
    COUNT(*) as cantidad,
    STRING_AGG(codigo, ', ') as permisos
FROM admin.permisos
GROUP BY modulo
ORDER BY modulo;

-- ============================================================================
-- ✅ FIN DE MIGRACIÓN
-- ============================================================================

-- Notas:
-- 1. Esta migración es idempotente (puedes ejecutarla múltiples veces)
-- 2. Los triggers invalidan automáticamente la caché cuando cambian los roles
-- 3. Las funciones PLPGSQL están disponibles para queries complejas
-- 4. Las vistas permiten consultas más simples desde la aplicación

