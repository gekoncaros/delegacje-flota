<?php
declare(strict_types=1);

namespace Delegacje\Security;

use PDO;

final class AuthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $user = $this->findByEmail($email);

        if (!$user || !(bool)$user['active']) {
            $this->audit(null, $email, false);
            throw new \RuntimeException('Nieprawidłowy login lub hasło.');
        }

        if (!empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time()) {
            $this->audit((int)$user['id'], $email, false);
            throw new \RuntimeException('Konto jest tymczasowo zablokowane.');
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            $this->registerFailure((int)$user['id']);
            $this->audit((int)$user['id'], $email, false);
            throw new \RuntimeException('Nieprawidłowy login lub hasło.');
        }

        $this->pdo->prepare(
            'UPDATE auth_users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id'
        )->execute(['id' => $user['id']]);

        $this->audit((int)$user['id'], $email, true);

        return [
            'id' => (int)$user['id'],
            'email' => (string)$user['email'],
            'display_name' => (string)$user['display_name'],
            'manager_user_id' => $user['manager_user_id'] === null ? null : (int)$user['manager_user_id'],
            'roles' => $this->rolesForUser((int)$user['id']),
        ];
    }

    public function userById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, display_name, active, manager_user_id FROM auth_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(bool)$user['active']) {
            return null;
        }

        return [
            'id' => (int)$user['id'],
            'email' => (string)$user['email'],
            'display_name' => (string)$user['display_name'],
            'manager_user_id' => $user['manager_user_id'] === null ? null : (int)$user['manager_user_id'],
            'roles' => $this->rolesForUser((int)$user['id']),
        ];
    }

    public function rolesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT role_code FROM user_role_assignments WHERE user_id = :user_id AND active = 1 ORDER BY role_code'
        );
        $stmt->execute(['user_id' => $userId]);
        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM auth_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function registerFailure(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE auth_users
             SET failed_login_count = failed_login_count + 1,
                 locked_until = CASE WHEN failed_login_count + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE) ELSE locked_until END
             WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    private function audit(?int $userId, string $email, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_login_audit (user_id, email_attempted, success, ip_address, user_agent)
             VALUES (:user_id, :email, :success, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'email' => $email,
            'success' => $success ? 1 : 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    }
}
