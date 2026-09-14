<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
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
