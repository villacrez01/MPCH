<?php
/**
 * Agrega columna `permissions` a la tabla oti.user_profiles
 * si no existe (la API de permisos ya está lista en usuarios.php y functions.php).
 */
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;
$pdo = Database::connect();

$pdo->exec("ALTER TABLE oti.user_profiles ADD COLUMN IF NOT EXISTS permissions JSONB DEFAULT '[]'::jsonb");
echo "OK: columna permissions agregada o ya existía.\n";

// Mostrar resultado
$cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = 'oti' AND table_name = 'user_profiles' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
echo "\nColumnas de oti.user_profiles:\n";
foreach ($cols as $c) { echo "  - " . $c['column_name'] . " (" . $c['data_type'] . ")\n"; }
