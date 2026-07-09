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

    /**
     * Normalises the page content by letting DOMDocument repair malformed markup and then
     * stripping the document wrapper that loadHTML() adds, keeping only the body inner HTML.
     *
     * We deliberately do not call DOMDocument::validate(): it would validate the content
     * against the HTML 4.0 Transitional DTD referenced in the doctype, which forces an
     * outbound HTTP request to w3.org on every page. In locked-down environments without
     * outbound internet access that request fails and PHP emits a warning. Validation adds
     * no value here anyway (Confluence storage format is never valid HTML 4.0), so removing
     * it also drops an unnecessary network dependency.
     */
    private function repairPageContent(ConfluencePage $page): ConfluencePage
    {
        $content = $page->getContent();
        if ($content === '') {
            return $page;
        }

        $previousLibxmlState = libxml_use_internal_errors(true);

        $domDocument = new DOMDocument();
        $domDocument->loadHTML($content, LIBXML_NONET);

        $body = $domDocument->getElementsByTagName('body')->item(0);
        if ($body !== null) {
            $pageContent = '';
            foreach ($body->childNodes as $child) {
                $pageContent .= $domDocument->saveHTML($child);
            }

            $page->setContent($pageContent);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        return $page;
    }
}
