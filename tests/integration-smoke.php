<?php
declare(strict_types=1);

use Delegacje\Accounting\ExpenseService;
use Delegacje\Application\DelegationWorkflowService;
use Delegacje\Fleet\VehicleFleetService;
use Delegacje\Fleet\VehicleIncidentService;
use Delegacje\Fleet\VehicleReservationService;
use Delegacje\Infrastructure\ActivityLogger;
use Delegacje\Security\AuditLogService;
use Delegacje\Security\AuthService;
use Delegacje\Security\Authorization;
use Delegacje\Security\SessionSecurity;
use Delegacje\Security\UserAdministrationService;
use Delegacje\Security\UserInvitationService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('APP_ENV') !== 'test') {
    fwrite(STDERR, "Refusing to run: APP_ENV must equal test.\n");
    exit(2);
}
$database = (string) getenv('DB_NAME');
if (!preg_match('/(^|_)test($|_)/i', $database)) {
    fwrite(STDERR, "Refusing to run: DB_NAME must visibly identify a test database.\n");
    exit(2);
}

$root = dirname(__DIR__);
foreach ([
    'src/Application/DelegationWorkflowService.php',
    'src/Fleet/VehicleReservationService.php',
    'src/Fleet/VehicleFleetService.php',
    'src/Fleet/VehicleIncidentService.php',
    'src/Accounting/ExpenseService.php',
    'src/Infrastructure/ActivityLogger.php',
    'src/Security/AuditLogService.php',
    'src/Security/AuthService.php',
    'src/Security/Authorization.php',
    'src/Security/SessionSecurity.php',
    'src/Security/UserAdministrationService.php',
    'src/Security/UserInvitationService.php',
] as $file) {
    require_once $root . '/' . $file;
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: '127.0.0.1',
    (int) (getenv('DB_PORT') ?: 3306),
    $database
);
$pdo = new PDO($dsn, (string) getenv('DB_USER'), (string) getenv('DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => true,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);

foreach (glob($root . '/database/migrations/*.sql') ?: [] as $migration) {
    $sql = file_get_contents($migration);
    if ($sql === false) {
        throw new RuntimeException('Cannot read migration: ' . basename($migration));
    }
    $pdo->exec($sql);
}
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function create_user(PDO $pdo, string $email, string $name, array $roles, ?int $managerId = null): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO auth_users (email, display_name, password_hash, active, manager_user_id)
         VALUES (:email, :name, :password, 1, :manager_id)'
    );
    $stmt->execute([
        'email' => $email,
        'name' => $name,
        'password' => password_hash('Integration-only-password!', PASSWORD_DEFAULT),
        'manager_id' => $managerId,
    ]);
    $id = (int) $pdo->lastInsertId();
    $role = $pdo->prepare('INSERT INTO user_role_assignments (user_id, role_code, active) VALUES (:user_id, :role, 1)');
    foreach ($roles as $code) {
        $role->execute(['user_id' => $id, 'role' => $code]);
    }
    return $id;
}

$managerId = create_user($pdo, 'manager@example.test', 'Test Manager', ['manager']);
$employeeId = create_user($pdo, 'employee@example.test', 'Test Employee', ['employee'], $managerId);
$accountingId = create_user($pdo, 'accounting@example.test', 'Test Accounting', ['accounting']);
$adminId = create_user($pdo, 'admin@example.test', 'Test Admin', ['super_admin']);

$manager = ['id' => $managerId, 'roles' => ['manager'], 'manager_user_id' => null];
$employee = ['id' => $employeeId, 'roles' => ['employee'], 'manager_user_id' => $managerId];
$accounting = ['id' => $accountingId, 'roles' => ['accounting'], 'manager_user_id' => null];
$admin = ['id' => $adminId, 'roles' => ['super_admin'], 'manager_user_id' => null];

$workflow = new DelegationWorkflowService($pdo);
$delegation = $workflow->create($employee, [
    'destination' => 'Gliwice',
    'purpose' => 'Integration smoke test',
    'date_from' => date('Y-m-d'),
    'date_to' => date('Y-m-d', strtotime('+1 day')),
    'transport' => 'company_car',
]);
assert_true($delegation['status'] === 'pending', 'delegation should await manager approval');
$delegation = $workflow->transition((int) $delegation['id'], $manager, 'approve');
$delegation = $workflow->transition((int) $delegation['id'], $employee, 'start', ['odometer_km' => 100]);
$delegation = $workflow->transition((int) $delegation['id'], $employee, 'finish', ['odometer_km' => 125]);
$workflow->transition((int) $delegation['id'], $accounting, 'accounting_book');
$paidDelegation = $workflow->transition((int) $delegation['id'], $accounting, 'accounting_pay');
assert_true($paidDelegation['accounting_status'] === 'paid', 'delegation should complete accounting workflow');

