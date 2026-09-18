<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Security/AuditLogService.php';

use Delegacje\Security\AuditLogService;
use Delegacje\Security\Authorization;

require_method('GET');
$user = current_api_user($auth);
Authorization::requireAnyRole($user, ['super_admin']);
$limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 100],
]);
if ($limit === false) {
    api_error('Nieprawidłowy limit.', 422);
}

api_response(['ok' => true, 'audit' => (new AuditLogService($pdo))->recent((int) $limit)]);
