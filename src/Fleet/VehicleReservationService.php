<?php
declare(strict_types=1);

namespace Delegacje\Fleet;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class VehicleReservationService
{
    public function __construct(private PDO $pdo) {}

    public function listForUser(array $user): array
    {
        $roles = $user['roles'] ?? [];
        $canSeeAll = in_array('fleet_admin', $roles, true) || in_array('super_admin', $roles, true);
        $sql = 'SELECT r.*, v.make, v.model, v.registration_number, u.display_name AS user_name,
                       d.number AS delegation_number, d.destination AS delegation_destination
                FROM vehicle_reservations r
                JOIN vehicles v ON v.id = r.vehicle_id
                JOIN auth_users u ON u.id = r.user_id
                LEFT JOIN app_delegations d ON d.id = r.delegation_id';
        $params = [];
        if (!$canSeeAll) {
            $sql .= ' WHERE r.user_id = :user_id';
            $params['user_id'] = (int)$user['id'];
        }
        $sql .= ' ORDER BY r.starts_at DESC LIMIT 250';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $user, array $data): int
    {
        $vehicleId = (int)($data['vehicle_id'] ?? 0);
        $startsAt = $this->dateTimeValue($data['starts_at'] ?? null);
        $endsAt = $this->dateTimeValue($data['ends_at'] ?? null);
        if ($vehicleId < 1) {
            throw new RuntimeException('Wskaż pojazd.');
        }
        if ($endsAt <= $startsAt) {
            throw new RuntimeException('Koniec rezerwacji musi być później niż początek.');
        }

        $targetUserId = filter_var($data['user_id'] ?? $user['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($targetUserId === false) {
            throw new RuntimeException('Nieprawidłowy użytkownik rezerwacji.');
        }
        $purpose = trim((string)($data['purpose'] ?? ''));
        if (mb_strlen($purpose) > 255) {
            throw new RuntimeException('Cel rezerwacji może mieć maksymalnie 255 znaków.');
        }
        $roles = $user['roles'] ?? [];
        if ($targetUserId !== (int)$user['id'] && !in_array('fleet_admin', $roles, true) && !in_array('super_admin', $roles, true)) {
            throw new RuntimeException('Nie możesz rezerwować pojazdu dla innego użytkownika.');
        }

        $this->pdo->beginTransaction();
        try {
            $target = $this->pdo->prepare('SELECT id FROM auth_users WHERE id=:id AND active=1 LIMIT 1 FOR UPDATE');
            $target->execute(['id' => $targetUserId]);
            if (!$target->fetchColumn()) {
                throw new RuntimeException('Użytkownik rezerwacji jest nieaktywny lub nie istnieje.');
            }
            $vehicle = $this->pdo->prepare('SELECT id, status FROM vehicles WHERE id = :id FOR UPDATE');
            $vehicle->execute(['id' => $vehicleId]);
            $row = $vehicle->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Nie znaleziono pojazdu.');
            if (in_array((string)$row['status'], ['service','unsafe'], true)) {
                throw new RuntimeException('Pojazd jest obecnie niedostępny.');
            }

            $conflict = $this->pdo->prepare(
                "SELECT id FROM vehicle_reservations
                 WHERE vehicle_id = :vehicle_id AND status = 'active'
                   AND starts_at < :ends_at AND ends_at > :starts_at
                 LIMIT 1 FOR UPDATE"
            );
            $conflict->execute([
                'vehicle_id' => $vehicleId,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            ]);
            if ($conflict->fetchColumn()) {
                throw new RuntimeException('Pojazd jest już zarezerwowany w tym terminie.');
            }

            $delegationId = isset($data['delegation_id']) && (int)$data['delegation_id'] > 0 ? (int)$data['delegation_id'] : null;
            if ($delegationId !== null) {
                $this->assertDelegationCanBeReserved($delegationId, $targetUserId, $roles);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO vehicle_reservations
                 (vehicle_id,user_id,delegation_id,starts_at,ends_at,purpose,status,created_by)
                 VALUES (:vehicle_id,:user_id,:delegation_id,:starts_at,:ends_at,:purpose,\'active\',:created_by)'
            );
            $stmt->execute([
                'vehicle_id'=>$vehicleId,
                'user_id'=>$targetUserId,
                'delegation_id'=>$delegationId,
                'starts_at'=>$startsAt->format('Y-m-d H:i:s'),
                'ends_at'=>$endsAt->format('Y-m-d H:i:s'),
                'purpose'=>$purpose ?: null,
                'created_by'=>(int)$user['id'],
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function cancel(array $user, int $reservationId): void
    {
        $stmt = $this->pdo->prepare('SELECT user_id,status FROM vehicle_reservations WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$reservationId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new RuntimeException('Nie znaleziono rezerwacji.');
        $roles=$user['roles']??[];
        if((int)$row['user_id'] !== (int)$user['id'] && !in_array('fleet_admin',$roles,true) && !in_array('super_admin',$roles,true)) {
            throw new RuntimeException('Brak uprawnień do anulowania tej rezerwacji.');
        }
        if((string)$row['status'] !== 'active') return;
        $update=$this->pdo->prepare("UPDATE vehicle_reservations SET status='cancelled',cancelled_at=NOW(),cancelled_by=:uid WHERE id=:id");
        $update->execute(['uid'=>(int)$user['id'],'id'=>$reservationId]);
    }

    private function dateTimeValue(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Podaj termin rezerwacji.');
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException('Termin rezerwacji ma nieprawidłowy format.');
        }
        return $parsed;
    }

    private function assertDelegationCanBeReserved(int $delegationId, int $targetUserId, array $roles): void
    {
        $stmt = $this->pdo->prepare('SELECT user_id, status FROM app_delegations WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $delegationId]);
        $delegation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$delegation) {
            throw new RuntimeException('Nie znaleziono wskazanej delegacji.');
        }
        if ((int) $delegation['user_id'] !== $targetUserId && !in_array('fleet_admin', $roles, true) && !in_array('super_admin', $roles, true)) {
            throw new RuntimeException('Nie możesz powiązać rezerwacji z cudzą delegacją.');
        }
        if (in_array((string) $delegation['status'], ['rejected', 'finished'], true)) {
            throw new RuntimeException('Nie można rezerwować pojazdu dla odrzuconej ani zakończonej delegacji.');
        }
    }
}
