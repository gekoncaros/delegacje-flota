<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Support/Env.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/ActivityLogger.php';
require_once dirname(__DIR__, 2) . '/src/Security/AuthService.php';
require_once dirname(__DIR__, 2) . '/src/Security/Authorization.php';
require_once dirname(__DIR__, 2) . '/src/Security/UserInvitationService.php';
require_once dirname(__DIR__, 2) . '/src/Security/SessionSecurity.php';
require_once dirname(__DIR__, 2) . '/src/Security/SecurityHeaders.php';

use Delegacje\Infrastructure\Database;
use Delegacje\Infrastructure\ActivityLogger;
use Delegacje\Security\AccessDeniedException;
use Delegacje\Security\AuthService;
use Delegacje\Security\UserInvitationService;
use Delegacje\Security\SessionSecurity;
use Delegacje\Security\SecurityHeaders;
use Delegacje\Support\Env;

Env::load(dirname(__DIR__, 2) . '/.env');
$config = require dirname(__DIR__, 2) . '/config/app.php';

SessionSecurity::start($config);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
SecurityHeaders::send();

set_exception_handler(static function (\Throwable $exception): never {
    if ($exception instanceof AccessDeniedException) {
        api_error($exception->getMessage(), 403);
    }
    error_log(sprintf('Unhandled API error [%s]: %s', get_class($exception), $exception->getMessage()));
    api_error('Wewnętrzny błąd serwera.', 500);
});

$pdo = (new Database($config['db']))->connection();
$auth = new AuthService($pdo);
$invitations = new UserInvitationService($pdo);

function json_input(): array
{
    $maxBytes = 1024 * 1024;
    $declaredLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
    if ($declaredLength !== false && $declaredLength !== null && $declaredLength > $maxBytes) {
        api_error('Żądanie jest zbyt duże.', 413);
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    if (strlen($raw) > $maxBytes) {
        api_error('Żądanie jest zbyt duże.', 413);
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

function api_exception(\Throwable $exception, int $validationStatus = 422): never
{
    if ($exception instanceof \PDOException) {
        error_log('Database API error: ' . $exception->getMessage());
        api_error('Nie udało się wykonać operacji na danych.', 500);
    }
    api_error($exception->getMessage(), $validationStatus);
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
    $credentialVersion = (string) ($user['_credential_version'] ?? '');
    if (!SessionSecurity::credentialVersionIsCurrent($credentialVersion)) {
        $_SESSION = [];
        session_regenerate_id(true);
        api_error('Sesja wygasła po zmianie hasła. Zaloguj się ponownie.', 401);
    }
    unset($user['_credential_version']);
    return $user;
}

/** Keep a security-relevant trace for all mutable API operations. */
function api_audit(PDO $pdo, int $userId, string $action, string $objectType, ?int $objectId, array $metadata = []): void
{
    unset($metadata['password'], $metadata['token'], $metadata['csrf_token']);
    try {
        (new ActivityLogger($pdo))->log($userId, $action, $objectType, $objectId, $metadata);
    } catch (\Throwable $exception) {
        // The business operation may already be committed. Avoid reporting a false
        // failure that would encourage a duplicate retry, while keeping a server trace.
        error_log(sprintf('Activity audit write failed for %s: %s', $action, $exception->getMessage()));
    }
}
