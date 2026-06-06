<?php

declare(strict_types=1);

namespace Grouch\parsers;

interface ParserInterface
{
    /**
     * Fetch and parse a feed.
     *
     * @param string   $feedUrl The self-URL of this feed (embedded in the RSS output).
     * @param callable $fetch   fn(string $url): string — returns response body.
     *                          Injected so tests can supply a mock without HTTP.
     */
    public function parse(string $feedUrl, callable $fetch): ParseResult;
}
