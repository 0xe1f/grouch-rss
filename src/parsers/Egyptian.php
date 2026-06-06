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
use Grouch\Contract\ParseEntry;
use Grouch\Contract\ParserInterface;
use Grouch\Contract\ParseResult;
use RuntimeException;

/**
 * Egyptian Theatre "Special Engagements" feed.
 *
 * The site is a Next.js app. Film data is embedded as a JSON blob inside a
 * <script> tag. We extract it with a regex, decode it, and build RSS entries.
 */
class Egyptian implements ParserInterface
{
    private const HOME_URL        = 'https://www.egyptiantheatre.com/';
    private const TITLES_URL      = 'https://www.egyptiantheatre.com/special-engagements';
    private const IMAGE_PREFIX    = 'https://cms.ntflxthtrs.com/';
    private const DETAIL_PREFIX   = 'https://www.egyptiantheatre.com/film/';
    private const MIN_SYNOPSIS    = 5;

    public function parse(string $feedUrl, callable $fetch): ParseResult
    {
        $html  = $fetch(self::TITLES_URL);
        $meta  = $this->extractMeta($html);
        $films = $this->extractFilms($html);
        $now   = new DateTimeImmutable();

        $entries = array_map(fn(array $blob) => $this->makeEntry($blob), $films);

        return new ParseResult(
            title:       $meta['og:title'] ?? 'Egyptian Theatre',
            feedUrl:     $feedUrl,
            siteUrl:     self::HOME_URL,
            description: $meta['og:description'] ?? '',
            entries:     $entries,
        );
    }

    // -------------------------------------------------------------------------

    /** @return array<string,string> */
    private function extractMeta(string $html): array
    {
        $meta = [];
        preg_match_all('/<meta( \w+="[^"]*")+\/?>/i', $html, $tagMatches);
        foreach ($tagMatches[0] as $tag) {
            $prop  = null;
            $value = null;
            preg_match_all('/ (\w+)="([^"]*)"/', $tag, $attrMatches, PREG_SET_ORDER);
            foreach ($attrMatches as [, $name, $val]) {
                if (in_array($name, ['property', 'name'], true)) {
                    $prop = $val;
                }
                if ($name === 'content') {
                    $value = $val;
                }
            }
            if ($prop !== null && $value !== null) {
                $meta[$prop] = $value;
            }
        }
        return $meta;
    }

    /** @return array[] */
    private function extractFilms(string $html): array
    {
        // Next.js embeds data as: self.__next_f.push([1,"b:<json>"])
        preg_match_all(
            '/<script>self\.__next_f\.push\(\[1,"b:(.*?)"\]\)<\/script>/s',
            $html,
            $matches,
        );

        if (count($matches[1]) !== 1) {
            throw new RuntimeException(
                'Expected exactly 1 Next.js data blob, found ' . count($matches[1]),
            );
        }

        // The JSON string is JS-escaped; decode it to proper UTF-8.
        $escaped   = $matches[1][0];
        $unescaped = json_decode('"' . $escaped . '"');
        if ($unescaped === null) {
            throw new RuntimeException('Failed to decode Next.js JSON blob');
        }

        $data     = json_decode($unescaped, true, 512, JSON_THROW_ON_ERROR);
        $filmData = $data[count($data) - 1]['children'][count($data[count($data) - 1]['children']) - 1]
            ['children'][count($data[count($data) - 1]['children'][count($data[count($data) - 1]['children']) - 1]['children']) - 1]
            [-1]['value']['filmData']['data'] ?? null;

        if ($filmData === null) {
            // Fallback: search the decoded structure for filmData
            $filmData = $this->findFilmData($data);
        }

        if ($filmData === null) {
            throw new RuntimeException('Could not locate filmData in Next.js blob');
        }

        return array_column($filmData, 'attributes');
    }

    /** Recursively search for filmData key. */
    private function findFilmData(mixed $node): ?array
    {
        if (!is_array($node)) {
            return null;
        }
        if (isset($node['filmData']['data'])) {
            return $node['filmData']['data'];
        }
        foreach ($node as $child) {
            $result = $this->findFilmData($child);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    private function makeEntry(array $blob): ParseEntry
    {
        $title    = trim($blob['FilmName'] ?? '');
        $slug     = trim($blob['Slug'] ?? '');
        $synopsis = trim($blob['Synopsis'] ?? '');
        $director = trim($blob['Director'] ?? '');

        $opening = DateTimeImmutable::createFromFormat('Y-m-d', $blob['OpeningDate'] ?? '') ?: new DateTimeImmutable();
        $closing = DateTimeImmutable::createFromFormat('Y-m-d', $blob['ClosingDate'] ?? '') ?: $opening;

        $timeRange = ($opening->format('Ymd') !== $closing->format('Ymd'))
            ? $opening->format('D M j') . ' - ' . $closing->format('D M j')
            : $opening->format('D M j');

        $updatedAt = DateTimeImmutable::createFromFormat(
            DateTimeInterface::ATOM,
            $blob['updatedAt'] ?? '',
        ) ?: new DateTimeImmutable();

        return new ParseEntry(
            guid:        $slug,
            title:       "$title ($timeRange)",
            url:         self::DETAIL_PREFIX . $slug,
            publishedAt: $updatedAt,
            html:        $this->buildHtml($blob, $synopsis, $director),
            summary:     strlen($synopsis) >= self::MIN_SYNOPSIS ? $synopsis : '',
            author:      $director,
        );
    }

    private function buildHtml(array $blob, string $synopsis, string $director): string
    {
        $parts = [];

        $showtime = trim($blob['RedLabelOverride'] ?? '');
        if ($showtime !== '') {
            $parts[] = '<p>' . htmlspecialchars($showtime, ENT_XML1) . '</p>';
        }
        if ($director !== '') {
            $parts[] = '<p>Director: ' . htmlspecialchars($director, ENT_XML1) . '</p>';
        }

        $cast = preg_replace('/\s+/', ' ', $blob['Cast'] ?? '');
        if ($cast !== '' && $cast !== null) {
            $parts[] = '<p>Cast: ' . htmlspecialchars($cast, ENT_XML1) . '</p>';
        }

        if (strlen($synopsis) >= self::MIN_SYNOPSIS) {
            $parts[] = '<p>' . htmlspecialchars($synopsis, ENT_XML1) . '</p>';
        }

        $posterUrl = $this->extractPoster($blob);
        if ($posterUrl !== '') {
            $parts[] = '<img src="' . htmlspecialchars($posterUrl, ENT_XML1 | ENT_QUOTES) . '">';
        }

        return '<div>' . "\n" . implode("\n", $parts) . "\n" . '</div>';
    }

    private function extractPoster(array $blob): string
    {
        $formats = $blob['Poster']['data']['attributes']['formats'] ?? [];
        foreach (['medium', 'small', 'thumbnail'] as $size) {
            if (isset($formats[$size]['url'])) {
                $url = $formats[$size]['url'];
                // API may return a relative path or a full absolute URL.
                return str_starts_with($url, 'http') ? $url : self::IMAGE_PREFIX . ltrim($url, '/');
            }
        }
        return '';
    }
}
