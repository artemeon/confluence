<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Tests\Endpoint;

use Artemeon\Confluence\Endpoint\Auth;
use Artemeon\Confluence\Endpoint\Content;
use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * The content endpoint parses Confluence JSON responses into DTOs and fails loudly on
 * unexpected HTTP statuses. Responses are served by a Guzzle MockHandler — no live API.
 */
final class ContentTest extends TestCase
{
    public function testFindChildAttachmentsParsesResultsIntoDtos(): void
    {
        $json = json_encode([
            'results' => [
                ['id' => 'a1', 'title' => 'first.png'],
                ['id' => 'a2', 'title' => 'second.png', 'history' => ['lastUpdated' => ['when' => '2021-01-01T00:00:00.000Z']]],
            ],
        ], JSON_THROW_ON_ERROR);
        $content = $this->content(new Response(200, [], $json));

        $attachments = $content->findChildAttachments('123');

        self::assertCount(2, $attachments);
        self::assertContainsOnlyInstancesOf(ConfluenceAttachment::class, $attachments);
        self::assertSame('a1', $attachments[0]->getId());
        self::assertSame('second.png', $attachments[1]->getTitle());
    }

    public function testFindChildAttachmentsReturnsEmptyListWhenThereAreNoAttachments(): void
    {
        $content = $this->content(new Response(200, [], json_encode(['results' => []], JSON_THROW_ON_ERROR)));

        self::assertSame([], $content->findChildAttachments('123'));
    }

    public function testFindChildAttachmentsThrowsOnUnexpectedSuccessStatus(): void
    {
        // 204 is a 2xx (so Guzzle's http_errors does not trigger) but not the 200 the
        // endpoint requires, exercising the explicit non-200 guard.
        $content = $this->content(new Response(204));

        $this->expectException(Exception::class);
        $content->findChildAttachments('123');
    }

    public function testFindChildAttachmentsSurfacesServerErrorsAsGuzzleException(): void
    {
        $content = $this->content(new Response(500, [], 'boom'));

        $this->expectException(GuzzleException::class);
        $content->findChildAttachments('123');
    }

    public function testFindPagesInSpaceParsesPagesAndTerminatesOnAShortPage(): void
    {
        $json = json_encode([
            'results' => [
                ['id' => 'p1', 'title' => 'Page one'],
                ['id' => 'p2', 'title' => 'Page two'],
            ],
            'limit' => 200,
            'size' => 2,
        ], JSON_THROW_ON_ERROR);
        $content = $this->content(new Response(200, [], $json));

        $pages = $content->findPagesInSpace('SPACE');

        self::assertCount(2, $pages);
        self::assertContainsOnlyInstancesOf(ConfluencePage::class, $pages);
        self::assertArrayHasKey('p1', $pages);
        self::assertSame('Page two', $pages['p2']->getTitle());
    }

    public function testFindPageContentParsesASinglePage(): void
    {
        $json = json_encode([
            'id' => 'p9',
            'title' => 'Single page',
            'body' => ['storage' => ['value' => '<p>body</p>']],
        ], JSON_THROW_ON_ERROR);
        $content = $this->content(new Response(200, [], $json));

        $page = $content->findPageContent('p9');

        self::assertSame('p9', $page->getId());
        self::assertSame('<p>body</p>', $page->getContent());
    }

    public function testFindPageContentThrowsOnUnexpectedSuccessStatus(): void
    {
        $content = $this->content(new Response(204));

        $this->expectException(Exception::class);
        $content->findPageContent('p9');
    }

    private function content(Response $response): Content
    {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $client = new Client(['handler' => $stack, 'base_uri' => 'https://example.atlassian.net/']);

        return new Content($client, new Auth('user@example.com', 'secret-token'));
    }
}
