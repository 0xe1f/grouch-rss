# grouch-rss

A lightweight PHP application that turns venue event listings into private RSS feeds, accessible to any feed reader that supports bearer tokens.

Requires PHP 8.3+ and Apache with `mod_rewrite`. No framework, no Composer on the server.

## Local development

All local work runs inside Docker.

```bash
# Start a live dev server at http://localhost:8080
docker compose -f docker/docker-compose.yml up server

# Run golden (fixture-driven) tests
docker compose -f docker/docker-compose.yml run --rm test-golden

# Run live (network) sanity tests
docker compose -f docker/docker-compose.yml run --rm test-live
```

The dev server reads the token from the `FEED_TOKEN` environment variable,
which is set to `dev` in `docker-compose.yml`. Pull a feed with:

```
http://localhost:8080/ac-movies?token=dev
```

## Configuration

Copy `config.php.example` to `config.php` and set a secret token:

```php
define('FEED_TOKEN', 'your-secret-here');
```

`config.php` is never committed. In the Docker dev environment, `FEED_TOKEN=dev`
is injected automatically.

## Authentication

Every request must include the token via either:

- **Authorization header**: `Authorization: Bearer <token>`
- **Query parameter**: `?token=<token>` (URL-encode the value)

## Deployment

```bash
# Deploy to a remote server (copies config.php by default)
./deploy.sh akop@example.com:public_html/feeds

# Deploy without overwriting an existing config.php on the server
./deploy.sh --skip-config akop@example.com:public_html/feeds

# Deploy to a local directory
./deploy.sh /var/www/html/feeds
```

Requires SSH key-based access. Sets `755` on directories and `644` on files
after transfer.

## Writing a new parser

### 1. Create the parser class

Add `src/parsers/YourParser.php` implementing `Grouch\Contract\ParserInterface`:

```php
<?php

declare(strict_types=1);

namespace Grouch\parsers;

use DateTimeImmutable;
use Grouch\Contract\ParseEntry;
use Grouch\Contract\ParserInterface;
use Grouch\Contract\ParseResult;

class YourParser implements ParserInterface
{
    public function parse(string $feedUrl, callable $fetch): ParseResult
    {
        // $fetch is fn(string $url): string
        // Use it to retrieve remote content — tests will inject a mock.
        $body = $fetch('https://example.com/api/events.json');
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $entries = [];
        foreach ($data['events'] as $event) {
            $entries[] = new ParseEntry(
                guid:        $event['id'],
                title:       $event['title'],
                url:         $event['url'],
                publishedAt: new DateTimeImmutable($event['date']),
                html:        '<p>' . htmlspecialchars($event['body'], ENT_XML1) . '</p>',
                summary:     $event['excerpt'],
                author:      '',
            );
        }

        return new ParseResult(
            title:       'Your Feed Title',
            feedUrl:     $feedUrl,
            siteUrl:     'https://example.com',
            description: '',
            entries:     $entries,
        );
    }
}
```

`ParseEntry` fields:

| Field         | Type                | Required | Notes                                              |
|---------------|---------------------|----------|----------------------------------------------------|
| `guid`        | `string`            | Yes      | Stable, unique identifier for the item             |
| `title`       | `string`            | Yes      |                                                    |
| `url`         | `string`            | Yes      | Canonical permalink                                |
| `publishedAt` | `DateTimeInterface` | Yes      |                                                    |
| `html`        | `string`            | No       | Rich body; takes precedence over `summary` in XML  |
| `summary`     | `string`            | No       | Plain-text fallback when `html` is empty           |
| `author`      | `string`            | No       |                                                    |

### 2. Define the route name

Add a `ROUTE` constant to your parser class. This is the URL path segment the feed will be served at:

```php
public const string ROUTE = 'your-feed';
```

That's it — `index.php` auto-discovers every parser in `src/parsers/` that implements `ParserInterface` and defines `ROUTE`. The feed will be available at `/your-feed?token=…`.

### 3. Create the source fixture

Capture a representative API response and save it under `tests/fixtures/`:

```
tests/fixtures/yourparser_source.json   (or .html, depending on format)
```

### 4. Generate the expected fixture

```bash
docker compose -f docker/docker-compose.yml run --rm test-golden \
    php tests/generate_fixtures.php
```

Inspect `tests/fixtures/yourparser_expected.json`, then commit both fixtures.

### 5. Write the golden test

```php
// tests/YourParserGoldenTest.php
namespace Grouch\Tests;

use Grouch\parsers\YourParser;

class YourParserGoldenTest extends GoldenTestCase
{
    protected function getResult(): \Grouch\Contract\ParseResult
    {
        $source = file_get_contents(__DIR__ . '/fixtures/yourparser_source.json');
        $parser = new YourParser();
        return $parser->parse('https://example.com/your-feed', fn($_) => $source);
    }

    protected function getExpectedFile(): string
    {
        return __DIR__ . '/fixtures/yourparser_expected.json';
    }
}
```

Register it in `phpunit.xml`:

```xml
<testsuite name="golden">
    <!-- existing entries … -->
    <file>tests/YourParserGoldenTest.php</file>
</testsuite>
```

### 6. Write the live test

```php
// tests/YourParserLiveTest.php
namespace Grouch\Tests;

use Grouch\HttpClient;
use Grouch\parsers\YourParser;

class YourParserLiveTest extends LiveTestCase
{
    protected function getResult(): \Grouch\Contract\ParseResult
    {
        $parser = new YourParser();
        return $parser->parse('https://example.com/your-feed', HttpClient::make());
    }
}
```

Register it in `phpunit.xml` under the `live` testsuite.

## Architecture

See [`docs/plans/architecture.md`](docs/plans/architecture.md).

## License

Copyright 2026 Akop Karapetyan. Licensed under the [Apache License, Version 2.0](LICENSE).
