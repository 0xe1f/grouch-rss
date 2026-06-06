<?php

declare(strict_types=1);

namespace Grouch\Tests;

use Grouch\parsers\Egyptian;
use Grouch\parsers\ParseResult;
use PHPUnit\Framework\Attributes\Group;

#[Group('golden')]
class EgyptianGoldenTest extends GoldenTestCase
{
    protected function getExpectedFile(): string
    {
        return __DIR__ . '/fixtures/egyptian_expected.json';
    }

    protected function getResult(): ParseResult
    {
        $source = file_get_contents(__DIR__ . '/fixtures/egyptian_source.html');

        $fetch = static fn(string $url): string => $source;

        $parser = new Egyptian();
        return $parser->parse('https://www.egyptiantheatre.com/', $fetch);
    }
}
