<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

/**
 * Entferne alle übrigen Macros aus dem Input
 */
class OtherMacroRemover implements MacroReplacerInterface
{

    public function replace(string $haystack): string
    {
        return preg_replace('/<ac:[^>]*>.*?<\/ac:[^>]*>/is', '', $haystack);
    }
}