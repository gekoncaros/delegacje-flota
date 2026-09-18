<?php
declare(strict_types=1);

namespace Delegacje\Security;

final class SecurityHeaders
{
    public static function send(bool $hideReferrer = false): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: ' . ($hideReferrer ? 'no-referrer' : 'same-origin'));
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');
        header('Cross-Origin-Resource-Policy: same-origin');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "base-uri 'none'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; "
            . "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; "
            . "manifest-src 'self'; worker-src 'self' blob:"
        );

        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}
