<?php

declare(strict_types=1);

namespace Grouch\Rss;

/**
 * Builds a valid RSS 2.0 document from feed metadata and items.
 *
 * Parser authors never touch XML: call feed() once, item() for each entry,
 * then toXml(). All escaping, CDATA wrapping, and date formatting is handled
 * internally.
 *
 * Example:
 *   $b = new RssBuilder();
 *   $b->feed(title: 'My Feed', url: '...', siteUrl: '...', description: '...');
 *   $b->item(guid: 'abc', title: 'Title', url: '...', publishedAt: new \DateTimeImmutable(), html: '<p>Hi</p>');
 *   echo $b->toXml();
 */
class RssBuilder
{
    private ?RssFeed $feed = null;
    /** @var RssItem[] */
    private array $items = [];

    public function feed(
        string $title,
        string $url,
        string $siteUrl,
        string $description = '',
    ): static {
        $this->feed = new RssFeed($title, $url, $siteUrl, $description);
        return $this;
    }

    public function item(
        string $guid,
        string $title,
        string $url,
        \DateTimeInterface $publishedAt,
        string $html = '',
        string $summary = '',
        string $author = '',
    ): static {
        $this->items[] = new RssItem($guid, $title, $url, $publishedAt, $html, $summary, $author);
        return $this;
    }

    public function toXml(): string
    {
        if ($this->feed === null) {
            throw new \LogicException('feed() must be called before toXml()');
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $rss = $doc->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $doc->appendChild($rss);

        $channel = $doc->createElement('channel');
        $rss->appendChild($channel);

        $this->appendText($doc, $channel, 'title', $this->feed->title);
        $this->appendText($doc, $channel, 'link', $this->feed->siteUrl);
        $this->appendText($doc, $channel, 'description', $this->feed->description);

        $atomLink = $doc->createElement('atom:link');
        $atomLink->setAttribute('href', $this->feed->url);
        $atomLink->setAttribute('rel', 'self');
        $atomLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomLink);

        foreach ($this->items as $item) {
            $channel->appendChild($this->buildItem($doc, $item));
        }

        return $doc->saveXML() ?: '';
    }

    private function buildItem(\DOMDocument $doc, RssItem $item): \DOMElement
    {
        $el = $doc->createElement('item');

        $this->appendText($doc, $el, 'title', $item->title);
        $this->appendText($doc, $el, 'link', $item->url);

        $guid = $doc->createElement('guid');
        $guid->setAttribute('isPermaLink', 'false');
        $guid->appendChild($doc->createTextNode($item->guid));
        $el->appendChild($guid);

        $this->appendText($doc, $el, 'pubDate', $item->publishedAt->format(\DateTime::RSS));

        if ($item->author !== '') {
            $this->appendText($doc, $el, 'author', $item->author);
        }

        // description: prefer rich HTML body, fall back to plain summary
        $body = $item->html !== '' ? $item->html : $item->summary;
        if ($body !== '') {
            $desc = $doc->createElement('description');
            $desc->appendChild($doc->createCDATASection($body));
            $el->appendChild($desc);
        }

        return $el;
    }

    private function appendText(
        \DOMDocument $doc,
        \DOMElement $parent,
        string $tag,
        string $value,
    ): void {
        $el = $doc->createElement($tag);
        $el->appendChild($doc->createTextNode($value));
        $parent->appendChild($el);
    }
}
