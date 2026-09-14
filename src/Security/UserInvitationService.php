<?php
declare(strict_types=1);

namespace Delegacje\Security;

use PDO;

final class UserInvitationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $email, string $displayName, string $roleCode, int $createdBy, int $ttlHours = 48): string
    {
        $email = strtolower(trim($email));
        $displayName = trim($displayName);
        $roleCode = trim($roleCode);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Nieprawidłowy adres e-mail.');
        }
        if ($displayName === '') {
            throw new \InvalidArgumentException('Podaj imię i nazwisko.');
        }

        $allowedRoles = ['employee', 'manager', 'accounting', 'fleet_admin', 'super_admin'];
        if (!in_array($roleCode, $allowedRoles, true)) {
            throw new \InvalidArgumentException('Nieprawidłowa rola.');
        }

        $check = $this->pdo->prepare('SELECT id FROM auth_users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);
        if ($check->fetchColumn()) {
            throw new \RuntimeException('Użytkownik z tym adresem e-mail już istnieje.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_invitations (email, display_name, role_code, token_hash, created_by, expires_at)
             VALUES (:email, :display_name, :role_code, :token_hash, :created_by, DATE_ADD(NOW(), INTERVAL :ttl HOUR))'
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':display_name', $displayName);
        $stmt->bindValue(':role_code', $roleCode);
        $stmt->bindValue(':token_hash', $tokenHash);
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $stmt->bindValue(':ttl', max(1, min(168, $ttlHours)), PDO::PARAM_INT);
        $stmt->execute();

        return $rawToken;
    }

    public function accept(string $rawToken, string $password): array
    {
        if (strlen($password) < 12) {
            throw new \InvalidArgumentException('Hasło musi mieć minimum 12 znaków.');
        }

        $tokenHash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare(
            'SELECT id, email, display_name, role_code
             FROM auth_invitations
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invite) {
            throw new \RuntimeException('Zaproszenie jest nieprawidłowe lub wygasło.');
        }

        $this->pdo->beginTransaction();
        try {
            $userStmt = $this->pdo->prepare(
                'INSERT INTO auth_users (email, display_name, password_hash, active, password_changed_at)
                 VALUES (:email, :display_name, :password_hash, 1, NOW())'
            );
            $userStmt->execute([
                'email' => $invite['email'],
                'display_name' => $invite['display_name'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int)$this->pdo->lastInsertId();

            $roleStmt = $this->pdo->prepare(
                'INSERT INTO user_role_assignments (user_id, role_code, active)
                 VALUES (:user_id, :role_code, 1)'
            );
            $roleStmt->execute([
                'user_id' => $userId,
                'role_code' => $invite['role_code'],
            ]);

            $used = $this->pdo->prepare('UPDATE auth_invitations SET used_at = NOW() WHERE id = :id');
            $used->execute(['id' => $invite['id']]);

            $this->pdo->commit();

            return [
                'id' => $userId,
                'email' => (string)$invite['email'],
                'display_name' => (string)$invite['display_name'],
                'roles' => [(string)$invite['role_code']],
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
