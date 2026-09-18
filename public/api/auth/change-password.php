<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';

require_method('POST');
$user = current_api_user($auth);
require_csrf();
$input = json_input();
$currentPassword = (string) ($input['currentPassword'] ?? '');
$newPassword = (string) ($input['newPassword'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    api_error('Podaj obecne i nowe hasło.', 422);
}

try {
    $credentialVersion = $auth->changePassword((int) $user['id'], $currentPassword, $newPassword);
    session_regenerate_id(true);
    \Delegacje\Security\SessionSecurity::markAuthenticated();
    \Delegacje\Security\SessionSecurity::bindCredentialVersion($credentialVersion);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    api_audit($pdo, (int) $user['id'], 'auth.password.change', 'user', (int) $user['id']);
    api_response(['ok' => true, 'csrfToken' => $_SESSION['csrf_token']]);
} catch (InvalidArgumentException $exception) {
    api_exception($exception, 422);
} catch (RuntimeException $exception) {
    api_exception($exception, 403);
}
