<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Security/UserAdministrationService.php';

use Delegacje\Security\Authorization;
use Delegacje\Security\UserAdministrationService;

require_method('POST');
require_csrf();
$actor = current_api_user($auth);
Authorization::requireAnyRole($actor, ['super_admin']);
$payload = json_input();
$targetId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($targetId === false || $targetId === null) {
    api_error('Nieprawidłowy użytkownik.', 422);
}

try {
    $updated = (new UserAdministrationService($pdo))->update((int) $actor['id'], $targetId, $payload);
    api_audit($pdo, (int) $actor['id'], 'admin.user.update', 'user', $targetId, [
        'active' => $updated['active'],
        'roles' => $updated['roles'],
        'manager_user_id' => $updated['managerUserId'],
    ]);
    api_response(['ok' => true, 'user' => $updated]);
} catch (RuntimeException $e) {
    api_exception($e, 422);
}
