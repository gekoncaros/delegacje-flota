<?php
declare(strict_types=1);

namespace Delegacje\Infrastructure;

use Delegacje\Contracts\VehicleRepository;
use InvalidArgumentException;
use PDO;

final class PdoVehicleRepository implements VehicleRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $map
    ) {}

    public function allVisibleForUser(int $userId): array
    {
        $table = $this->id($this->map['vehicles_table']);

        $stmt = $this->pdo->query(
            "SELECT id, make, model, registration_number, mileage_km, status
             FROM {$table}
             ORDER BY registration_number"
        );

        return $stmt->fetchAll();
    }

    public function findVisibleForUser(int $vehicleId, int $userId): ?array
    {
        $table = $this->id($this->map['vehicles_table']);

        $stmt = $this->pdo->prepare(
            "SELECT id, make, model, registration_number, mileage_km, status
             FROM {$table}
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $vehicleId]);
        $vehicle = $stmt->fetch();

        return $vehicle === false ? null : $vehicle;
    }

    private function id(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Nieprawidłowy identyfikator SQL w konfiguracji.');
        }

        return $identifier;
    }
}
