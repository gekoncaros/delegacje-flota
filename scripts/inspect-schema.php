<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/Support/Env.php';
require dirname(__DIR__) . '/src/Infrastructure/Database.php';

use Delegacje\Infrastructure\Database;
use Delegacje\Support\Env;

Env::load(dirname(__DIR__) . '/.env');
$config = require dirname(__DIR__) . '/config/app.php';

$pdo = (new Database($config['db']))->connection();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$result = [];
foreach ($tables as $table) {
    $table = (string)$table;

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        continue;
    }

    $columns = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll();

    $result[$table] = array_map(
        static fn(array $column): array => [
            'field' => $column['Field'],
            'type' => $column['Type'],
            'null' => $column['Null'],
            'key' => $column['Key'],
            'default' => $column['Default'],
            'extra' => $column['Extra'],
        ],
        $columns
    );
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
