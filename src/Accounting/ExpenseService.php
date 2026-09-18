<?php
declare(strict_types=1);

namespace Delegacje\Accounting;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class ExpenseService
{
    private const CATEGORIES = ['fuel', 'hotel', 'parking', 'toll', 'meal', 'other'];
    private const CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listForUser(array $user): array
    {
        $roles = $user['roles'] ?? [];
        $canSeeAll = in_array('accounting', $roles, true) || in_array('super_admin', $roles, true);
        $sql = 'SELECT e.*, u.display_name AS user_name, d.number AS delegation_number,
                       d.destination AS delegation_destination,
                       a.id AS attachment_id, a.mime_type AS attachment_mime
                FROM app_expenses e
                JOIN auth_users u ON u.id = e.user_id
                LEFT JOIN app_delegations d ON d.id = e.delegation_id
                LEFT JOIN expense_attachments a ON a.expense_id = e.id';
        $params = [];
        if (!$canSeeAll) {
            $sql .= ' WHERE e.user_id = :user_id';
            $params['user_id'] = (int) $user['id'];
        }
        $sql .= ' ORDER BY e.expense_date DESC, e.id DESC LIMIT 300';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $user, array $data, ?array $attachment = null): int
    {
        $category = trim((string) ($data['category'] ?? 'other'));
        $amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'PLN')));
        $date = trim((string) ($data['expense_date'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $delegationId = $this->nullableId($data['delegation_id'] ?? null);

        if (!in_array($category, self::CATEGORIES, true)) {
            throw new RuntimeException('Nieprawidłowa kategoria kosztu.');
        }
        if ($amount === false || $amount <= 0 || $amount > 1000000) {
            throw new RuntimeException('Kwota musi mieścić się między 0,01 a 1 000 000.');
        }
        if (!in_array($currency, self::CURRENCIES, true)) {
            throw new RuntimeException('Nieprawidłowa waluta.');
        }
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsedDate || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            throw new RuntimeException('Podaj prawidłową datę kosztu.');
        }
        if (mb_strlen($description) > 2000) {
            throw new RuntimeException('Opis kosztu może mieć maksymalnie 2000 znaków.');
        }

        $userId = (int) $user['id'];
        $this->pdo->beginTransaction();
        try {
            if ($delegationId !== null) {
                $this->assertOwnDelegation($delegationId, $userId);
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_expenses
                    (delegation_id, user_id, category, amount, currency, expense_date, description, status)
                 VALUES
                    (:delegation_id, :user_id, :category, :amount, :currency, :expense_date, :description, \'submitted\')'
            );
            $stmt->execute([
                'delegation_id' => $delegationId,
                'user_id' => $userId,
                'category' => $category,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'currency' => $currency,
                'expense_date' => $parsedDate->format('Y-m-d'),
                'description' => $description ?: null,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            if ($attachment !== null) {
                $this->pdo->prepare(
                    'INSERT INTO expense_attachments
                        (expense_id, delegation_id, user_id, original_name, stored_name, mime_type, size_bytes, sha256)
                     VALUES
                        (:expense_id, :delegation_id, :user_id, :original_name, :stored_name, :mime_type, :size_bytes, :sha256)'
                )->execute([
                    'expense_id' => $id,
                    'delegation_id' => $delegationId,
                    'user_id' => $userId,
                    'original_name' => $attachment['original_name'],
                    'stored_name' => $attachment['stored_name'],
                    'mime_type' => $attachment['mime_type'],
                    'size_bytes' => $attachment['size_bytes'],
                    'sha256' => $attachment['sha256'],
                ]);
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

    public function transition(array $user, int $expenseId, string $action, ?string $note): void
    {
        $roles = $user['roles'] ?? [];
        if (!in_array('accounting', $roles, true) && !in_array('super_admin', $roles, true)) {
            throw new RuntimeException('Brak uprawnień księgowych.');
        }
        $next = ['approve' => 'approved', 'reject' => 'rejected', 'pay' => 'paid'][$action] ?? null;
        if ($next === null) {
            throw new RuntimeException('Nieobsługiwana operacja księgowa.');
        }
        $note = trim((string) $note);
        if ($action === 'reject' && $note === '') {
            throw new RuntimeException('Podaj powód odrzucenia kosztu.');
        }

        $stmt = $this->pdo->prepare('SELECT status FROM app_expenses WHERE id = :id LIMIT 1 FOR UPDATE');
        $this->pdo->beginTransaction();
        try {
            $stmt->execute(['id' => $expenseId]);
            $current = $stmt->fetchColumn();
            if ($current === false) {
                throw new RuntimeException('Nie znaleziono kosztu.');
            }
            if ((in_array($action, ['approve', 'reject'], true) && $current !== 'submitted') || ($action === 'pay' && $current !== 'approved')) {
                throw new RuntimeException('Koszt jest w stanie, który nie pozwala na tę operację.');
            }
            $update = $this->pdo->prepare('UPDATE app_expenses SET status=:status, reviewed_by=:user_id, reviewed_at=NOW(), decision_note=:note WHERE id=:id');
            $update->execute(['status' => $next, 'user_id' => (int) $user['id'], 'note' => $note ?: null, 'id' => $expenseId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function attachmentForUser(array $user, int $attachmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.* FROM expense_attachments a
             JOIN app_expenses e ON e.id = a.expense_id
             WHERE a.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$attachment) {
            return null;
        }
        $roles = $user['roles'] ?? [];
        if ((int) $attachment['user_id'] !== (int) $user['id'] && !in_array('accounting', $roles, true) && !in_array('super_admin', $roles, true)) {
            return null;
        }
        return $attachment;
    }

    private function assertOwnDelegation(int $delegationId, int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM app_delegations WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $delegationId]);
        if ((int) $stmt->fetchColumn() !== $userId) {
            throw new RuntimeException('Nie możesz przypisać kosztu do tej delegacji.');
        }
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new RuntimeException('Nieprawidłowy identyfikator delegacji.');
        }
        return $id;
    }
}
