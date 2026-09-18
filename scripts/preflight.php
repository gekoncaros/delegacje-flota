<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Support/Env.php';
require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';

use Delegacje\Infrastructure\Database;
use Delegacje\Support\Env;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function add_check(array &$checks, string $label, bool $passed, ?string $detail = null): void
{
    $checks[] = [$label, $passed, $detail];
}

$root = dirname(__DIR__);
Env::load($root . '/.env');
$config = require $root . '/config/app.php';
$environment = (string) ($config['env'] ?? 'production');
$databaseName = (string) ($config['db']['name'] ?? '');

$checks = [];
add_check($checks, 'PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='));
foreach (['pdo', 'pdo_mysql', 'json', 'fileinfo', 'mbstring'] as $ext) {
    add_check($checks, "Extension: {$ext}", extension_loaded($ext));
}

add_check($checks, 'config/app.php exists', is_file($root . '/config/app.php'));
add_check($checks, 'public directory exists', is_dir($root . '/public'));
add_check($checks, 'private storage directory writable', is_dir($root . '/storage') && is_writable($root . '/storage'));
add_check($checks, 'storage is outside document root', !str_starts_with(realpath($root . '/storage') ?: '', realpath($root . '/public') ?: ''));

if ($environment === 'test') {
    add_check(
        $checks,
        'test database is visibly isolated',
        preg_match('/(^|_)test($|_)/i', $databaseName) === 1,
        $databaseName === '' ? 'DB_NAME is empty' : $databaseName
    );
} else {
    add_check($checks, 'APP_MODE=production', ($config['mode'] ?? '') === 'production');
    add_check($checks, 'APP_URL uses HTTPS', str_starts_with((string) ($config['url'] ?? ''), 'https://'));
    add_check(
        $checks,
        'secure session cookie enabled',
        filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN) === true
    );
}

$requiredTables = [
    'activity_log',
    'app_delegations',
    'app_delegation_history',
    'app_expenses',
    'auth_invitations',
    'auth_login_audit',
    'auth_users',
    'expense_attachments',
    'user_role_assignments',
    'vehicle_incidents',
    'vehicle_reservations',
    'vehicles',
];

try {
    $pdo = (new Database($config['db']))->connection();
    add_check($checks, 'database connection', true);
    $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
    $stmt = $pdo->prepare(
        "SELECT table_name FROM information_schema.tables
         WHERE table_schema = ? AND table_name IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$databaseName], $requiredTables));
    $present = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $missing = array_values(array_diff($requiredTables, $present));
    add_check(
        $checks,
        'required database schema',
        $missing === [],
        $missing === [] ? null : 'missing: ' . implode(', ', $missing)
    );
} catch (Throwable $exception) {
    add_check($checks, 'database connection', false, $exception->getMessage());
}

$allOk = true;
foreach ($checks as [$label, $ok, $detail]) {
    $allOk = $allOk && $ok;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label;
    if (!$ok && $detail !== null) {
        echo ' (' . $detail . ')';
    }
    echo PHP_EOL;
}

echo PHP_EOL;
echo $allOk
    ? "Preflight passed. Environment is ready for release validation." . PHP_EOL
    : "Preflight failed. Fix the items marked FAIL before production deployment." . PHP_EOL;

exit($allOk ? 0 : 1);
