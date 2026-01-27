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
    /**
     * @var array<array-key,mixed>|null
     */
    private ?array $space;
    /**
     * @var array<array-key,mixed>|null
     */
    private ?array $version;
    /**
     * @var array<array-key,mixed>|null
     */
    private ?array $body;
    /**
     * @var array<array-key,mixed>|null
     */
    private ?array $metadata;
    private ?DateTime $lastUpdated;
    private string $content;

    /**
     * @param array<array-key,mixed> $rawData
     */
    public function __construct(private array $rawData)
    {
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

    /**
     * @return array<array-key,mixed>|null
     */
    public function getSpace(): ?array
    {
        return $this->space;
    }

    /**
     * @return array<array-key,mixed>|null
     */
    public function getVersion(): ?array
    {
        return $this->version;
    }

    /**
     * @return array<array-key,mixed>|null
     */
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

    /**
     * @return array<array-key,mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @return list<ConfluenceLabel>
     */
    public function getLabels(): array
    {
        $metadata = $this->getMetadata();

        if ($metadata === null || !array_key_exists('labels', $metadata)) {
            return [];
        }

        $labels = [];

        foreach ($metadata['labels']['results'] as $labelData) {
            $labels[] = new ConfluenceLabel($labelData['id'], $labelData['name'], $labelData['prefix'], $labelData['label']);
        }

        return $labels;
    }

    public function getLastUpdated(): ?DateTime
    {
        return $this->lastUpdated;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }
}
