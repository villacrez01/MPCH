<?php
define('BASE_URL','http://localhost/OTI/');
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;
$pdo = Database::connect();
$cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'user_profiles' AND table_schema = 'oti' ORDER BY ordinal_position")->fetchAll();
foreach($cols as $c) {
    echo $c['column_name'] . "\n";
}
