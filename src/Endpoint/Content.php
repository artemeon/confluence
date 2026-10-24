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
     * @throws Exception
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
                        'expand' => 'history.lastUpdated,metadata.labels,body.storage,ancestors,extensions.position',
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

        return $this->sortByPageTree($pages);
    }

    /**
     * Orders the pages like the Confluence page tree: depth-first, siblings by manual
     * position, unpositioned siblings by title. Pages whose parent is unknown become roots.
     *
     * @param array<array-key, ConfluencePage> $pages
     * @return array<array-key, ConfluencePage>
     */
    private function sortByPageTree(array $pages): array
    {
        $children = [];
        foreach ($pages as $page) {
            $parentId = $page->getParentId() ?? '';
            $children[isset($pages[$parentId]) ? $parentId : ''][] = $page;
        }

        $sorted = [];
        $this->appendSubtree('', $children, $sorted);

        return $sorted;
    }

    /**
     * @param array<array-key, list<ConfluencePage>> $children
     * @param array<array-key, ConfluencePage> $sorted
     */
    private function appendSubtree(string $parentId, array $children, array &$sorted): void
    {
        $siblings = $children[$parentId] ?? [];
        usort($siblings, static fn (ConfluencePage $a, ConfluencePage $b): int =>
            [$a->getPosition() ?? PHP_INT_MAX, $a->getTitle()] <=> [$b->getPosition() ?? PHP_INT_MAX, $b->getTitle()]);

        foreach ($siblings as $page) {
            $pageId = (string) $page->getId();
            $sorted[$pageId] = $page;
            $this->appendSubtree($pageId, $children, $sorted);
        }
    }

    /**
     * Fetches a single page by its ID from the Confluence Content API, expanding its
     * stored body, version, space and labels into a {@see ConfluencePage}.
     *
     * @param string $pageId the Confluence content ID of the page to load
     *
     * @throws GuzzleException if the HTTP request fails (network error, timeout, …)
     * @throws Exception if Confluence responds with a non-200 status code
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
     * Lists the attachments of a page via the Content API's child/attachment endpoint,
     * expanding each attachment's history so its last-updated timestamp is available.
     *
     * @param string $pageId the Confluence content ID of the parent page
     *
     * @return list<ConfluenceAttachment> the page's attachments, empty if it has none
     *
     * @throws GuzzleException if the HTTP request fails (network error, timeout, …)
     * @throws Exception if Confluence responds with a non-200 status code
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

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Error retrieving attachments. HTTP status code: ' . $response->getStatusCode());
        }

        $attachmentsData = json_decode($response->getBody()->getContents(), true);
        $attachments = [];

        foreach ($attachmentsData['results'] as $attachmentRawData) {
            $attachments[] = new ConfluenceAttachment($attachmentRawData);
        }

        return $attachments;
    }
}
