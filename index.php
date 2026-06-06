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

require_once __DIR__ . '/src/autoload.php';

use Grouch\Auth;
use Grouch\Contract\ParseResult;
use Grouch\HttpClient;
use Grouch\parsers\AcMovies;
use Grouch\parsers\Egyptian;
use Grouch\Rss\RssBuilder;

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
defined('FEED_TOKEN') || define('FEED_TOKEN', (string) getenv('FEED_TOKEN'));

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

Auth::requireBearer($_SERVER['HTTP_AUTHORIZATION'] ?? '', FEED_TOKEN);

// ---------------------------------------------------------------------------
// Routing
// ---------------------------------------------------------------------------

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
// Strip a leading subdirectory prefix so the script works whether it's at /
// or at /feeds/ or any other mount point, as long as mod_rewrite funnels
// requests here.
$segment = trim(basename($path), '/');

$parsers = [
    'ac-movies' => new AcMovies(),
    'egyptian'  => new Egyptian(),
];

if (!isset($parsers[$segment])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unknown feed. Available: ' . implode(', ', array_keys($parsers));
    exit;
}

// ---------------------------------------------------------------------------
// Parse & respond
// ---------------------------------------------------------------------------

$selfUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . $_SERVER['REQUEST_URI'];

try {
    $result = $parsers[$segment]->parse($selfUrl, HttpClient::make());
    $xml    = buildXml($result);
} catch (\Throwable $e) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Feed error: ' . $e->getMessage();
    exit;
}

header('Content-Type: application/rss+xml; charset=utf-8');
echo $xml;

// ---------------------------------------------------------------------------

function buildXml(ParseResult $result): string
{
    $b = new RssBuilder();
    $b->feed(
        title:       $result->title,
        url:         $result->feedUrl,
        siteUrl:     $result->siteUrl,
        description: $result->description,
    );

    foreach ($result->entries as $entry) {
        $b->item(
            guid:        $entry->guid,
            title:       $entry->title,
            url:         $entry->url,
            publishedAt: $entry->publishedAt,
            html:        $entry->html,
            summary:     $entry->summary,
            author:      $entry->author,
        );
    }

    return $b->toXml();
}
