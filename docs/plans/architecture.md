# Architecture

## Goals

- Convert venue-specific event listings into RSS feeds consumable by standard feed readers.
- Minimal production dependencies: plain PHP, Apache, no framework, no Composer on the server.
- Bearer token authentication so feeds are private but accessible to any feed reader that supports custom headers or query parameters.
- Simple local development via Docker; simple deployment via `rsync`.

## Request flow

```
Browser / feed reader
  └─ GET /?token=…                         (no feed segment)
  │    └─ Apache mod_rewrite → index.php
  │         ├─ Auth::requireBearer()       validates token (401 on failure)
  │         └─ renderIndex()               HTML page with <link rel="alternate">
  │                                        tags and human-readable feed list
  │
  └─ GET /ac-movies?token=…               (known feed segment)
       └─ Apache mod_rewrite → index.php
            ├─ Auth::requireBearer()       validates token (401 on failure)
            ├─ new AcMovies()              only this parser file is autoloaded
            ├─ parser->parse()             fetches & transforms data
            │    └─ HttpClient::make()     injectable fetch callable
            ├─ RssBuilder                  assembles RSS 2.0 XML
            └─ echo $xml
```

## Parser registry

Parsers are registered explicitly in `index.php`:

```php
$parsers = [
    'ac-movies' => ['class' => \Grouch\parsers\AcMovies::class, 'name' => 'American Cinematheque'],
    'egyptian'  => ['class' => \Grouch\parsers\Egyptian::class, 'name' => 'Egyptian Theatre'],
];
```

`::class` resolves to a string at compile time without triggering the autoloader,
so only the parser matching the requested route is ever loaded. `name` is the
human-readable label shown in the HTML index and in feed reader subscription
dialogs (via `<link rel="alternate" title="…">`).

Each parser class also declares `public const string ROUTE` matching its registry
key, as self-documentation.

## Namespace layout

```
Grouch\
  Auth              bearer-token gate; verifyBearer() (bool) and requireBearer() (halts on failure)
  HttpClient        thin cURL wrapper; returns an injectable callable
  Contract\
    ParserInterface   parse(feedUrl, fetch): ParseResult
    ParseResult       feed metadata + entries[]
    ParseEntry        guid, title, url, publishedAt, html, summary, author
  Rss\
    RssBuilder        fluent builder → RSS 2.0 XML via DOMDocument
    RssFeed           value object (feed metadata)
    RssItem           value object (single item)
  parsers\
    AcMovies          American Cinematheque WordPress/Algolia endpoint
    Egyptian          Egyptian Theatre Next.js CMS API
```

## Authentication

Tokens are compared with `hash_equals` to prevent timing attacks.

The `?token=` query parameter is parsed directly from `$_SERVER['QUERY_STRING']`
(not `$_GET`) so that literal `+` characters in base64 tokens are not silently
converted to spaces.

All routes — including the HTML index page — require a valid token. The index
page embeds the token in all feed URLs so a user who visits with a valid token
can subscribe to any feed directly from the listing.

## Testing strategy

| Suite  | Data source     | Must pass in CI? | Network? |
|--------|-----------------|------------------|----------|
| golden | Fixture files   | Yes              | No       |
| live   | Real endpoints  | No (advisory)    | Yes      |

Golden fixtures are committed JSON files (`tests/fixtures/*_expected.json`).
When parser output changes intentionally, regenerate them:

```bash
docker compose -f docker/docker-compose.yml run --rm test-golden \
    php tests/generate_fixtures.php
```

Then review the diff, commit the updated fixture, and re-run the golden suite.

## Deployment

Production requires only PHP 8.3+ and Apache with `mod_rewrite`. No Composer,
no framework, no build step. `src/autoload.php` is a hand-written PSR-4 loader
for the `Grouch\` namespace.

`deploy.sh` uses `rsync` to transfer source files and `ssh` to set permissions.
Development files (`tests/`, `docker/`, `phpunit.xml`, `deploy.sh`, `docs/`) are
excluded from the transfer. `config.php` (containing the secret token) is included
by default; pass `--skip-config` to leave an existing server copy untouched.
