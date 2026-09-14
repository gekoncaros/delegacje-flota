<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Support/Env.php';
require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__) . '/src/Contracts/DelegationRepository.php';
require_once dirname(__DIR__) . '/src/Contracts/VehicleRepository.php';
require_once dirname(__DIR__) . '/src/Infrastructure/PdoDelegationRepository.php';
require_once dirname(__DIR__) . '/src/Infrastructure/PdoVehicleRepository.php';

use Delegacje\Infrastructure\Database;
use Delegacje\Infrastructure\PdoDelegationRepository;
use Delegacje\Infrastructure\PdoVehicleRepository;
use Delegacje\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

$appConfig = require dirname(__DIR__) . '/config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['demo_delegations'])) {
    $_SESSION['demo_delegations'] = [];
}

if (!isset($_SESSION['demo_expenses'])) {
    $_SESSION['demo_expenses'] = [];
}

if (!isset($_SESSION['demo_vehicles'])) {
    $_SESSION['demo_vehicles'] = [
        [
            'id' => 1,
            'make' => 'Ford',
            'model' => 'Focus',
            'registration_number' => 'DEMO 001',
            'mileage_km' => 125320,
            'status' => 'available',
        ],
        [
            'id' => 2,
            'make' => 'Skoda',
            'model' => 'Octavia',
            'registration_number' => 'DEMO 002',
            'mileage_km' => 88420,
            'status' => 'service',
        ],
    ];
}

function app_config(): array
{
    global $appConfig;
    return $appConfig;
}

function app_mode(): string
{
    return (string)(app_config()['mode'] ?? 'demo');
}

function production_repositories(): array
{
    static $repositories = null;

    if (is_array($repositories)) {
        return $repositories;
    }

    $config = app_config();

    if (($config['mode'] ?? 'demo') !== 'production') {
        throw new RuntimeException('Repozytoria produkcyjne są dostępne tylko w APP_MODE=production.');
    }

    $pdo = (new Database($config['db']))->connection();

    $repositories = [
        'delegations' => new PdoDelegationRepository($pdo, $config['mapping']),
        'vehicles' => new PdoVehicleRepository($pdo, $config['mapping']),
    ];

    return $repositories;
}

function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Sesja formularza wygasła. Wróć do aplikacji i spróbuj ponownie.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function demo_delegation_by_id(int $id): ?array
{
    foreach ($_SESSION['demo_delegations'] as $delegation) {
        if ((int)$delegation['id'] === $id) {
            return $delegation;
        }
    }

    return null;
}

function replace_demo_delegation(array $updated): void
{
    foreach ($_SESSION['demo_delegations'] as $index => $delegation) {
        if ((int)$delegation['id'] === (int)$updated['id']) {
            $_SESSION['demo_delegations'][$index] = $updated;
            return;
        }
    }
}

function current_demo_delegation(): ?array
{
    foreach (array_reverse($_SESSION['demo_delegations']) as $delegation) {
        if (($delegation['trip_status'] ?? '') === 'started') {
            return $delegation;
        }
    }

    return null;
}
