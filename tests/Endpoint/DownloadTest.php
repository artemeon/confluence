<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Tests\Endpoint;

use Artemeon\Confluence\Endpoint\Auth;
use Artemeon\Confluence\Endpoint\Download;
use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The download endpoint is the heart of the #32861 fix: attachments must be fetched
 * from the REST API (not the legacy /wiki/download servlet) using the same Basic auth,
 * and only when the local copy is stale. All HTTP is served by a Guzzle MockHandler,
 * so these tests never touch the live Confluence API.
 */
final class DownloadTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/confluence-download-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->tempDir);
    }

    public function testDownloadAttachmentHitsTheRestEndpointAndWritesTheFile(): void
    {
        $mock = new MockHandler([new Response(200, [], 'PNG-BYTES')]);
        $download = new Download($this->client($mock), $this->auth(), $this->tempDir);

        $download->downloadAttachment($this->attachment('att42', 'image.png'), '12345');

        self::assertSame('PNG-BYTES', file_get_contents($this->tempDir . '/image.png'));
        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame(
            '/wiki/rest/api/content/12345/child/attachment/att42/download',
            $request->getUri()->getPath(),
        );
    }

    public function testDownloadAttachmentSendsBasicAuthCredentials(): void
    {
        $mock = new MockHandler([new Response(200, [], 'data')]);
        $download = new Download($this->client($mock), $this->auth(), $this->tempDir);

        $download->downloadAttachment($this->attachment('att1', 'file.bin'), '1');

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        $authorization = $request->getHeaderLine('Authorization');
        self::assertStringStartsWith('Basic ', $authorization);
        self::assertSame(
            'user@example.com:secret-token',
            base64_decode(substr($authorization, strlen('Basic ')), true),
        );
    }

    public function testDownloadAttachmentSkipsDownloadWhenLocalCopyIsUpToDate(): void
    {
        file_put_contents($this->tempDir . '/image.png', 'existing');

        // No responses queued: any HTTP call would make the MockHandler throw.
        $mock = new MockHandler([]);
        $download = new Download($this->client($mock), $this->auth(), $this->tempDir);

        // Attachment last changed in the past, local file's mtime is "now" -> up to date.
        $download->downloadAttachment($this->attachment('att1', 'image.png', '2000-01-01T00:00:00.000Z'), '1');

        self::assertNull($mock->getLastRequest());
        self::assertSame('existing', file_get_contents($this->tempDir . '/image.png'));
    }

    public function testDownloadAttachmentReDownloadsWhenRemoteIsNewerThanLocalCopy(): void
    {
        file_put_contents($this->tempDir . '/image.png', 'stale');

        $mock = new MockHandler([new Response(200, [], 'fresh')]);
        $download = new Download($this->client($mock), $this->auth(), $this->tempDir);

        // Attachment last changed far in the future, local file's mtime is "now" -> stale.
        $download->downloadAttachment($this->attachment('att1', 'image.png', '2999-01-01T00:00:00.000Z'), '1');

        self::assertNotNull($mock->getLastRequest());
        self::assertSame('fresh', file_get_contents($this->tempDir . '/image.png'));
    }

    public function testDownloadPageContentWritesContentToTheGivenFile(): void
    {
        $mock = new MockHandler([]);
        $download = new Download($this->client($mock), $this->auth(), $this->tempDir);

        $download->downloadPageContent($this->page('<h1>Hello</h1>'), 'content.html');

        self::assertSame('<h1>Hello</h1>', file_get_contents($this->tempDir . '/content.html'));
        self::assertNull($mock->getLastRequest());
    }

    public function testEnsureDownloadFolderCreatesAMissingNestedFolder(): void
    {
        $nested = $this->tempDir . '/a/b/c';
        $download = new Download($this->client(new MockHandler([])), $this->auth(), $nested);

        $download->downloadPageContent($this->page('x'), 'content.html');

        self::assertDirectoryExists($nested);
        self::assertFileExists($nested . '/content.html');
    }

    public function testThrowsWhenDownloadFolderCannotBeCreated(): void
    {
        // A file blocks the folder path, so mkdir() cannot create it.
        $blocker = $this->tempDir . '/iam-a-file';
        file_put_contents($blocker, 'x');

        $download = new Download($this->client(new MockHandler([])), $this->auth(), $blocker . '/sub');

        // Swallow the expected mkdir() warning so PHPUnit's strict handler does not
        // fail the test before the RuntimeException we actually want to assert.
        set_error_handler(static fn (): bool => true);

        try {
            $this->expectException(RuntimeException::class);
            $download->downloadPageContent($this->page('x'), 'content.html');
        } finally {
            restore_error_handler();
        }
    }

    private function client(MockHandler $mock): Client
    {
        return new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://example.atlassian.net/',
        ]);
    }

    private function auth(): Auth
    {
        return new Auth('user@example.com', 'secret-token');
    }

    private function attachment(string $id, string $title, ?string $lastUpdated = null): ConfluenceAttachment
    {
        $rawData = ['id' => $id, 'title' => $title];
        if ($lastUpdated !== null) {
            $rawData['history'] = ['lastUpdated' => ['when' => $lastUpdated]];
        }

        return new ConfluenceAttachment($rawData);
    }

    private function page(string $content): ConfluencePage
    {
        return new ConfluencePage(['id' => '1', 'body' => ['storage' => ['value' => $content]]]);
    }

    private function removeRecursively(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->removeRecursively($path . '/' . $entry);
                }
            }
            rmdir($path);

            return;
        }

        unlink($path);
    }
}
