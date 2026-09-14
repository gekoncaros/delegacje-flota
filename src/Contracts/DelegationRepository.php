<?php
declare(strict_types=1);

namespace Delegacje\Contracts;

interface DelegationRepository
{
    public function recentForUser(int $userId, int $limit = 20): array;
    public function findForUser(int $delegationId, int $userId): ?array;
    public function createForUser(int $userId, array $data): int;
}
