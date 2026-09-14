<?php
declare(strict_types=1);

namespace Delegacje\Infrastructure;

use Delegacje\Contracts\DelegationRepository;
use InvalidArgumentException;
use PDO;

final class PdoDelegationRepository implements DelegationRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $map
    ) {}

    public function recentForUser(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $table = $this->id($this->map['delegations_table']);
        $id = $this->id($this->map['delegation_id']);
        $user = $this->id($this->map['delegation_user_id']);
        $destination = $this->id($this->map['delegation_destination']);
        $purpose = $this->id($this->map['delegation_purpose']);
        $from = $this->id($this->map['delegation_date_from']);
        $to = $this->id($this->map['delegation_date_to']);
        $status = $this->id($this->map['delegation_status']);

        $sql = "SELECT {$id} AS id, {$destination} AS destination, {$purpose} AS purpose,
                       {$from} AS date_from, {$to} AS date_to, {$status} AS status
                FROM {$table}
                WHERE {$user} = :user_id
                ORDER BY {$id} DESC
                LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function findForUser(int $delegationId, int $userId): ?array
    {
        $table = $this->id($this->map['delegations_table']);
        $id = $this->id($this->map['delegation_id']);
        $user = $this->id($this->map['delegation_user_id']);
        $destination = $this->id($this->map['delegation_destination']);
        $purpose = $this->id($this->map['delegation_purpose']);
        $from = $this->id($this->map['delegation_date_from']);
        $to = $this->id($this->map['delegation_date_to']);
        $status = $this->id($this->map['delegation_status']);

        $sql = "SELECT {$id} AS id, {$destination} AS destination, {$purpose} AS purpose,
                       {$from} AS date_from, {$to} AS date_to, {$status} AS status
                FROM {$table}
                WHERE {$id} = :delegation_id AND {$user} = :user_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['delegation_id' => $delegationId, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function createForUser(int $userId, array $data): int
    {
        $table = $this->id($this->map['delegations_table']);
        $user = $this->id($this->map['delegation_user_id']);
        $destination = $this->id($this->map['delegation_destination']);
        $purpose = $this->id($this->map['delegation_purpose']);
        $from = $this->id($this->map['delegation_date_from']);
        $to = $this->id($this->map['delegation_date_to']);
        $status = $this->id($this->map['delegation_status']);

        $sql = "INSERT INTO {$table}
                    ({$user}, {$destination}, {$purpose}, {$from}, {$to}, {$status})
                VALUES
                    (:user_id, :destination, :purpose, :date_from, :date_to, :status)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'destination' => $data['destination'],
            'purpose' => $data['purpose'],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'status' => $data['status'] ?? 'draft',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function id(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Nieprawidłowy identyfikator SQL w konfiguracji.');
        }

        return $identifier;
    }
}
