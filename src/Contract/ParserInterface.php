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

interface ParserInterface
{
    /**
     * The URL path segment this parser handles, e.g. 'ac-movies'.
     * Every concrete parser must define: public const string ROUTE = '...';
     */

    /**
     * Fetch and parse a feed.
     *
     * @param string   $feedUrl The self-URL of this feed (embedded in the RSS output).
     * @param callable $fetch   fn(string $url): string — returns response body.
     *                          Injected so tests can supply a mock without HTTP.
     */
    public function parse(string $feedUrl, callable $fetch): ParseResult;
}
