<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Support/Env.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__, 2) . '/src/Security/AuthService.php';
require_once dirname(__DIR__, 2) . '/src/Security/Authorization.php';

use Delegacje\Infrastructure\Database;
use Delegacje\Security\AuthService;
use Delegacje\Support\Env;

Env::load(dirname(__DIR__, 2) . '/.env');
$config = require dirname(__DIR__, 2) . '/config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('delegacje_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');

$pdo = (new Database($config['db']))->connection();
$auth = new AuthService($pdo);

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_error('Nieprawidłowy JSON.', 400);
    }
    return $data;
}

function api_response(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $message, int $status = 400): never
{
    api_response(['ok' => false, 'error' => $message], $status);
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        api_error('Niedozwolona metoda.', 405);
    }
}

function require_csrf(): void
{
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        api_error('Nieprawidłowy token CSRF.', 419);
    }
}

function current_api_user(AuthService $auth): array
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId < 1) {
        api_error('Brak aktywnej sesji.', 401);
    }
    $user = $auth->userById($userId);
    if (!$user) {
        $_SESSION = [];
        api_error('Sesja użytkownika wygasła.', 401);
    }
    return $user;
}
