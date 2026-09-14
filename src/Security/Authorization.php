<?php
declare(strict_types=1);

namespace Delegacje\Security;

final class Authorization
{
    public static function hasRole(array $user, string $role): bool
    {
        return in_array($role, $user['roles'] ?? [], true);
    }

    public static function hasAnyRole(array $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if (self::hasRole($user, (string)$role)) {
                return true;
            }
        }
        return false;
    }

    public static function requireAnyRole(array $user, array $roles): void
    {
        if (!self::hasAnyRole($user, $roles)) {
            http_response_code(403);
            throw new \RuntimeException('Brak uprawnień do wykonania tej operacji.');
        }
    }
}
