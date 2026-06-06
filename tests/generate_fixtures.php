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

/**
 * Regenerates expected golden fixtures from the PHP parsers + source fixtures.
 *
 * Run inside Docker:
 *   docker compose run --rm test-golden php tests/generate_fixtures.php
 *
 * Review the output files, then commit them as the new approved baseline.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use Grouch\Contract\ParseResult;
use Grouch\parsers\AcMovies;
use Grouch\parsers\Egyptian;

function resultToArray(ParseResult $r): array
{
    return [
        'feed' => [
            'title'       => $r->title,
            'feedUrl'     => $r->feedUrl,
            'siteUrl'     => $r->siteUrl,
            'description' => $r->description,
        ],
        'entries' => array_map(static fn($e) => [
            'guid'    => $e->guid,
            'title'   => $e->title,
            'url'     => $e->url,
            'html'    => $e->html,
            'summary' => $e->summary,
            'author'  => $e->author,
        ], $r->entries),
    ];
}

function generate(string $name, callable $getResult, string $outFile): void
{
    echo "Generating $name ... ";
    $result = $getResult();
    $json   = json_encode(resultToArray($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($outFile, $json . "\n");
    echo count($result->entries) . " entries written to $outFile\n";
}

$fixtureDir = __DIR__ . '/fixtures';

generate(
    name:      'amecine',
    getResult: static function () use ($fixtureDir): ParseResult {
        $source = file_get_contents("$fixtureDir/amecine_source.json");
        return (new AcMovies())->parse(
            'https://www.americancinematheque.com/now-showing/',
            static fn(string $url): string => $source,
        );
    },
    outFile: "$fixtureDir/amecine_expected.json",
);

generate(
    name:      'egyptian',
    getResult: static function () use ($fixtureDir): ParseResult {
        $source = file_get_contents("$fixtureDir/egyptian_source.html");
        return (new Egyptian())->parse(
            'https://www.egyptiantheatre.com/',
            static fn(string $url): string => $source,
        );
    },
    outFile: "$fixtureDir/egyptian_expected.json",
);

echo "Done. Review the fixtures, then commit them as the approved baseline.\n";
