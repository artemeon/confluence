<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint\Dto;

class ConfluenceAttachment
{
    private array $rawData;

    private string $title;

    public function __construct(array $rawData)
    {
        $this->rawData = $rawData;

        $this->title = $rawData['title'];
    }

    public function findDownloadPath(): ?string
    {
        return (string)$this->rawData['_links']['download'] ?? null;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}