<?php
declare(strict_types=1);

namespace Delegacje\Contracts;

interface DelegationEventRepository
{
    public function latestForDelegation(int $delegationId, int $userId): ?array;

    public function add(
        int $delegationId,
        int $userId,
        string $eventType,
        ?int $odometerKm = null,
        ?float $latitude = null,
        ?float $longitude = null,
        array $metadata = []
    ): int;
}
