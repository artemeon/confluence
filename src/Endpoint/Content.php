<?php
declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint;

use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use Exception;
use GuzzleHttp\Client;

class Content
{
    private Client $client;
    private Auth $auth;

    public function __construct(Client $client, Auth $auth)
    {
        $this->client = $client;
        $this->auth = $auth;
    }

    /**
     * Use the Confluence Content API to retrieve page content
     */
    public function findPageContent(string $pageId): ConfluencePage
    {
        // Use the Confluence Content API to retrieve page content
        $response = $this->client->get(
            'content/' . $pageId,
            array_merge([
                'query' => [
                    'expand' => 'body.storage,version,space,metadata.labels'
                ]
            ], $this->auth->getAuthenticationArray())
        );

        if ($response->getStatusCode() === 200) {
            $content = json_decode($response->getBody()->getContents(), true);
        } else {
            throw new Exception('Error retrieving page content. HTTP status code: ' . $response->getStatusCode());
        }

        return new ConfluencePage($content);
    }

    /**
     * Use descendants.attachment in the Content API to get attachments
     */
    public function findChildAttachments(string $pageId): array
    {
        $response = $this->client->get(
            'content/' . $pageId . '/child/attachment',
            array_merge([], $this->auth->getAuthenticationArray())
        );

        if ($response->getStatusCode() === 200) {
            $attachmentsData = json_decode($response->getBody()->getContents(), true);
            $attachments = [];

            foreach ($attachmentsData['results'] as $attachmentRawData) {
                $attachments[] = new ConfluenceAttachment($attachmentRawData);
            }

            return $attachments;
        } else {
            throw new Exception('Fehler beim Abrufen der Attachments. HTTP-Statuscode: ' . $response->getStatusCode());
        }
    }
}