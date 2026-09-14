<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';

use Delegacje\Security\Authorization;

require_method('GET');
$user = current_api_user($auth);
Authorization::requireAnyRole($user, ['super_admin']);

$stmt = $pdo->query(
    "SELECT u.id, u.email, u.display_name, u.active, u.manager_user_id, u.last_login_at,
            GROUP_CONCAT(r.role_code ORDER BY r.role_code SEPARATOR ',') AS roles
     FROM auth_users u
     LEFT JOIN user_role_assignments r ON r.user_id = u.id AND r.active = 1
     GROUP BY u.id, u.email, u.display_name, u.active, u.manager_user_id, u.last_login_at
     ORDER BY u.display_name, u.email"
);

$rows = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $rows[] = [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'displayName' => (string)$row['display_name'],
        'active' => (bool)$row['active'],
        'managerUserId' => $row['manager_user_id'] === null ? null : (int)$row['manager_user_id'],
        'lastLoginAt' => $row['last_login_at'],
        'roles' => $row['roles'] ? explode(',', (string)$row['roles']) : [],
    ];
}

api_response(['ok' => true, 'users' => $rows]);
