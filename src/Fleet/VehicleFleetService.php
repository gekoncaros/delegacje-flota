<?php
declare(strict_types=1);

namespace Delegacje\Fleet;

use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Read and report operations available to every authenticated fleet user.
 * Administrative incident lifecycle operations remain in VehicleIncidentService.
 */
final class VehicleFleetService
{
    private const INCIDENT_CATEGORIES = ['failure', 'damage', 'tires', 'warning', 'service', 'documents', 'other'];
    private const STATUSES = ['available', 'service', 'unsafe'];
    private const FUEL_TYPES = ['petrol', 'diesel', 'lpg', 'hybrid', 'electric', 'other'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listVehicles(array $user): array
    {
        $admin = $this->isFleetAdmin($user);
        $fields = $admin
            ? 'v.id, v.make, v.model, v.registration_number, v.mileage_km, v.status, v.assigned_user_id,
               v.inspection_due, v.insurance_due, v.vin, v.year, v.fuel_type, v.notes, u.display_name AS assigned_user_name'
            : 'v.id, v.make, v.model, v.registration_number, v.mileage_km, v.status, v.assigned_user_id,
               v.inspection_due, v.insurance_due, u.display_name AS assigned_user_name';
        $stmt = $this->pdo->query(
            "SELECT {$fields}
             FROM vehicles v
             LEFT JOIN auth_users u ON u.id = v.assigned_user_id
             ORDER BY v.registration_number ASC
             LIMIT 500"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function managementOptions(array $user): array
    {
        $this->requireFleetAdmin($user);
        $stmt = $this->pdo->query(
            'SELECT id, display_name, email FROM auth_users WHERE active = 1 ORDER BY display_name, email LIMIT 1000'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveVehicle(array $user, array $data): int
    {
        $this->requireFleetAdmin($user);
        $id = $this->nullablePositiveInt($data['id'] ?? null, 'Nieprawidłowy pojazd.');
        $make = trim((string) ($data['make'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $registration = strtoupper(trim((string) ($data['registration_number'] ?? '')));
        $vin = strtoupper(trim((string) ($data['vin'] ?? '')));
        $year = $this->nullablePositiveInt($data['year'] ?? null, 'Nieprawidłowy rok produkcji.');
        $fuelType = trim((string) ($data['fuel_type'] ?? 'other'));
        $mileage = $this->nullableNonNegativeInt($data['mileage_km'] ?? 0) ?? 0;
        $status = trim((string) ($data['status'] ?? 'available'));
        $assignedUserId = $this->nullablePositiveInt($data['assigned_user_id'] ?? null, 'Nieprawidłowy użytkownik.');
        $inspectionDue = $this->nullableDate($data['inspection_due'] ?? null, 'Nieprawidłowa data badania technicznego.');
        $insuranceDue = $this->nullableDate($data['insurance_due'] ?? null, 'Nieprawidłowa data ubezpieczenia.');
        $notes = trim((string) ($data['notes'] ?? ''));

        if (mb_strlen($make) < 2 || mb_strlen($make) > 100 || mb_strlen($model) < 1 || mb_strlen($model) > 100) {
            throw new RuntimeException('Podaj prawidłową markę i model pojazdu.');
        }
        if (!preg_match('/^[A-Z0-9 -]{2,32}$/', $registration)) {
            throw new RuntimeException('Numer rejestracyjny może zawierać litery, cyfry, spacje i myślniki.');
        }
        if ($vin !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
            throw new RuntimeException('VIN musi mieć 17 znaków i nie może zawierać liter I, O ani Q.');
        }
        $maxYear = (int) date('Y') + 1;
        if ($year !== null && ($year < 1950 || $year > $maxYear)) {
            throw new RuntimeException('Rok produkcji jest poza dozwolonym zakresem.');
        }
        if (!in_array($fuelType, self::FUEL_TYPES, true) || !in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Nieprawidłowy typ paliwa lub status pojazdu.');
        }
        if (mb_strlen($notes) > 4000) {
            throw new RuntimeException('Uwagi mogą mieć maksymalnie 4000 znaków.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($id !== null) {
                $vehicle = $this->pdo->prepare('SELECT id FROM vehicles WHERE id = :id LIMIT 1 FOR UPDATE');
                $vehicle->execute(['id' => $id]);
                if (!$vehicle->fetchColumn()) {
                    throw new RuntimeException('Nie znaleziono pojazdu.');
                }
            }
            if ($assignedUserId !== null) {
                $this->assertActiveUser($assignedUserId);
            }
            if ($status === 'available' && $id !== null) {
                $this->assertNoOpenUnsafeIncident($id);
            }
            $duplicate = $id === null
                ? $this->pdo->prepare('SELECT id FROM vehicles WHERE registration_number = :registration LIMIT 1 FOR UPDATE')
                : $this->pdo->prepare('SELECT id FROM vehicles WHERE registration_number = :registration AND id <> :id LIMIT 1 FOR UPDATE');
            $duplicate->execute($id === null ? ['registration' => $registration] : ['registration' => $registration, 'id' => $id]);
            if ($duplicate->fetchColumn()) {
                throw new RuntimeException('Pojazd z tym numerem rejestracyjnym już istnieje.');
            }

            $params = [
                'make' => $make,
                'model' => $model,
                'registration' => $registration,
                'vin' => $vin ?: null,
                'year' => $year,
                'fuel_type' => $fuelType,
                'mileage' => $mileage,
                'status' => $status,
                'assigned_user_id' => $assignedUserId,
                'inspection_due' => $inspectionDue,
                'insurance_due' => $insuranceDue,
                'notes' => $notes ?: null,
            ];
            if ($id === null) {
                $params['qr_token'] = bin2hex(random_bytes(32));
                $stmt = $this->pdo->prepare(
                    'INSERT INTO vehicles
                        (make, model, registration_number, vin, year, fuel_type, mileage_km, status, assigned_user_id, inspection_due, insurance_due, notes, qr_token, updated_at)
                     VALUES
                        (:make, :model, :registration, :vin, :year, :fuel_type, :mileage, :status, :assigned_user_id, :inspection_due, :insurance_due, :notes, :qr_token, NOW())'
                );
                $stmt->execute($params);
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $params['id'] = $id;
                $stmt = $this->pdo->prepare(
                    'UPDATE vehicles SET make=:make, model=:model, registration_number=:registration, vin=:vin, year=:year,
                     fuel_type=:fuel_type, mileage_km=:mileage, status=:status, assigned_user_id=:assigned_user_id,
                     inspection_due=:inspection_due, insurance_due=:insurance_due, notes=:notes, updated_at=NOW()
                     WHERE id=:id'
                );
                $stmt->execute($params);
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function createIncident(array $user, array $data): int
    {
        $vehicleId = filter_var($data['vehicle_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $category = trim((string) ($data['category'] ?? 'other'));
        $description = trim((string) ($data['description'] ?? ''));
        $unsafeToDrive = filter_var($data['unsafe_to_drive'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $mileage = $this->nullableNonNegativeInt($data['mileage_km'] ?? null);

        if ($vehicleId === false || $vehicleId === null) {
            throw new RuntimeException('Wskaż pojazd.');
        }
        if (!in_array($category, self::INCIDENT_CATEGORIES, true)) {
            throw new RuntimeException('Nieprawidłowa kategoria zgłoszenia.');
        }
        if (mb_strlen($description) < 10 || mb_strlen($description) > 4000) {
            throw new RuntimeException('Opis zgłoszenia musi mieć od 10 do 4000 znaków.');
        }

        $this->pdo->beginTransaction();
        try {
            $vehicle = $this->pdo->prepare('SELECT id FROM vehicles WHERE id = :id FOR UPDATE');
            $vehicle->execute(['id' => $vehicleId]);
            if (!$vehicle->fetchColumn()) {
                throw new RuntimeException('Nie znaleziono pojazdu.');
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO vehicle_incidents
                    (vehicle_id, user_id, category, description, mileage_km, unsafe_to_drive, status, updated_at)
                 VALUES
                    (:vehicle_id, :user_id, :category, :description, :mileage_km, :unsafe_to_drive, \'open\', NOW())'
            );
            $stmt->execute([
                'vehicle_id' => $vehicleId,
                'user_id' => (int) $user['id'],
                'category' => $category,
                'description' => $description,
                'mileage_km' => $mileage,
                'unsafe_to_drive' => $unsafeToDrive ? 1 : 0,
            ]);
            $incidentId = (int) $this->pdo->lastInsertId();

            // A report marked unsafe immediately removes the vehicle from reservations.
            if ($unsafeToDrive) {
                $this->pdo->prepare("UPDATE vehicles SET status = 'unsafe', updated_at = NOW() WHERE id = :id")
                    ->execute(['id' => $vehicleId]);
            }

            $this->pdo->commit();
            return $incidentId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function nullableNonNegativeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($result === false) {
            throw new RuntimeException('Stan licznika musi być liczbą nieujemną.');
        }
        return $result;
    }

    private function nullablePositiveInt(mixed $value, string $error): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }
        $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($result === false) {
            throw new RuntimeException($error);
        }
        return $result;
    }

    private function nullableDate(mixed $value, string $error): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new RuntimeException($error);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            throw new RuntimeException($error);
        }
        return $date->format('Y-m-d');
    }

    private function assertActiveUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM auth_users WHERE id=:id AND active=1 LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $userId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Wybrany użytkownik jest nieaktywny lub nie istnieje.');
        }
    }

    private function assertNoOpenUnsafeIncident(int $vehicleId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM vehicle_incidents
             WHERE vehicle_id=:vehicle_id AND unsafe_to_drive=1 AND status <> 'resolved'
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute(['vehicle_id' => $vehicleId]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Nie można udostępnić pojazdu, dopóki zgłoszenie bezpieczeństwa nie zostanie zamknięte.');
        }
    }

    private function isFleetAdmin(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        return in_array('fleet_admin', $roles, true) || in_array('super_admin', $roles, true);
    }

    private function requireFleetAdmin(array $user): void
    {
        if (!$this->isFleetAdmin($user)) {
            throw new RuntimeException('Brak uprawnień administratora floty.');
        }
    }
}
