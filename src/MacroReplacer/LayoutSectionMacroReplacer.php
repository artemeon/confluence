<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

class LayoutSectionMacroReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        $haystack = preg_replace_callback(
            '/<ac:layout-section\s+ac:type="([^"]+)"[^>]*>(.*?)<\/ac:layout-section>/is',
            function ($match) {
                $macroType = $match[1];
                $macroContent = $match[2];
                return '<div data-macro-type="'.$macroType.'">'.$macroContent.'</div>';
            },
            $haystack
        );


        return preg_replace_callback(
            '/<ac:layout-cell>(.*?)<\/ac:layout-cell>/is',
            function ($match) {
                $macroContent = $match[1];
                return '<div>'.$macroContent.'</div>';
            },
            $haystack
        );
    }
}
