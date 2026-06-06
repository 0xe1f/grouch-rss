<?php

declare(strict_types=1);

namespace Grouch\Tests;

use Grouch\parsers\AcMovies;
use Grouch\parsers\ParseResult;
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
        $source = file_get_contents(__DIR__ . '/fixtures/amecine_source.json');

        // The parser fetches a date-ranged URL; the mock always returns the
        // captured Algolia snapshot regardless of the requested URL.
        $fetch = static fn(string $url): string => $source;

        $parser = new AcMovies();
        return $parser->parse('https://www.americancinematheque.com/now-showing/', $fetch);
    }
}
