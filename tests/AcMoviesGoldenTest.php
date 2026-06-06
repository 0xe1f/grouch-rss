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

namespace Grouch\Tests;

use Grouch\Contract\ParseResult;
use Grouch\parsers\AcMovies;
use PHPUnit\Framework\Attributes\Group;

#[Group('golden')]
class AcMoviesGoldenTest extends GoldenTestCase
{
    protected function getExpectedFile(): string
    {
        return __DIR__ . '/fixtures/amecine_expected.json';
    }

    protected function getResult(): ParseResult
    {
        $home   = file_get_contents(__DIR__ . '/fixtures/amecine_home.html');
        $source = file_get_contents(__DIR__ . '/fixtures/amecine_source.json');

        $fetch = static function (string $url) use ($home, $source): string {
            // First call fetches the homepage to extract the Algolia environment;
            // subsequent calls fetch the Algolia API endpoint.
            return str_contains($url, 'algolia_get_events') ? $source : $home;
        };

        $parser = new AcMovies();
        return $parser->parse('https://www.americancinematheque.com/now-showing/', $fetch);
    }
}
