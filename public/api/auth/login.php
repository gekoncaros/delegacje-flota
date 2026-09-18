<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';

require_method('POST');
$input = json_input();
$email = (string)($input['email'] ?? '');
$password = (string)($input['password'] ?? '');

if ($email === '' || $password === '') {
    api_error('Podaj e-mail i hasło.', 422);
}

try {
    $user = $auth->login($email, $password);
    session_regenerate_id(true);
    \Delegacje\Security\SessionSecurity::bindCredentialVersion((string) ($user['_credential_version'] ?? ''));
    unset($user['_credential_version']);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['roles'] = $user['roles'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    \Delegacje\Security\SessionSecurity::markAuthenticated();

    api_response([
        'ok' => true,
        'user' => $user,
        'csrfToken' => $_SESSION['csrf_token'],
    ]);
} catch (RuntimeException $e) {
    api_exception($e, 401);
}
