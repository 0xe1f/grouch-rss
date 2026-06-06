<?php

declare(strict_types=1);

namespace Grouch\Tests;

use Grouch\parsers\AcMovies;
use Grouch\parsers\ParserInterface;
use PHPUnit\Framework\Attributes\Group;

#[Group('live')]
class AcMoviesLiveTest extends LiveTestCase
{
    protected function getParser(): ParserInterface
    {
        return new AcMovies();
    }

    protected function getFeedUrl(): string
    {
        return 'https://www.americancinematheque.com/now-showing/';
    }
}
