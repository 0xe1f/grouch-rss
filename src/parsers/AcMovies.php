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

namespace Grouch\parsers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Grouch\Contract\ParseEntry;
use Grouch\Contract\ParserInterface;
use Grouch\Contract\ParseResult;

/**
 * American Cinematheque "Now Showing" feed.
 *
 * Fetches from the WordPress/Algolia JSON endpoint and returns upcoming events
 * for the next 30 days.
 */
class AcMovies implements ParserInterface
{
    public const string ROUTE = 'ac-movies';

    private const TITLE = 'Now Showing - American Cinematheque';
    private const HOME_URL = 'https://www.americancinematheque.com/now-showing/';
    private const FEED_URL = 'https://www.americancinematheque.com/wp-json/wp/v2/algolia_get_events'
        . '?environment=%s&startDate=%d&endDate=%d';
    private const TZ = 'US/Pacific';

    public function parse(string $feedUrl, callable $fetch): ParseResult
    {
        $tz    = new DateTimeZone(self::TZ);
        $now   = new DateTimeImmutable('now', $tz);
        $start = $now->setTime(0, 0, 0);
        $end   = $start->modify('+30 days');

        $apiUrl = sprintf(self::FEED_URL, $this->fetchEnvironment($fetch), $start->getTimestamp(), $end->getTimestamp());
        $body   = $fetch($apiUrl);
        $doc    = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $hits = array_filter(
            $doc['hits'] ?? [],
            fn(array $hit) => ($hit['post_type'] ?? '') === 'event',
        );

        $entries = array_map(fn(array $hit) => $this->makeEntry($hit, $now, $tz), array_values($hits));

        return new ParseResult(
            title:       self::TITLE,
            feedUrl:     $feedUrl,
            siteUrl:     self::HOME_URL,
            description: '',
            entries:     $entries,
        );
    }

    private function fetchEnvironment(callable $fetch): string
    {
        $html = $fetch(self::HOME_URL);
        if (preg_match('/window\.algolia_env\s*=\s*"([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        return 'production';
    }

    private function makeEntry(array $blob, DateTimeImmutable $now, DateTimeZone $tz): ParseEntry
    {
        $date = $blob['event_start_date'] ?? '';
        $time = $blob['event_start_time'] ?? '00:00:00';
        // Normalise H:i → H:i:s so createFromFormat always gets seconds.
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time .= ':00';
        }
        $startDt = DateTimeImmutable::createFromFormat('Ymd H:i:s', "$date $time", $tz)
            ?: (new DateTimeImmutable($date ?: '1970-01-01', $tz))->setTime(0, 0, 0);

        $title = html_entity_decode(trim($blob['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = $title . ' (' . $startDt->format('D M j') . ')';

        // event_card_excerpt may contain HTML markup; strip tags for plain-text use.
        $synopsis = trim(strip_tags($blob['event_card_excerpt'] ?? ''));
        $html     = $this->buildHtml($startDt, $synopsis, $blob['event_card_image'] ?? null);

        return new ParseEntry(
            guid:        (string) ($blob['objectID'] ?? ''),
            title:       $title,
            url:         trim($blob['url'] ?? ''),
            publishedAt: DateTimeImmutable::createFromFormat('U', (string) $now->getTimestamp()),
            html:        $html,
            summary:     $synopsis,
            author:      '',
        );
    }

    private function buildHtml(DateTimeImmutable $startDt, string $synopsis, ?array $imageData): string
    {
        $parts = [];
        $parts[] = '<p>' . htmlspecialchars($startDt->format('D M d | H:i'), ENT_XML1) . '</p>';

        if ($synopsis !== '') {
            $parts[] = '<p>' . htmlspecialchars($synopsis, ENT_XML1) . '</p>';
        }

        $imgUrl = $imageData['url'] ?? '';
        if ($imgUrl !== '') {
            $parts[] = '<img src="' . htmlspecialchars($imgUrl, ENT_XML1 | ENT_QUOTES) . '" />';
        }

        return '<div>' . "\n" . implode("\n", $parts) . "\n" . '</div>';
    }
}
