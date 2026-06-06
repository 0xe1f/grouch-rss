<?php

declare(strict_types=1);

namespace Grouch\Tests;

use Grouch\HttpClient;
use Grouch\parsers\ParseResult;
use Grouch\parsers\ParserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Base for live (network) sanity tests.
 *
 * No content comparison — we only verify that the parser returns a
 * well-formed result with populated required fields. These tests can
 * flap due to network conditions or site changes; they are tagged
 * @group live and run with continue-on-error in CI.
 */
abstract class LiveTestCase extends TestCase
{
    abstract protected function getParser(): ParserInterface;
    abstract protected function getFeedUrl(): string;

    private ParseResult $result;

    protected function setUp(): void
    {
        parent::setUp();
        $this->result = $this->getParser()->parse(
            $this->getFeedUrl(),
            HttpClient::make(),
        );
    }

    // -------------------------------------------------------------------------

    #[Group('live')]
    public function testFeedTitleIsNonEmpty(): void
    {
        $this->assertNotEmpty($this->result->title, 'Feed title should not be empty');
    }

    #[Group('live')]
    public function testFeedSiteUrlIsNonEmpty(): void
    {
        $this->assertNotEmpty($this->result->siteUrl, 'Feed siteUrl should not be empty');
    }

    #[Group('live')]
    public function testAtLeastOneEntry(): void
    {
        $this->assertGreaterThanOrEqual(1, count($this->result->entries), 'Feed should have at least one entry');
    }

    #[Group('live')]
    public function testAllEntriesHaveGuid(): void
    {
        foreach ($this->result->entries as $entry) {
            $this->assertNotEmpty($entry->guid, "Entry guid should not be empty (title: {$entry->title})");
        }
    }

    #[Group('live')]
    public function testAllEntriesHaveTitle(): void
    {
        foreach ($this->result->entries as $entry) {
            $this->assertNotEmpty($entry->title, "Entry title should not be empty (guid: {$entry->guid})");
        }
    }

    #[Group('live')]
    public function testAllEntriesHaveUrl(): void
    {
        foreach ($this->result->entries as $entry) {
            $this->assertNotEmpty($entry->url, "Entry url should not be empty (guid: {$entry->guid})");
            $this->assertStringStartsWith('http', $entry->url, "Entry url should be absolute (guid: {$entry->guid})");
        }
    }

    #[Group('live')]
    public function testAllEntriesHavePublishedAt(): void
    {
        foreach ($this->result->entries as $entry) {
            $this->assertGreaterThan(
                0,
                $entry->publishedAt->getTimestamp(),
                "Entry publishedAt should be a valid timestamp (guid: {$entry->guid})",
            );
        }
    }
}
