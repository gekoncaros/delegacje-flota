<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';

require_method('POST');
$input = json_input();
$token = (string)($input['token'] ?? '');
$password = (string)($input['password'] ?? '');

if ($token === '' || $password === '') {
    api_error('Brak tokenu lub hasła.', 422);
}

try {
    $user = $invitations->accept($token, $password);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['roles'] = $user['roles'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    \Delegacje\Security\SessionSecurity::markAuthenticated();
    $createdUser = $auth->userById((int) $user['id']);
    \Delegacje\Security\SessionSecurity::bindCredentialVersion((string) ($createdUser['_credential_version'] ?? ''));
    api_audit($pdo, (int) $user['id'], 'auth.invitation.accept', 'user', (int) $user['id']);

    api_response([
        'ok' => true,
        'user' => $user,
        'csrfToken' => $_SESSION['csrf_token'],
    ]);
} catch (InvalidArgumentException $e) {
    api_exception($e, 422);
} catch (RuntimeException $e) {
    api_exception($e, 410);
}
