<?php

declare(strict_types=1);

namespace Artemeon\Confluence;

use Artemeon\Confluence\Endpoint\Content;
use Artemeon\Confluence\Endpoint\Download;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use Artemeon\Confluence\MacroReplacer\MacroReplacerInterface;
use Exception;

class ConfluencePageContentDownloader
{
    private array $macroReplacers;
    private Content $contentEndpoint;
    private Download $downloadEndpoint;

    public function __construct(Content $contentEndpoint, Download $downloadEndpoint, array $macroReplacers = [])
    {
        $this->macroReplacers = $macroReplacers;
        $this->contentEndpoint = $contentEndpoint;
        $this->downloadEndpoint = $downloadEndpoint;
    }

    public function downloadPageContent(ConfluencePage $page): void
    {
        try {
            foreach ($this->macroReplacers as $macroReplacer) {
                if ($macroReplacer instanceof MacroReplacerInterface) {
                    $page->setContent($macroReplacer->replace($page->getContent()));
                }
            }

            $this->downloadEndpoint->downloadPageContent($page, 'content.html');

            $attachments = $this->contentEndpoint->findChildAttachments($page->getId());

            foreach ($attachments as $attachment) {
                $this->downloadEndpoint->downloadAttachment($attachment);
            }

        } catch (Exception $e) {
            echo 'An error has occurred: ' . $e->getMessage();
        }
    }

}
