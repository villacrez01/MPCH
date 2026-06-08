-- =============================================
-- Migration: Performance Indexes
-- Sistema OTI - Optimizacion de rendimiento
-- Fecha: 2026-05-21
-- Ejecutar con: psql -U postgres -d sistema_soporte -f 001_performance_indexes.sql
-- =============================================

-- ── 1. Version marker (para reportar ejecucion pendiente o ya ejecutada) ──
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_tables
        WHERE schemaname = 'public' AND tablename = '_migration_version'
    ) THEN
        RAISE NOTICE 'Migracion 001 ya fue ejecutada anteriormente (tabla _migration_version encontrada).';
    ELSE
        DROP TABLE IF EXISTS public._migration_version;
        CREATE TABLE public._migration_version (
            id         text      PRIMARY KEY,
            applied_at timestamp NOT NULL DEFAULT now()
        );
        INSERT INTO public._migration_version (id) VALUES ('001_performance_indexes');
        RAISE NOTICE 'Migracion 001 registrada exitosamente.';
    END IF;
END
$$ LANGUAGE plpgsql;

-- ── 2. pg_trgm extension ──
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- ── 3. Indices compuestos para oti.tickets ──
--    Usamos transaccion comun porque no son tablas grandes,
--    los indices se construyen bien sin CONCURRENTLY.
--    CONCURRENTLY se reserva para entornos productivos con tablas
--    de millones de filas donde no se puede bloquear escrituras.
BEGIN;

CREATE INDEX IF NOT EXISTS idx_tickets_status_created
    ON oti.tickets(status_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_tickets_user_created
    ON oti.tickets(user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_tickets_assigned_status
    ON oti.tickets(assigned_admin_id, status_id);

CREATE INDEX IF NOT EXISTS idx_tickets_priority
    ON oti.tickets(priority_id);

CREATE INDEX IF NOT EXISTS idx_tickets_service
    ON oti.tickets(service_id);

CREATE INDEX IF NOT EXISTS idx_tickets_location
    ON oti.tickets(location_id);

CREATE INDEX IF NOT EXISTS idx_equipment_location_status
    ON oti.equipment(location_id, status);

CREATE INDEX IF NOT EXISTS idx_locations_parent_type
    ON oti.locations(parent_id, type);

CREATE INDEX IF NOT EXISTS idx_ticket_activity_ticket_created
    ON oti.ticket_activity(ticket_id, created_at DESC);

-- Indices GIN para busquedas full-text
CREATE INDEX IF NOT EXISTS idx_users_search_trgm
    ON admin.usuarios USING gin (
        coalesce(nombre, '') gin_trgm_ops,
        coalesce(email, '') gin_trgm_ops
    );

CREATE INDEX IF NOT EXISTS idx_tickets_search_trgm
    ON oti.tickets USING gin (
        coalesce(title, '') gin_trgm_ops,
        coalesce(description, '') gin_trgm_ops
    );

COMMIT;

-- ── 4. Actualizar estadisticas del planner ──
--    Necesario despues de crear indices masivos para que PostgreSQL
--    elija los planes de ejecucion correctos en queries con JOIN + GROUP BY
ANALYZE oti.tickets;
ANALYZE oti.equipment;
ANALYZE oti.locations;
ANALYZE oti.ticket_activity;
ANALYZE admin.usuarios;

-- =============================================
-- Rollback (ejecutar en pgAdmin o psql):
--
-- DROP TABLE IF EXISTS public._migration_version;
-- DROP INDEX IF EXISTS oti.idx_tickets_status_created;
-- DROP INDEX IF EXISTS oti.idx_tickets_user_created;
-- DROP INDEX IF EXISTS oti.idx_tickets_assigned_status;
-- DROP INDEX IF EXISTS oti.idx_tickets_priority;
-- DROP INDEX IF EXISTS oti.idx_tickets_service;
-- DROP INDEX IF EXISTS oti.idx_tickets_location;
-- DROP INDEX IF EXISTS oti.idx_equipment_location_status;
-- DROP INDEX IF EXISTS oti.idx_locations_parent_type;
-- DROP INDEX IF EXISTS oti.idx_ticket_activity_ticket_created;
-- DROP INDEX IF EXISTS admin.idx_users_search_trgm;
-- DROP INDEX IF EXISTS oti.idx_tickets_search_trgm;
-- DROP EXTENSION IF EXISTS pg_trgm;
-- =============================================
