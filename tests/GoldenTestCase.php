<?php

declare(strict_types=1);

namespace Grouch\Tests;

use Grouch\parsers\ParseResult;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Base for golden (fixture-driven) tests.
 *
 * Subclasses provide:
 *  - a ParseResult produced by injecting a mock $fetch returning fixture data
 *  - an expected JSON file path
 *
 * This class handles the field-by-field comparison.
 */
abstract class GoldenTestCase extends TestCase
{
    abstract protected function getResult(): ParseResult;
    abstract protected function getExpectedFile(): string;

    // -------------------------------------------------------------------------

    private ParseResult $result;
    /** @var array<string,mixed> */
    private array $expected;

    protected function setUp(): void
    {
        parent::setUp();
        $this->result   = $this->getResult();
        $json           = file_get_contents($this->getExpectedFile());
        $this->expected = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    // -------------------------------------------------------------------------
    // Feed-level assertions
    // -------------------------------------------------------------------------

    #[Group('golden')]
    public function testFeedTitle(): void
    {
        $this->assertSame($this->expected['feed']['title'], $this->result->title);
    }

    #[Group('golden')]
    public function testFeedSiteUrl(): void
    {
        $this->assertSame($this->expected['feed']['siteUrl'], $this->result->siteUrl);
    }

    #[Group('golden')]
    public function testFeedDescription(): void
    {
        $this->assertSame($this->expected['feed']['description'], $this->result->description);
    }

    // -------------------------------------------------------------------------
    // Entry-level assertions
    // -------------------------------------------------------------------------

    #[Group('golden')]
    public function testEntryCount(): void
    {
        $this->assertCount(count($this->expected['entries']), $this->result->entries);
    }

    #[Group('golden')]
    public function testEntryGuids(): void
    {
        $expectedGuids = array_column($this->expected['entries'], 'guid');
        $actualGuids   = array_map(fn($e) => $e->guid, $this->result->entries);
        $this->assertSame($expectedGuids, $actualGuids);
    }

    #[Group('golden')]
    public function testEntryTitles(): void
    {
        foreach ($this->expected['entries'] as $i => $exp) {
            $this->assertSame(
                $exp['title'],
                $this->result->entries[$i]->title,
                "Title mismatch at entry $i ({$exp['guid']})",
            );
        }
    }

    #[Group('golden')]
    public function testEntryUrls(): void
    {
        foreach ($this->expected['entries'] as $i => $exp) {
            $this->assertSame(
                $exp['url'],
                $this->result->entries[$i]->url,
                "URL mismatch at entry $i ({$exp['guid']})",
            );
        }
    }

    #[Group('golden')]
    public function testEntryHtml(): void
    {
        foreach ($this->expected['entries'] as $i => $exp) {
            $this->assertSame(
                $exp['html'],
                $this->result->entries[$i]->html,
                "HTML mismatch at entry $i ({$exp['guid']})",
            );
        }
    }

    #[Group('golden')]
    public function testEntrySummaries(): void
    {
        foreach ($this->expected['entries'] as $i => $exp) {
            $this->assertSame(
                $exp['summary'],
                $this->result->entries[$i]->summary,
                "Summary mismatch at entry $i ({$exp['guid']})",
            );
        }
    }

    #[Group('golden')]
    public function testEntryAuthors(): void
    {
        foreach ($this->expected['entries'] as $i => $exp) {
            $this->assertSame(
                $exp['author'],
                $this->result->entries[$i]->author,
                "Author mismatch at entry $i ({$exp['guid']})",
            );
        }
    }
}
