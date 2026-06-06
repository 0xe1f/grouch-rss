<?php

declare(strict_types=1);

namespace Grouch\parsers;

class ParseEntry
{
    public function __construct(
        public readonly string $guid,
        public readonly string $title,
        public readonly string $url,
        public readonly \DateTimeInterface $publishedAt,
        public readonly string $html = '',
        public readonly string $summary = '',
        public readonly string $author = '',
    ) {}
}
