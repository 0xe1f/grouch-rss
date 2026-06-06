<?php

declare(strict_types=1);

namespace Grouch\Tests;

use Grouch\parsers\Egyptian;
use Grouch\parsers\ParserInterface;
use PHPUnit\Framework\Attributes\Group;

#[Group('live')]
class EgyptianLiveTest extends LiveTestCase
{
    protected function getParser(): ParserInterface
    {
        return new Egyptian();
    }

    protected function getFeedUrl(): string
    {
        return 'https://www.egyptiantheatre.com/';
    }
}
