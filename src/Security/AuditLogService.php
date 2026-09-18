<?php
declare(strict_types=1);

namespace Delegacje\Security;

use PDO;

final class AuditLogService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recent(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $activity = $this->pdo->prepare(
            'SELECT l.id, l.action, l.object_type, l.object_id, l.ip_address, l.created_at,
                    u.email AS actor_email, u.display_name AS actor_name
             FROM activity_log l
             LEFT JOIN auth_users u ON u.id = l.user_id
             ORDER BY l.id DESC
             LIMIT :limit'
        );
        $activity->bindValue(':limit', $limit, PDO::PARAM_INT);
        $activity->execute();

        $logins = $this->pdo->prepare(
            'SELECT a.id, a.email_attempted, a.success, a.ip_address, a.created_at,
                    u.display_name AS actor_name
             FROM auth_login_audit a
             LEFT JOIN auth_users u ON u.id = a.user_id
             ORDER BY a.id DESC
             LIMIT :limit'
        );
        $logins->bindValue(':limit', $limit, PDO::PARAM_INT);
        $logins->execute();

        return [
            'activity' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'action' => (string) $row['action'],
                'objectType' => $row['object_type'] === null ? null : (string) $row['object_type'],
                'objectId' => $row['object_id'] === null ? null : (int) $row['object_id'],
                'ipAddress' => $row['ip_address'] === null ? null : (string) $row['ip_address'],
                'createdAt' => (string) $row['created_at'],
                'actorEmail' => $row['actor_email'] === null ? null : (string) $row['actor_email'],
                'actorName' => $row['actor_name'] === null ? null : (string) $row['actor_name'],
            ], $activity->fetchAll(PDO::FETCH_ASSOC)),
            'logins' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'email' => $row['email_attempted'] === null ? null : (string) $row['email_attempted'],
                'success' => (bool) $row['success'],
                'ipAddress' => $row['ip_address'] === null ? null : (string) $row['ip_address'],
                'createdAt' => (string) $row['created_at'],
                'actorName' => $row['actor_name'] === null ? null : (string) $row['actor_name'],
            ], $logins->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }
}
