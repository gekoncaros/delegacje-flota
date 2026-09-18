<?php
declare(strict_types=1);

namespace Delegacje\Security;

use PDO;
use RuntimeException;

final class UserAdministrationService
{
    private const ROLES = ['employee', 'manager', 'accounting', 'fleet_admin', 'super_admin'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listUsers(): array
    {
        $stmt = $this->pdo->query(
            "SELECT u.id, u.email, u.display_name, u.active, u.manager_user_id, u.last_login_at,
                    m.display_name AS manager_name,
                    GROUP_CONCAT(r.role_code ORDER BY r.role_code SEPARATOR ',') AS roles
             FROM auth_users u
             LEFT JOIN auth_users m ON m.id = u.manager_user_id
             LEFT JOIN user_role_assignments r ON r.user_id = u.id AND r.active = 1
             GROUP BY u.id, u.email, u.display_name, u.active, u.manager_user_id, u.last_login_at, m.display_name
             ORDER BY u.display_name, u.email"
        );

        $users = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $users[] = [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'displayName' => (string) $row['display_name'],
                'active' => (bool) $row['active'],
                'managerUserId' => $row['manager_user_id'] === null ? null : (int) $row['manager_user_id'],
                'managerName' => $row['manager_name'] === null ? null : (string) $row['manager_name'],
                'lastLoginAt' => $row['last_login_at'],
                'roles' => $row['roles'] ? explode(',', (string) $row['roles']) : [],
            ];
        }
        return $users;
    }

    public function update(int $actorId, int $targetId, array $data): array
    {
        $displayName = trim((string) ($data['displayName'] ?? ''));
        if (!array_key_exists('active', $data)) {
            throw new RuntimeException('Brak stanu konta.');
        }
        $active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null) {
            throw new RuntimeException('Nieprawidłowy stan konta.');
        }
        $managerId = $this->nullableId($data['managerUserId'] ?? null);
        $submittedRoles = $data['roles'] ?? null;
        if (!is_array($submittedRoles)) {
            throw new RuntimeException('Nieprawidłowa lista ról.');
        }
        $roles = [];
        foreach ($submittedRoles as $role) {
            if (!is_string($role) || !in_array($role, self::ROLES, true)) {
                throw new RuntimeException('Lista zawiera nieprawidłową rolę.');
            }
            $roles[] = $role;
        }
        $roles = array_values(array_unique($roles));

        if ($targetId < 1) {
            throw new RuntimeException('Nieprawidłowy użytkownik.');
        }
        if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 190) {
            throw new RuntimeException('Nazwa użytkownika musi mieć od 2 do 190 znaków.');
        }
        if ($roles === []) {
            throw new RuntimeException('Użytkownik musi mieć co najmniej jedną rolę.');
        }
        if ($targetId === $managerId) {
            throw new RuntimeException('Użytkownik nie może być własnym przełożonym.');
        }
        if ($targetId === $actorId && (!$active || !in_array('super_admin', $roles, true))) {
            throw new RuntimeException('Nie możesz wyłączyć własnego konta ani odebrać sobie roli Super Admina.');
        }

        $this->pdo->beginTransaction();
        try {
            $target = $this->pdo->prepare('SELECT id FROM auth_users WHERE id = :id LIMIT 1 FOR UPDATE');
            $target->execute(['id' => $targetId]);
            if (!$target->fetchColumn()) {
                throw new RuntimeException('Nie znaleziono użytkownika.');
            }
            if ($managerId !== null) {
                $this->assertEligibleManager($managerId);
            }

            $this->pdo->prepare(
                'UPDATE auth_users SET display_name = :display_name, active = :active, manager_user_id = :manager_id WHERE id = :id'
            )->execute([
                'display_name' => $displayName,
                'active' => $active ? 1 : 0,
                'manager_id' => $managerId,
                'id' => $targetId,
            ]);

            $this->pdo->prepare('UPDATE user_role_assignments SET active = 0, updated_at = NOW() WHERE user_id = :user_id')
                ->execute(['user_id' => $targetId]);
            $roleStmt = $this->pdo->prepare(
                'INSERT INTO user_role_assignments (user_id, role_code, active, updated_at)
                 VALUES (:user_id, :role_code, 1, NOW())
                 ON DUPLICATE KEY UPDATE active = 1, updated_at = NOW()'
            );
            foreach ($roles as $role) {
                $roleStmt->execute(['user_id' => $targetId, 'role_code' => $role]);
            }

            if (!$active || (!in_array('manager', $roles, true) && !in_array('super_admin', $roles, true))) {
                $this->pdo->prepare('UPDATE auth_users SET manager_user_id = NULL WHERE manager_user_id = :manager_id')
                    ->execute(['manager_id' => $targetId]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        foreach ($this->listUsers() as $user) {
            if ($user['id'] === $targetId) {
                return $user;
            }
        }
        throw new RuntimeException('Nie udało się odczytać zaktualizowanego użytkownika.');
    }

    private function assertEligibleManager(int $managerId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id FROM auth_users u
             JOIN user_role_assignments r ON r.user_id = u.id AND r.active = 1
             WHERE u.id = :id AND u.active = 1 AND r.role_code IN ('manager', 'super_admin')
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute(['id' => $managerId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Wybrany przełożony jest nieaktywny lub nie ma właściwej roli.');
        }
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new RuntimeException('Nieprawidłowy przełożony.');
        }
        return $id;
    }
}
