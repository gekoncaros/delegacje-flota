<?php
declare(strict_types=1);

namespace Delegacje\Security;

final class SessionSecurity
{
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name('delegacje_session');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
        self::enforceLifetime($config);
    }

    public static function markAuthenticated(): void
    {
        $now = time();
        $_SESSION['session_started_at'] = $now;
        $_SESSION['last_activity_at'] = $now;
    }

    public static function bindCredentialVersion(string $version): void
    {
        $_SESSION['credential_version'] = $version;
    }

    public static function credentialVersionIsCurrent(string $version): bool
    {
        if (!array_key_exists('credential_version', $_SESSION)) {
            // Preserve sessions created before this hardening was deployed. They are
            // bound to the current version on their first subsequent request.
            self::bindCredentialVersion($version);
            return true;
        }
        return hash_equals((string) $_SESSION['credential_version'], $version);
    }

    private static function enforceLifetime(array $config): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }
        $now = time();
        $idle = max(300, (int) ($config['session']['idle_timeout_seconds'] ?? 3600));
        $absolute = max($idle, (int) ($config['session']['absolute_timeout_seconds'] ?? 43200));
        $started = (int) ($_SESSION['session_started_at'] ?? $now);
        $lastActivity = (int) ($_SESSION['last_activity_at'] ?? $now);

        if (($now - $lastActivity) > $idle || ($now - $started) > $absolute) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            return;
        }

        $_SESSION['session_started_at'] = $started;
        $_SESSION['last_activity_at'] = $now;
    }
}
