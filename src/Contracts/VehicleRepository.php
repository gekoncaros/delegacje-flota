<?php
declare(strict_types=1);

namespace Delegacje\Contracts;

interface VehicleRepository
{
    public function allVisibleForUser(int $userId): array;
    public function findVisibleForUser(int $vehicleId, int $userId): ?array;
}
