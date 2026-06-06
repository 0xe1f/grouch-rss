<?php

declare(strict_types=1);

namespace Grouch\parsers;

/**
 * Intermediate data structure returned by every parser.
 *
 * Decouples fetching/parsing logic from RSS XML generation:
 * parsers populate this, RssBuilder consumes it.
 */
class ParseResult
{
    /** @param ParseEntry[] $entries */
    public function __construct(
        public readonly string $title,
        public readonly string $feedUrl,
        public readonly string $siteUrl,
        public readonly string $description,
        public readonly array $entries,
    ) {}
}
