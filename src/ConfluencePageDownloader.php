<?php

declare(strict_types=1);

namespace Artemeon\Confluence;

use Artemeon\Confluence\MacroReplacer\MacroReplacerInterface;
use Exception;
use GuzzleHttp\Client;

class ConfluencePageDownloader
{
    private string $confluenceUrl;

    private string $username;

    private string $password;

    private string $pageId;

    private string $downloadFolder;

    private array $macroReplacers;

    public function __construct(string $confluenceUrl, string $username, string $password, string $pageId, string $downloadFolder, array $macroReplacers = [])
    {
        $this->confluenceUrl = $confluenceUrl;
        $this->username = $username;
        $this->password = $password;
        $this->pageId = $pageId;
        $this->downloadFolder = $downloadFolder . '/' . $this->pageId;
        $this->macroReplacers = $macroReplacers;
    }

    public function downloadPageWithAttachments(): void
    {
        $client = new Client();

        if (!$this->checkDownloadFolder()) {
            echo 'Fehler: Der Download-Ordner existiert nicht oder konnte nicht erstellt werden.';

            return;
        }

        try {
            $pageData = $this->downloadPageContent($client);
            $htmlContent = $pageData['body']['storage']['value'];

            foreach ($this->macroReplacers as $macroReplacer) {
                if ($macroReplacer instanceof MacroReplacerInterface) {
                    $htmlContent = $macroReplacer->replace($htmlContent);
                }
            }

            $this->savePageContent($htmlContent);

            // Herunterladen von Attachments über "descendants.attachment"
            $attachments = $this->getDescendantAttachments($this->pageId, $client);
            foreach ($attachments as $attachment) {
                $this->downloadAttachment($client, $attachment);
            }

            echo 'Der Seiteninhalt und die Attachments wurden erfolgreich heruntergeladen und im Ordner "' . $this->downloadFolder . '" gespeichert.';
        } catch (Exception $e) {
            echo 'Ein Fehler ist aufgetreten: ' . $e->getMessage();
        }
    }

    private function downloadPageContent(Client $client): array
    {
        // Verwende die Confluence Content API, um den Seiteninhalt abzurufen
        $apiUrl = $this->confluenceUrl . '/wiki/rest/api/content/' . $this->pageId . '?expand=body.storage,version,space,metadata.labels';
        $response = $client->request('GET', $apiUrl, ['auth' => [$this->username, $this->password]]);

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        } else {
            throw new Exception('Fehler beim Abrufen des Seiteninhalts. HTTP-Statuscode: ' . $response->getStatusCode());
        }
    }

    private function getDescendantAttachments(string $pageId, Client $client): array
    {
        // Verwende "descendants.attachment" in der Content API, um Attachments zu erhalten
        $apiUrl = $this->confluenceUrl . '/wiki/rest/api/content/' . $pageId . '/child/attachment';
        $response = $client->request('GET', $apiUrl, ['auth' => [$this->username, $this->password]]);

        if ($response->getStatusCode() === 200) {
            $attachmentsData = json_decode($response->getBody()->getContents(), true);
            $attachments = $attachmentsData['results'];

            return $attachments;
        } else {
            throw new Exception('Fehler beim Abrufen der Attachments. HTTP-Statuscode: ' . $response->getStatusCode());
        }
    }

    private function checkDownloadFolder(): bool
    {
        if (!is_dir($this->downloadFolder)) {
            return mkdir($this->downloadFolder, 0755, true);
        }

        return true;
    }

    private function savePageContent(string $htmlContent): void
    {
        $htmlFile = $this->downloadFolder . '/page.html';
        file_put_contents($htmlFile, $htmlContent);
    }

    private function downloadAttachment(Client $client, array $attachment): void
    {
        // Verwende den relativen Pfad aus der API, um das Attachment herunterzuladen
        $attachmentPath = $this->confluenceUrl . '/wiki' . $attachment['_links']['download'];
        $attachmentContent = $client->request('GET', $attachmentPath, ['auth' => [$this->username, $this->password]])->getBody()->getContents();

        $attachmentFolder = $this->downloadFolder;

        if (!is_dir($attachmentFolder)) {
            mkdir($attachmentFolder, 0755, true);
        }

        $attachmentFilePath = $attachmentFolder . '/' . $attachment['title'];
        file_put_contents($attachmentFilePath, $attachmentContent);
    }
}