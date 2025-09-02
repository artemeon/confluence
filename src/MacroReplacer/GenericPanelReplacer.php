<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

/**
 * Replace <ac:structured-macro ac:name="panel"> (custom panels)
 * with <div class="panel panel-custom" data-bgcolor="..." data-icon="...">
 */
class GenericPanelReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        return preg_replace_callback(
            '/<ac:structured-macro\s+ac:name="panel"[^>]*>(.*?)<\/ac:structured-macro>/is',
            function ($match) {
                $content = $match[1];

                // Extract parameters
                preg_match('/<ac:parameter ac:name="bgColor">#(.*?)<\/ac:parameter>/is', $content, $bgMatch);
                preg_match('/<ac:parameter ac:name="panelIcon">:(.*?):<\/ac:parameter>/is', $content, $iconMatch);
                preg_match('/<ac:rich-text-body[^>]*>(.*?)<\/ac:rich-text-body>/is', $content, $bodyMatch);

                $bgColor = $bgMatch[1] ?? '';
                $icon = $iconMatch[1] ?? '';
                $body = $bodyMatch[1] ?? $content;

                return sprintf(
                    '<div class="confluence-panel-custom %s %s"><div>%s</div></div>',
                    htmlspecialchars($bgColor, ENT_QUOTES),
                    htmlspecialchars($icon, ENT_QUOTES),
                    $body
                );
            },
            $haystack
        );
    }
}
