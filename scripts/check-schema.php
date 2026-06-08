<?php
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;
$pdo = Database::connect();

echo "=== oti.equipment columns ===\n";
$cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'equipment' AND table_schema = 'oti' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
echo implode(', ', $cols) . "\n";

echo "\n=== oti.user_profiles columns ===\n";
$cols2 = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'user_profiles' AND table_schema = 'oti' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
echo implode(', ', $cols2) . "\n";

echo "\n=== oti.tickets columns ===\n";
$cols3 = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tickets' AND table_schema = 'oti' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
echo implode(', ', $cols3) . "\n";
