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
use Grouch\Contract\ParserInterface;
use Grouch\HttpClient;
use Grouch\Rss\RssBuilder;

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
defined('FEED_TOKEN') || define('FEED_TOKEN', (string) getenv('FEED_TOKEN'));

// ---------------------------------------------------------------------------
// Routing
// ---------------------------------------------------------------------------

$path    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segment = str_ends_with($path, '/') ? '' : trim(basename($path), '/');

// Auto-discover all parsers in src/parsers/. Each parser must define ROUTE.
$parsers = [];
foreach (glob(__DIR__ . '/src/parsers/*.php') as $file) {
    $class = 'Grouch\\parsers\\' . basename($file, '.php');
    if (is_a($class, ParserInterface::class, true) && defined("$class::ROUTE")) {
        $parsers[$class::ROUTE] = new $class();
    }
}

// ---------------------------------------------------------------------------
// Index page — shown when no feed segment is present in the URL.
// Token is embedded in links only if the request is authenticated.
// ---------------------------------------------------------------------------

// All requests require a valid token — index listing and feeds alike.
Auth::requireBearer($_SERVER['HTTP_AUTHORIZATION'] ?? '', FEED_TOKEN);

if (!isset($parsers[$segment])) {
    renderIndex($parsers, FEED_TOKEN);
    exit;
}

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

function renderIndex(array $parsers, string $token): void
{
    $feeds = [];
    foreach ($parsers as $route => $_) {
        $url = $route . ($token !== '' ? '?token=' . rawurlencode($token) : '');
        $feeds[] = ['route' => $route, 'url' => $url];
    }

    header('Content-Type: text/html; charset=utf-8');

    $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Feeds</title>
<?php foreach ($feeds as $f): ?>
<link rel="alternate" type="application/rss+xml" title="<?= $esc($f['route']) ?>" href="<?= $esc($f['url']) ?>">
<?php endforeach; ?>
<style>
  body { font: 1rem/1.6 system-ui, sans-serif; max-width: 42rem; margin: 2rem auto; padding: 0 1rem; color: #222; }
  h1   { font-size: 1.25rem; margin-bottom: 1rem; }
  ul   { padding: 0; list-style: none; }
  li   { padding: .35rem 0; border-bottom: 1px solid #eee; }
  a    { font-weight: 600; text-decoration: none; color: #0057b8; }
  a:hover { text-decoration: underline; }
  span { color: #666; font-size: .875rem; margin-left: .5rem; }
</style>
</head>
<body>
<h1>Feeds</h1>
<ul>
<?php foreach ($feeds as $f): ?>
<li><a href="<?= $esc($f['url']) ?>"><?= $esc($f['route']) ?></a><span>(<?= $esc($f['url']) ?>)</span></li>
<?php endforeach; ?>
</ul>
</body>
</html>
<?php
}
