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
        return preg_replace('/<ac:[^>]*>.*?<\/ac:[^>]*>/is', '', $haystack);
    }
}