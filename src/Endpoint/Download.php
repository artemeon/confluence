<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint;

use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

    /**
     * Makes sure the configured download folder exists, creating it recursively if needed.
     *
     * @throws RuntimeException if the folder is missing and cannot be created
     */
    private function ensureDownloadFolder(): void
    {
        if (!is_dir($this->downloadFolder) && !mkdir($this->downloadFolder, 0755, true) && !is_dir($this->downloadFolder)) {
            throw new RuntimeException(sprintf('The download folder "%s" does not exist and could not be created.', $this->downloadFolder));
        }
    }

    /**
     * Writes a page's (already prepared) HTML content to a file in the download folder.
     *
     * @param ConfluencePage $confluencePage the page whose content is written
     * @param string $fileName the target file name, relative to the download folder (e.g. "content.html")
     *
     * @throws RuntimeException if the download folder cannot be ensured
     */
    public function downloadPageContent(ConfluencePage $confluencePage, string $fileName): void
    {
        $this->ensureDownloadFolder();

        $htmlFile = $this->downloadFolder . '/' . $fileName;
        file_put_contents($htmlFile, $confluencePage->getContent());
    }

    /**
     * Downloads a single page attachment into the download folder, but only if it is
     * new or has been updated since the locally stored copy (see {@see shouldAttachmentBeUpdated()}).
     *
     * @param ConfluenceAttachment $attachment the attachment to download; its title is used as the file name
     * @param string $pageId the Confluence content ID of the page the attachment belongs to
     *
     * @throws RuntimeException if the download folder cannot be ensured
     * @throws GuzzleException if the HTTP request to the REST endpoint fails
     */
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

    /**
     * Decides whether an attachment needs to be (re-)downloaded.
     *
     * Returns true when no local copy exists yet, when the attachment has no known
     * last-updated date, or when the local file is older than the attachment's
     * last-updated date; otherwise the local copy is considered up to date.
     *
     * @param ConfluenceAttachment $attachment the attachment to check against its local copy
     *
     * @return bool true if the attachment should be downloaded, false if the local copy is current
     */
    private function shouldAttachmentBeUpdated(ConfluenceAttachment $attachment): bool
    {
        $filepath = $this->getAttachmentFilePath($attachment);

        $lastUpdated = $attachment->getLastUpdated();
        if (!$lastUpdated instanceof DateTime) {
            return true;
        }

        if (file_exists($filepath)) {
            $fileModificationTime = filemtime($filepath);
            if (is_int($fileModificationTime)) {
                return $fileModificationTime < $lastUpdated->getTimestamp();
            }
        }

        return true;
    }
}
