<?php
declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint;

use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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
     * Use the Confluence Content API to retrieve all pages from a given space
     *
     * @param  string  $spaceKey
     * @param  int  $limit
     * @param  int|null  $offset
     * @return ConfluencePage[]
     * @throws GuzzleException
     */
    public function findPagesInSpace(string $spaceKey, int $limit = 2000, ?int $offset = null): array
    {
        $foundEntries = 0;
        $pages = [];

        do {
            $response = $this->client->get(
                'wiki/rest/api/content',
                array_merge([
                    'query' => array_filter([
                        'spaceKey' => $spaceKey,
                        'expand' => 'history.lastUpdated,metadata.labels,body.storage',
                        'status' => 'current',
                        'start' => $offset,
                        'limit' => 200,
                    ]),
                ], $this->auth->getAuthenticationArray())
            );

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Error retrieving pages by Space Key. HTTP status code: '.$response->getStatusCode());
            }
            $content = json_decode($response->getBody()->getContents(), true);

            foreach ($content['results'] ?? [] as $pageData) {
                $pages[$pageData['id']] = new ConfluencePage($pageData);
            }

            $offset += $content['limit'];
            $foundEntries += $content['size'];
        } while ($foundEntries <= $limit && $content['size'] >= $content['limit']);

        return $pages;
    }

    /**
     * Use the Confluence Content API to retrieve page content
     */
    public function findPageContent(string $pageId): ConfluencePage
    {
        $response = $this->client->get(
            'wiki/rest/api/content/' . $pageId,
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
            'wiki/rest/api/content/' . $pageId . '/child/attachment',
            array_merge([
                'query' => [
                    'expand' => 'history,history.lastUpdated'
                ]
            ], $this->auth->getAuthenticationArray())
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
