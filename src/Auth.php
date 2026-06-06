<?php

declare(strict_types=1);

namespace Grouch;

class Auth
{
    /**
     * Terminate the request with 401 unless the Authorization header carries
     * a valid Bearer token.
     *
     * Accepts both "Authorization: Bearer <token>" (standard header) and the
     * token via the "token" query parameter as a fallback (useful for RSS
     * readers that cannot set custom headers).
     */
    public static function requireBearer(string $authHeader, string $expectedToken): void
    {
        if ($expectedToken === '') {
            // No token configured — fail closed rather than open.
            self::deny('Feed token is not configured on this server.');
        }

        // Check Authorization header first.
        if (str_starts_with($authHeader, 'Bearer ')) {
            $provided = substr($authHeader, 7);
            if (hash_equals($expectedToken, $provided)) {
                return;
            }
        }

        // Fallback: ?token=... query param (for feed readers that can't set headers).
        $queryToken = $_GET['token'] ?? '';
        if ($queryToken !== '' && hash_equals($expectedToken, $queryToken)) {
            return;
        }

        self::deny('Invalid or missing Bearer token.');
    }

    private static function deny(string $message): never
    {
        http_response_code(401);
        header('WWW-Authenticate: Bearer realm="grouch-rss"');
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
