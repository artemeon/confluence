<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint\Dto;

use DateTime;

class ConfluencePage
{
    private ?string $id;
    private ?string $type;
    private ?string $status;
    private ?string $title;
    private ?array $space;
    private ?array $version;
    private ?array $body;
    private ?array $metadata;
    private ?DateTime $lastUpdated;

    private array $rawData;
    private string $content;

    public function __construct(array $rawData)
    {
        $this->rawData = $rawData;

        $this->id = $rawData['id'] ?? null;
        $this->type = $rawData['type'] ?? null;
        $this->status = $rawData['status'] ?? null;
        $this->title = $rawData['title'] ?? null;
        $this->space = $rawData['space'] ?? null;
        $this->version = $rawData['version'] ?? null;
        $this->body = $rawData['body'] ?? null;
        $this->metadata = $rawData['metadata'] ?? null;
        $this->lastUpdated = isset($rawData['history']['lastUpdated']['when']) ? new DateTime($rawData['history']['lastUpdated']['when']) : null;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getSpace(): ?array
    {
        return $this->space;
    }

    public function getVersion(): ?array
    {
        return $this->version;
    }

    public function getBody(): ?array
    {
        return $this->body;
    }

    public function getContent(): string
    {
        if (empty($this->content)) {
            $this->content = $this->getBody()['storage']['value'] ?? '';
        }

        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getLabels(): array {
        $labels = [];

        foreach ($this->getMetadata()['labels']['results'] as $labelData) {
            $labels[] = new ConfluenceLabel($labelData['id'], $labelData['name'], $labelData['prefix'], $labelData['label']);
        }

        return $labels;
    }

    public function getLastUpdated(): ?DateTime
    {
        return $this->lastUpdated;
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }
}
