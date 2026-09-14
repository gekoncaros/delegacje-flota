<?php
declare(strict_types=1);

namespace Delegacje\Fleet;

use PDO;
use RuntimeException;

final class VehicleReservationService
{
    public function __construct(private PDO $pdo) {}

    public function listForUser(array $user): array
    {
        $roles = $user['roles'] ?? [];
        $canSeeAll = in_array('fleet_admin', $roles, true) || in_array('super_admin', $roles, true);
        $sql = 'SELECT r.*, v.make, v.model, v.registration_number, u.display_name AS user_name
                FROM vehicle_reservations r
                JOIN vehicles v ON v.id = r.vehicle_id
                JOIN auth_users u ON u.id = r.user_id';
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
        $startsAt = trim((string)($data['starts_at'] ?? ''));
        $endsAt = trim((string)($data['ends_at'] ?? ''));
        if ($vehicleId < 1 || $startsAt === '' || $endsAt === '') {
            throw new RuntimeException('Podaj pojazd oraz termin rezerwacji.');
        }
        if (strtotime($endsAt) <= strtotime($startsAt)) {
            throw new RuntimeException('Koniec rezerwacji musi być później niż początek.');
        }

        $targetUserId = (int)($data['user_id'] ?? $user['id']);
        $roles = $user['roles'] ?? [];
        if ($targetUserId !== (int)$user['id'] && !in_array('fleet_admin', $roles, true) && !in_array('super_admin', $roles, true)) {
            throw new RuntimeException('Nie możesz rezerwować pojazdu dla innego użytkownika.');
        }

        $this->pdo->beginTransaction();
        try {
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
            $conflict->execute(['vehicle_id'=>$vehicleId,'starts_at'=>$startsAt,'ends_at'=>$endsAt]);
            if ($conflict->fetchColumn()) {
                throw new RuntimeException('Pojazd jest już zarezerwowany w tym terminie.');
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO vehicle_reservations
                 (vehicle_id,user_id,delegation_id,starts_at,ends_at,purpose,status,created_by)
                 VALUES (:vehicle_id,:user_id,:delegation_id,:starts_at,:ends_at,:purpose,\'active\',:created_by)'
            );
            $stmt->execute([
                'vehicle_id'=>$vehicleId,
                'user_id'=>$targetUserId,
                'delegation_id'=>isset($data['delegation_id']) && (int)$data['delegation_id'] > 0 ? (int)$data['delegation_id'] : null,
                'starts_at'=>$startsAt,
                'ends_at'=>$endsAt,
                'purpose'=>trim((string)($data['purpose'] ?? '')) ?: null,
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
}
