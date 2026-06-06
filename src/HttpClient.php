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

use RuntimeException;

/**
 * Minimal curl-based HTTP client.
 *
 * Returns a callable suitable for injection into parsers:
 *
 *   $fetch = HttpClient::make();
 *   $body  = $fetch('https://example.com/data.json');
 */
class HttpClient
{
    /**
     * Returns a fetch callable: fn(string $url): string
     *
     * Throws \RuntimeException on HTTP error or curl failure.
     */
    public static function make(): callable
    {
        return static function (string $url): string {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERAGENT      => 'grouch-rss/1.0',
                CURLOPT_HTTPHEADER     => ['Accept: application/json, text/html, */*'],
            ]);

            $body   = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                throw new RuntimeException("curl error for $url: $error");
            }
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException("HTTP $status fetching $url");
            }

            return (string) $body;
        };
    }
}
