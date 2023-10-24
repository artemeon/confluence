<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

/**
 * Ersetze <ac:structured-macro> durch <div> mit Klasse
 */
class StructuredMacroReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        return preg_replace_callback(
            '/<ac:structured-macro\s+ac:name="([^"]+)"[^>]*>(.*?)<\/ac:structured-macro>/is',
            function ($match) {
                $macroName = $match[1];
                $macroContent = $match[2];
                return '<div class="' . $macroName . '">' . $macroContent . '</div>';
            },
            $haystack
        );
    }
}