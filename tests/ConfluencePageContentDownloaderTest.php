<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Tests;

use Artemeon\Confluence\ConfluencePageContentDownloader;
use Artemeon\Confluence\Endpoint\Content;
use Artemeon\Confluence\Endpoint\Download;
use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use Artemeon\Confluence\MacroReplacer\MacroReplacerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The downloader orchestrates macro replacement, page download and attachment download.
 * Since the #32861 fix it must also report failures through the injected PSR-3 logger
 * instead of silently swallowing them.
 */
final class ConfluencePageContentDownloaderTest extends TestCase
{
    public function testAppliesReplacersAndDownloadsContentAndAttachments(): void
    {
        $attachment = new ConfluenceAttachment(['id' => 'att1', 'title' => 'image.png']);

        $replacer = $this->createMock(MacroReplacerInterface::class);
        $replacer->expects($this->once())->method('replace')->willReturn('<p>replaced</p>');

        $content = $this->createMock(Content::class);
        $content->expects($this->once())->method('findChildAttachments')->with('123')->willReturn([$attachment]);

        $download = $this->createMock(Download::class);
        $download->expects($this->once())->method('downloadPageContent')
            ->with($this->isInstanceOf(ConfluencePage::class), 'content.html');
        $download->expects($this->once())->method('downloadAttachment')->with($attachment, '123');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $downloader = new ConfluencePageContentDownloader($content, $download, [$replacer], $logger);
        $downloader->downloadPageContent($this->page('123'));
    }

    public function testDoesNotDownloadAttachmentsWhenDisabled(): void
    {
        $content = $this->createMock(Content::class);
        $content->expects($this->never())->method('findChildAttachments');

        $download = $this->createMock(Download::class);
        $download->expects($this->once())->method('downloadPageContent');
        $download->expects($this->never())->method('downloadAttachment');

        $downloader = new ConfluencePageContentDownloader($content, $download);
        $downloader->downloadPageContent($this->page('123'), false);
    }

    public function testDoesNotDownloadAttachmentsWhenPageHasNoId(): void
    {
        $content = $this->createMock(Content::class);
        $content->expects($this->never())->method('findChildAttachments');

        $download = $this->createMock(Download::class);
        $download->expects($this->once())->method('downloadPageContent');
        $download->expects($this->never())->method('downloadAttachment');

        $downloader = new ConfluencePageContentDownloader($content, $download);
        $downloader->downloadPageContent($this->page(null));
    }

    public function testLogsAnErrorWhenDownloadingFails(): void
    {
        // Stubs (no expectations): they only provide behaviour. The assertion lives on
        // the logger mock below.
        $download = self::createStub(Download::class);
        $download->method('downloadPageContent')->willThrowException(new RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with($this->stringContains('boom'), $this->arrayHasKey('exception'));

        $downloader = new ConfluencePageContentDownloader(self::createStub(Content::class), $download, [], $logger);

        // Must not bubble up — the failure is caught and logged.
        $downloader->downloadPageContent($this->page('123'));
    }

    private function page(?string $id): ConfluencePage
    {
        $rawData = ['body' => ['storage' => ['value' => '<p>original</p>']]];
        if ($id !== null) {
            $rawData['id'] = $id;
        }

        return new ConfluencePage($rawData);
    }
}
