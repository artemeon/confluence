<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint;

use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use DateTime;
use GuzzleHttp\Client;
use RuntimeException;

class Download
{
    private Client $client;
    private Auth $auth;
    private string $downloadFolder;

    public function __construct(Client $client, Auth $auth, string $downloadFolder)
    {
        $this->client = $client;
        $this->auth = $auth;
        $this->downloadFolder = $downloadFolder;
    }

    private function ensureDownloadFolder(): void
    {
        if (!is_dir($this->downloadFolder) && !mkdir($this->downloadFolder, 0755, true) && !is_dir($this->downloadFolder)) {
            throw new RuntimeException(sprintf('The download folder "%s" does not exist and could not be created.', $this->downloadFolder));
        }
    }

    public function downloadPageContent(ConfluencePage $confluencePage, string $fileName): void
    {
        $this->ensureDownloadFolder();

        $htmlFile = $this->downloadFolder . '/' . $fileName;
        file_put_contents($htmlFile, $confluencePage->getContent());
    }

    public function downloadAttachment(ConfluenceAttachment $attachment, string $pageId): void
    {
        $this->ensureDownloadFolder();

        if ($this->shouldAttachmentBeUpdated($attachment)) {
            // Download via the supported REST endpoint. Same Basic-auth credentials
            // (email + API token) as every other request; only the endpoint changed:
            // the legacy /wiki/download servlet rejects API-token auth (HTTP 401),
            // while the REST API accepts it.
            $attachmentContent = $this->client->get(
                'wiki/rest/api/content/' . $pageId . '/child/attachment/' . $attachment->getId() . '/download',
                array_merge([], $this->auth->getAuthenticationArray())
            )->getBody()->getContents();

            file_put_contents($this->getAttachmentFilePath($attachment), $attachmentContent);
        }
    }

    private function getAttachmentFilePath(ConfluenceAttachment $attachment): string
    {
        return $this->downloadFolder . '/' . $attachment->getTitle();
    }

    private function shouldAttachmentBeUpdated(ConfluenceAttachment $attachment): bool
    {
        $filepath = $this->getAttachmentFilePath($attachment);

        $lastUpdated = $attachment->getLastUpdated();
        if (!$lastUpdated instanceof DateTime) {
            return true;
        }

        if (file_exists($filepath)) {
            $filemtime = filemtime($filepath);
            if (is_int($filemtime)) {
                return $filemtime < $lastUpdated->getTimestamp();
            }
        }

        return true;
    }
}
