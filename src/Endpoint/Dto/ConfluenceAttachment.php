<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint\Dto;

use DateTime;

class ConfluenceAttachment
{
    private array $rawData;

    private string $title;

    private ?DateTime $lastUpdated;

    public function __construct(array $rawData)
    {
        $this->rawData = $rawData;

        $this->title = $rawData['title'];
        $this->lastUpdated = isset($rawData['history']['lastUpdated']['when']) ? new DateTime($rawData['history']['lastUpdated']['when']) : null;
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
