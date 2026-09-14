<?php
declare(strict_types=1);

namespace Delegacje\Infrastructure;

use PDO;

final class ActivityLogger
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function log(
        ?int $userId,
        string $action,
        ?string $objectType = null,
        ?int $objectId = null,
        array $metadata = []
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_log
                (user_id, action, object_type, object_id, ip_address, user_agent, metadata_json)
             VALUES
                (:user_id, :action, :object_type, :object_id, :ip_address, :user_agent, :metadata_json)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
