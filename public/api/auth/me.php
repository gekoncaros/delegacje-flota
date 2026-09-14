<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';

require_method('GET');
$user = current_api_user($auth);
api_response([
    'ok' => true,
    'user' => $user,
    'csrfToken' => $_SESSION['csrf_token'] ?? null,
]);
