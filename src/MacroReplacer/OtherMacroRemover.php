<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

/**
 * Remove all remaining macros from the input
 */
class OtherMacroRemover implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        $haystack = preg_replace('/<ac:[^>]+>.*?<\/ac:[^>]+>/is', '', $haystack);
        return preg_replace('/<\/ac:([a-zA-Z0-9]+)>/', '', $haystack);
    }
}
