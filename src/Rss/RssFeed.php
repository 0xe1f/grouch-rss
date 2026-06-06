<?php

declare(strict_types=1);

namespace Grouch\Rss;

class RssFeed
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $siteUrl,
        public readonly string $description = '',
    ) {}
}
