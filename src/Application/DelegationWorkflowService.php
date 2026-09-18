<?php
declare(strict_types=1);

namespace Delegacje\Application;

use PDO;
use RuntimeException;

final class DelegationWorkflowService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listForUser(array $user): array
    {
        $roles = $user['roles'] ?? [];
        $userId = (int)$user['id'];

        if (in_array('super_admin', $roles, true)) {
            return $this->queryList('1=1', []);
        }
        if (in_array('accounting', $roles, true)) {
            return $this->queryList("status = 'finished' OR accounting_status <> 'not_ready'", []);
        }
        if (in_array('manager', $roles, true)) {
            return $this->queryList('(user_id = :uid OR manager_id = :mid)', ['uid' => $userId, 'mid' => $userId]);
        }

        return $this->queryList('user_id = :uid', ['uid' => $userId]);
    }

    public function create(array $user, array $data): array
    {
        $destination = trim((string)($data['destination'] ?? ''));
        $purpose = trim((string)($data['purpose'] ?? ''));
        $dateFrom = (string)($data['date_from'] ?? '');
        $dateTo = (string)($data['date_to'] ?? '');
        $transport = trim((string)($data['transport'] ?? 'company_car'));

        if ($destination === '' || $purpose === '' || $dateFrom === '' || $dateTo === '') {
            throw new RuntimeException('Uzupełnij cel, opis i daty delegacji.');
        }
        if ($dateTo < $dateFrom) {
            throw new RuntimeException('Data zakończenia nie może być wcześniejsza niż rozpoczęcia.');
        }

        $userId = (int)$user['id'];
        $managerId = $user['manager_user_id'] ?? null;
        $status = $managerId ? 'pending' : 'approved';
        $number = sprintf('DEL/%s/%s', date('Y'), strtoupper(bin2hex(random_bytes(3))));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_delegations
                (number,user_id,manager_id,destination,purpose,date_from,date_to,transport,status,submitted_at,approved_at,accounting_status)
                VALUES (:number,:user_id,:manager_id,:destination,:purpose,:date_from,:date_to,:transport,:status,NOW(),:approved_at,\'not_ready\')'
            );
            $stmt->execute([
                'number' => $number,
                'user_id' => $userId,
                'manager_id' => $managerId,
                'destination' => $destination,
                'purpose' => $purpose,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'transport' => $transport,
                'status' => $status,
                'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $this->history($id, $userId, 'created', null, $status, null, ['manager_id' => $managerId]);
            $this->pdo->commit();
            return $this->findVisible($id, $user);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function transition(int $id, array $user, string $action, array $data = []): array
    {
        $row = $this->findVisible($id, $user, true);
        $roles = $user['roles'] ?? [];
        $actorId = (int)$user['id'];
        $from = (string)$row['status'];
        $to = $from;
        $updates = [];
        $note = trim((string)($data['note'] ?? '')) ?: null;

        switch ($action) {
            case 'approve':
                $this->requireManager($row, $user, $roles);
                if ($from !== 'pending') throw new RuntimeException('Delegacja nie oczekuje na akceptację.');
                $to = 'approved'; $updates['approved_at'] = date('Y-m-d H:i:s');
                break;
            case 'reject':
                $this->requireManager($row, $user, $roles);
                if ($from !== 'pending') throw new RuntimeException('Delegacja nie oczekuje na akceptację.');
                $to = 'rejected'; $updates['rejected_at'] = date('Y-m-d H:i:s'); $updates['decision_note'] = $note;
                break;
            case 'start':
                if ((int)$row['user_id'] !== $actorId) throw new RuntimeException('Tylko właściciel delegacji może rozpocząć wyjazd.');
                if ($from !== 'approved') throw new RuntimeException('Delegacja musi być zatwierdzona.');
                $odo = (int)($data['odometer_km'] ?? 0);
                if ($odo < 0) throw new RuntimeException('Nieprawidłowy stan licznika.');
                $to = 'started';
                $updates += ['started_at'=>date('Y-m-d H:i:s'),'odometer_start'=>$odo,'start_lat'=>$this->nullableFloat($data['lat'] ?? null),'start_lng'=>$this->nullableFloat($data['lng'] ?? null)];
                break;
            case 'finish':
                if ((int)$row['user_id'] !== $actorId) throw new RuntimeException('Tylko właściciel delegacji może zakończyć wyjazd.');
                if ($from !== 'started') throw new RuntimeException('Delegacja nie jest rozpoczęta.');
                $odo = (int)($data['odometer_km'] ?? 0);
                if ($odo < (int)$row['odometer_start']) throw new RuntimeException('Końcowy stan licznika nie może być niższy od początkowego.');
                $to = 'finished';
                $updates += ['finished_at'=>date('Y-m-d H:i:s'),'odometer_end'=>$odo,'finish_lat'=>$this->nullableFloat($data['lat'] ?? null),'finish_lng'=>$this->nullableFloat($data['lng'] ?? null),'accounting_status'=>'ready'];
                break;
            case 'accounting_book':
                if (!in_array('accounting', $roles, true) && !in_array('super_admin', $roles, true)) throw new RuntimeException('Brak uprawnień księgowych.');
                if ($from !== 'finished') throw new RuntimeException('Najpierw zakończ delegację.');
                $updates['accounting_status'] = 'booked';
                break;
            case 'accounting_pay':
                if (!in_array('accounting', $roles, true) && !in_array('super_admin', $roles, true)) throw new RuntimeException('Brak uprawnień księgowych.');
                if ($from !== 'finished') throw new RuntimeException('Najpierw zakończ delegację.');
                $updates['accounting_status'] = 'paid';
                break;
            default:
                throw new RuntimeException('Nieobsługiwana operacja.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($to !== $from) $updates['status'] = $to;
            $this->updateRow($id, $updates);
            $this->history($id, $actorId, $action, $from, $to, $note, $data);
            $this->pdo->commit();
            return $this->findVisible($id, $user);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findVisible(int $id, array $user, bool $forUpdate = false): array
    {
        $sql = 'SELECT d.*, u.display_name AS employee_name, m.display_name AS manager_name FROM app_delegations d JOIN auth_users u ON u.id=d.user_id LEFT JOIN auth_users m ON m.id=d.manager_id WHERE d.id=:id';
        if ($forUpdate) $sql .= ' FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Nie znaleziono delegacji.');

        $roles = $user['roles'] ?? [];
        $uid = (int)$user['id'];
        $isAssignedManager = (int)($row['manager_id'] ?? 0) === $uid && in_array('manager', $roles, true);
        $visible = (int)$row['user_id'] === $uid || $isAssignedManager || in_array('accounting', $roles, true) || in_array('super_admin', $roles, true);
        if (!$visible) throw new RuntimeException('Brak dostępu do delegacji.');
        return $row;
    }

    private function queryList(string $where, array $params): array
    {
        $stmt = $this->pdo->prepare('SELECT d.*, u.display_name AS employee_name, m.display_name AS manager_name FROM app_delegations d JOIN auth_users u ON u.id=d.user_id LEFT JOIN auth_users m ON m.id=d.manager_id WHERE '.$where.' ORDER BY d.created_at DESC LIMIT 200');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function requireManager(array $row, array $user, array $roles): void
    {
        $uid = (int)$user['id'];
        $isAssignedManager = (int)($row['manager_id'] ?? 0) === $uid && in_array('manager', $roles, true);
        if (!$isAssignedManager && !in_array('super_admin', $roles, true)) {
            throw new RuntimeException('Tylko przypisany przełożony może podjąć decyzję.');
        }
    }

    private function updateRow(int $id, array $updates): void
    {
        if (!$updates) return;
        $allowed = ['status','approved_at','rejected_at','decision_note','started_at','finished_at','odometer_start','odometer_end','start_lat','start_lng','finish_lat','finish_lng','accounting_status'];
        $set = []; $params = ['id' => $id];
        foreach ($updates as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            $set[] = $key.' = :'.$key; $params[$key] = $value;
        }
        if (!$set) return;
        $stmt = $this->pdo->prepare('UPDATE app_delegations SET '.implode(', ', $set).' WHERE id = :id');
        $stmt->execute($params);
    }

    private function history(int $id, int $actor, string $action, ?string $from, ?string $to, ?string $note, array $metadata): void
    {
        unset($metadata['password'], $metadata['token']);
        $stmt = $this->pdo->prepare('INSERT INTO app_delegation_history (delegation_id,actor_user_id,action_code,from_status,to_status,note,metadata_json) VALUES (:delegation_id,:actor,:action,:from_status,:to_status,:note,:metadata)');
        $stmt->execute(['delegation_id'=>$id,'actor'=>$actor,'action'=>$action,'from_status'=>$from,'to_status'=>$to,'note'=>$note,'metadata'=>$metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null]);
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float)$value;
    }
}