$fleet = new VehicleFleetService($pdo);
$vehiclePayload = [
    'make' => 'Skoda',
    'model' => 'Octavia',
    'registration_number' => 'TEST 001',
    'vin' => 'TMBJG7NE0J0123456',
    'year' => (int) date('Y'),
    'fuel_type' => 'diesel',
    'mileage_km' => 100,
    'status' => 'available',
    'assigned_user_id' => $employeeId,
    'inspection_due' => date('Y-m-d', strtotime('+1 year')),
    'insurance_due' => date('Y-m-d', strtotime('+1 year')),
];
$vehicleId = $fleet->saveVehicle($admin, $vehiclePayload);
assert_true($vehicleId > 0, 'fleet admin should create a vehicle');
assert_true(!array_key_exists('vin', $fleet->listVehicles($employee)[0]), 'employee fleet view must not expose VIN');
assert_true($fleet->listVehicles($admin)[0]['vin'] === 'TMBJG7NE0J0123456', 'fleet admin should see vehicle details');
$reservationDelegation = $workflow->create($employee, [
    'destination' => 'Katowice',
    'purpose' => 'Vehicle reservation integration test',
    'date_from' => date('Y-m-d'),
    'date_to' => date('Y-m-d', strtotime('+1 day')),
    'transport' => 'company_car',
]);
$reservationDelegation = $workflow->transition((int) $reservationDelegation['id'], $manager, 'approve');
$reservations = new VehicleReservationService($pdo);
$reservationId = $reservations->create($employee, [
    'vehicle_id' => $vehicleId,
    'starts_at' => date('Y-m-d\\TH:i', strtotime('+2 hours')),
    'ends_at' => date('Y-m-d\\TH:i', strtotime('+4 hours')),
    'delegation_id' => $reservationDelegation['id'],
    'purpose' => 'Integration smoke test',
]);
assert_true($reservationId > 0, 'vehicle reservation should be created');
$reservations->cancel($employee, $reservationId);

$incidentId = $fleet->createIncident($employee, [
    'vehicle_id' => $vehicleId,
    'category' => 'failure',
    'description' => 'Integration test incident description.',
    'mileage_km' => 125,
    'unsafe_to_drive' => true,
]);
assert_true($incidentId > 0, 'vehicle incident should be created');
assert_true($pdo->query('SELECT status FROM vehicles WHERE id = ' . $vehicleId)->fetchColumn() === 'unsafe', 'unsafe report should lock vehicle');
$availabilityBlocked = false;
try {
    $fleet->saveVehicle($admin, array_merge($vehiclePayload, ['id' => $vehicleId, 'status' => 'available']));
} catch (RuntimeException) {
    $availabilityBlocked = true;
}
assert_true($availabilityBlocked, 'open unsafe incident must block vehicle availability');
(new VehicleIncidentService($pdo))->transition($admin, $incidentId, 'resolve', null, 'Resolved during integration test.');
$fleet->saveVehicle($admin, array_merge($vehiclePayload, ['id' => $vehicleId, 'status' => 'available']));

$expenses = new ExpenseService($pdo);
$expenseId = $expenses->create($employee, [
    'delegation_id' => $delegation['id'],
    'category' => 'parking',
    'amount' => '25.50',
    'currency' => 'PLN',
    'expense_date' => date('Y-m-d'),
    'description' => 'Integration smoke test expense',
]);
$expenses->transition($accounting, $expenseId, 'approve', null);
$expenses->transition($accounting, $expenseId, 'pay', null);
assert_true($pdo->query('SELECT status FROM app_expenses WHERE id = ' . $expenseId)->fetchColumn() === 'paid', 'expense should complete accounting workflow');

$updated = (new UserAdministrationService($pdo))->update($adminId, $employeeId, [
    'displayName' => 'Updated Test Employee',
    'active' => true,
    'managerUserId' => $managerId,
    'roles' => ['employee'],
]);
assert_true($updated['displayName'] === 'Updated Test Employee', 'Super Admin should update user data');

$auth = new AuthService($pdo);
$credentialVersion = $auth->changePassword($employeeId, 'Integration-only-password!', 'A-new-integration-password!');
assert_true($credentialVersion !== '', 'password change should create a credential version');
$_SESSION = [];
SessionSecurity::bindCredentialVersion('old-version');
assert_true(!SessionSecurity::credentialVersionIsCurrent($credentialVersion), 'old session version should be rejected');
SessionSecurity::bindCredentialVersion($credentialVersion);
assert_true(SessionSecurity::credentialVersionIsCurrent($credentialVersion), 'current session version should remain valid');
$loggedIn = $auth->login('employee@example.test', 'A-new-integration-password!');
assert_true($loggedIn['id'] === $employeeId, 'user should log in with changed password');
$oldPasswordRejected = false;
try {
    $auth->login('employee@example.test', 'Integration-only-password!');
} catch (RuntimeException) {
    $oldPasswordRejected = true;
}
assert_true($oldPasswordRejected, 'old password must stop working after password change');

$invitations = new UserInvitationService($pdo);
$token = $invitations->create('invited@example.test', 'Invited User', 'employee', $adminId, 24);
$invited = $invitations->accept($token, 'Invitation-password!');
assert_true($invited['email'] === 'invited@example.test', 'invitation should create a user');
$reusedInvitationRejected = false;
try {
    $invitations->accept($token, 'Invitation-password!');
} catch (RuntimeException) {
    $reusedInvitationRejected = true;
}
assert_true($reusedInvitationRejected, 'invitation token must be single use');

(new ActivityLogger($pdo))->log($adminId, 'integration.audit', 'user', $employeeId);
$audit = (new AuditLogService($pdo))->recent(10);
assert_true($audit['activity'][0]['action'] === 'integration.audit', 'activity audit should be readable');
assert_true(count($audit['logins']) >= 2, 'login audit should contain success and failure');

$accessDenied = false;
try {
    Authorization::requireAnyRole($employee, ['super_admin']);
} catch (\Delegacje\Security\AccessDeniedException) {
    $accessDenied = true;
}
assert_true($accessDenied, 'role denial should use the dedicated access exception');

echo "Integration smoke test passed.\n";
