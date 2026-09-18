<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Security/UserAdministrationService.php';

use Delegacje\Security\Authorization;
use Delegacje\Security\UserAdministrationService;

require_method('GET');
$user = current_api_user($auth);
Authorization::requireAnyRole($user, ['super_admin']);

api_response(['ok' => true, 'users' => (new UserAdministrationService($pdo))->listUsers()]);
