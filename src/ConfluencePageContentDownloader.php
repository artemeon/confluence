<?php

declare(strict_types=1);

namespace Artemeon\Confluence;

use Artemeon\Confluence\Endpoint\Content;
use Artemeon\Confluence\Endpoint\Download;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use Artemeon\Confluence\MacroReplacer\MacroReplacerInterface;
use DOMDocument;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ConfluencePageContentDownloader
{
    /**
     * @var MacroReplacerInterface[]
     */
    private array $macroReplacers;
    private Content $contentEndpoint;
    private Download $downloadEndpoint;
    private LoggerInterface $logger;

    /**
     * @param MacroReplacerInterface[] $macroReplacers
     */
    public function __construct(Content $contentEndpoint, Download $downloadEndpoint, array $macroReplacers = [], ?LoggerInterface $logger = null)
    {
        $this->macroReplacers = $macroReplacers;
        $this->contentEndpoint = $contentEndpoint;
        $this->downloadEndpoint = $downloadEndpoint;
        $this->logger = $logger ?? new NullLogger();
    }

    public function downloadPageContent(ConfluencePage $page, bool $withAttachments = true): void
    {
        try {
            foreach ($this->macroReplacers as $macroReplacer) {
                if ($macroReplacer instanceof MacroReplacerInterface) {
                    $page->setContent($macroReplacer->replace($page->getContent()));
                }
            }

            $page = $this->repairPageContent($page);

            $this->downloadEndpoint->downloadPageContent($page, 'content.html');

            if (!$withAttachments) {
                return;
            }

            $pageId = $page->getId();
            if ($pageId === null) {
                return;
            }

            $attachments = $this->contentEndpoint->findChildAttachments($pageId);
            foreach ($attachments as $attachment) {
                $this->downloadEndpoint->downloadAttachment($attachment, $pageId);
            }
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Failed to download Confluence page content for page "%s": %s', $page->getId() ?? 'unknown', $e->getMessage()),
                ['exception' => $e],
            );
        }
    }

    private function repairPageContent(ConfluencePage $page): ConfluencePage
    {
        $previousLibxmlState = libxml_use_internal_errors(true);

        $domDocument = new DOMDocument();
        $domDocument->loadHTML($page->getContent());
        if (!$domDocument->validate()) {
            $pageContent = '';
            foreach ($domDocument->getElementsByTagName('body')->item(0)->childNodes ?? [] as $child) {
                $pageContent .= $domDocument->saveHTML($child);
            }

            $page->setContent($pageContent);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        return $page;
    }
}
