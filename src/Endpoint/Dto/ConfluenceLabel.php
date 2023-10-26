<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint\Dto;

class ConfluenceLabel
{
    private string $id;
    private string $name;
    private string $prefix;
    private string $label;

    public function __construct(string $id, string $name, string $prefix, string $label)
    {
        $this->id = $id;
        $this->name = $name;
        $this->prefix = $prefix;
        $this->label = $label;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}