<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

interface MacroReplacerInterface
{
    public function replace(string $haystack) : string;
}