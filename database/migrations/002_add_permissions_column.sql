-- Agrega columna permissions a oti.user_profiles si no existe
-- La columna guarda los permisos de cada usuario como JSON array
ALTER TABLE oti.user_profiles ADD COLUMN IF NOT EXISTS permissions JSONB DEFAULT '[]'::jsonb;

-- Verificar columnas de la tabla
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_schema = 'oti' AND table_name = 'user_profiles'
ORDER BY ordinal_position;
