<?php
declare(strict_types=1);

namespace Delegacje\Infrastructure;

use Delegacje\Contracts\DelegationEventRepository;
use PDO;

final class PdoDelegationEventRepository implements DelegationEventRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function latestForDelegation(int $delegationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, delegation_id, user_id, event_type, occurred_at, odometer_km, latitude, longitude, metadata_json
             FROM delegation_events
             WHERE delegation_id = :delegation_id AND user_id = :user_id
             ORDER BY occurred_at DESC, id DESC
             LIMIT 1'
        );

        $stmt->execute([
            'delegation_id' => $delegationId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function add(
        int $delegationId,
        int $userId,
        string $eventType,
        ?int $odometerKm = null,
        ?float $latitude = null,
        ?float $longitude = null,
        array $metadata = []
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO delegation_events
                (delegation_id, user_id, event_type, occurred_at, odometer_km, latitude, longitude, ip_address, metadata_json)
             VALUES
                (:delegation_id, :user_id, :event_type, NOW(), :odometer_km, :latitude, :longitude, :ip_address, :metadata_json)'
        );

        $stmt->execute([
            'delegation_id' => $delegationId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'odometer_km' => $odometerKm,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
