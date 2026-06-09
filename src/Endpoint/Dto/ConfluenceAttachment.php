<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint\Dto;

use DateTime;

class ConfluenceAttachment
{
    private string $title;

    private ?DateTime $lastUpdated;

    /**
     * @param array{
     *     id: string,
     *     title: string,
     *     history?: array{
     *         lastUpdated?: array{
     *             when?: string|null,
     *         },
     *     },
     * } $rawData
     */
    public function __construct(private array $rawData)
    {
        $this->title = $rawData['title'];
        $this->lastUpdated = isset($rawData['history']['lastUpdated']['when']) ? new DateTime($rawData['history']['lastUpdated']['when']) : null;
    }

    public function getId(): string
    {
        return $this->rawData['id'];
    }

    public function findDownloadPath(): ?string
    {
        return $this->rawData['_links']['download'] ?? null;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLastUpdated(): ?DateTime
    {
        return $this->lastUpdated;
    }
}
