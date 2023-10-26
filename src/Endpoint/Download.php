<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint;

use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use GuzzleHttp\Client;

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

    private function checkDownloadFolder(): bool
    {
        if (!is_dir($this->downloadFolder)) {
            return mkdir($this->downloadFolder, 0755, true);
        }

        return true;
    }

    public function downloadPageContent(ConfluencePage $confluencePage, string $fileName)
    {
        $htmlFile = $this->downloadFolder . '/' . $fileName;
        file_put_contents($htmlFile, $confluencePage->getContent());
    }

    public function downloadAttachment(ConfluenceAttachment $attachment): void
    {
        if (!$this->checkDownloadFolder()) {
            echo 'Error: The download folder does not exist or could not be created.';

            return;
        }

        // Verwende den relativen Pfad aus der API, um das Attachment herunterzuladen
        $attachmentContent = $this->client->get(
            '/wiki/' . $attachment->findDownloadPath(),
            array_merge([], $this->auth->getAuthenticationArray())
        )->getBody()->getContents();

        file_put_contents($this->downloadFolder . '/' . $attachment->getTitle(), $attachmentContent);
    }
}