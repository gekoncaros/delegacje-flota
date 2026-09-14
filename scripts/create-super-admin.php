<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/Support/Env.php';
require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';

use Delegacje\Infrastructure\Database;
use Delegacje\Support\Env;

Env::load(dirname(__DIR__) . '/.env');
$config = require dirname(__DIR__) . '/config/app.php';
$pdo = (new Database($config['db']))->connection();

$email = mb_strtolower(trim((string)($argv[1] ?? '')));
$name = trim((string)($argv[2] ?? ''));
$password = (string)($argv[3] ?? '');

if ($email === '' || $name === '' || $password === '') {
    fwrite(STDERR, "Użycie: php scripts/create-super-admin.php admin@firma.pl \"Imię Nazwisko\" \"SilneHasło\"\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Nieprawidłowy adres e-mail.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Hasło musi mieć minimum 12 znaków.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO auth_users (email, display_name, password_hash, active, password_changed_at)
         VALUES (:email, :display_name, :password_hash, 1, NOW())'
    );
    $stmt->execute([
        'email' => $email,
        'display_name' => $name,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = (int)$pdo->lastInsertId();

    $role = $pdo->prepare(
        'INSERT INTO user_role_assignments (user_id, role_code, active) VALUES (:user_id, :role_code, 1)'
    );
    $role->execute(['user_id' => $userId, 'role_code' => 'super_admin']);

    $pdo->commit();
    fwrite(STDOUT, "Utworzono Super Admina #{$userId}: {$email}\n");
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Nie udało się utworzyć konta: {$e->getMessage()}\n");
    exit(1);
}
