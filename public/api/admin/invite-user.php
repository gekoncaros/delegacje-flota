<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';

use Delegacje\Security\Authorization;

require_method('POST');
$user = current_api_user($auth);
require_csrf();
Authorization::requireAnyRole($user, ['super_admin']);

$input = json_input();
$email = (string)($input['email'] ?? '');
$displayName = (string)($input['displayName'] ?? '');
$roleCode = (string)($input['roleCode'] ?? 'employee');
$ttlHours = (int)($input['ttlHours'] ?? 48);

try {
    $token = $invitations->create($email, $displayName, $roleCode, (int)$user['id'], $ttlHours);
    $baseUrl = rtrim((string)($config['url'] ?? getenv('APP_URL') ?: ''), '/');
    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = $host ? $scheme . '://' . $host : '';
    }
    $inviteUrl = $baseUrl . '/invite.php?token=' . rawurlencode($token);

    api_response([
        'ok' => true,
        'inviteUrl' => $inviteUrl,
        'expiresInHours' => max(1, min(168, $ttlHours)),
    ], 201);
} catch (InvalidArgumentException $e) {
    api_error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    api_error($e->getMessage(), 409);
}
