<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

/**
 * Replace <ac:structured-macro> with <div> with class
 */
class StructuredMacroReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        return preg_replace_callback(
            '/<ac:structured-macro\s+ac:name="(info|note|warning|error|success)"[^>]*>(.*?)<\/ac:structured-macro>/is',
            function ($match) {
                $macroName = $match[1];
                $macroContent = $match[2];

                if (preg_match('/<ac:rich-text-body[^>]*>(.*?)<\/ac:rich-text-body>/is', $macroContent, $inner)) {
                    $macroContent = $inner[1];
                }

                return sprintf('<div class="confluence-panel-%s"><div>%s</div></div>', $macroName, $macroContent);
            },
            $haystack
        );
    }
}
