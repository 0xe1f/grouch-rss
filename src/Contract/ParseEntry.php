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

namespace Grouch\Contract;

use DateTimeInterface;

class ParseEntry
{
    public function __construct(
        public readonly string $guid,
        public readonly string $title,
        public readonly string $url,
        public readonly DateTimeInterface $publishedAt,
        public readonly string $html = '',
        public readonly string $summary = '',
        public readonly string $author = '',
    ) {}
}
