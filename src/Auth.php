<?php

// Copyright 2026 Akop Karapetyan
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

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
    /**
     * Returns true if the request carries a valid bearer token, false otherwise.
     * Never terminates the request.
     */
    public static function verifyBearer(string $authHeader, string $expectedToken): bool
    {
        if ($expectedToken === '') {
            return false;
        }

        if (str_starts_with($authHeader, 'Bearer ')) {
            $provided = substr($authHeader, 7);
            if (hash_equals($expectedToken, $provided)) {
                return true;
            }
        }

        preg_match('/(?:^|&)token=([^&]*)/', $_SERVER['QUERY_STRING'] ?? '', $m);
        $queryToken = isset($m[1]) ? rawurldecode($m[1]) : '';
        return $queryToken !== '' && hash_equals($expectedToken, $queryToken);
    }

    /**
     * Terminate the request with 401 unless the request carries a valid bearer token.
     *
     * Accepts both "Authorization: Bearer <token>" (standard header) and the
     * token via the "token" query parameter as a fallback (useful for RSS
     * readers that cannot set custom headers).
     */
    public static function requireBearer(string $authHeader, string $expectedToken): void
    {
        if ($expectedToken === '') {
            self::deny('Feed token is not configured on this server.');
        }
        if (!self::verifyBearer($authHeader, $expectedToken)) {
            self::deny('Invalid or missing Bearer token.');
        }
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
