<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

/**
 * Replace <ac:adf-node type="panel"> with <div class="panel panel-{type}">
 */
class AdfPanelReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        $result = preg_replace_callback(
            '/<ac:adf-node\s+type="panel"[^>]*>(.*?)<\/ac:adf-node>/is',
            function ($match) {
                $content = $match[1];

                // Extract panel-type
                preg_match('/<ac:adf-attribute key="panel-type">(.*?)<\/ac:adf-attribute>/is', $content, $typeMatch);
                $panelType = strtolower($typeMatch[1] ?? 'custom');

                // Extract inner content
                preg_match('/<ac:adf-content[^>]*>(.*?)<\/ac:adf-content>/is', $content, $bodyMatch);
                $body = $bodyMatch[1] ?? $content;

                return sprintf('<div class="documentation-panel-%s"><div>%s</div></div>', $panelType, $body);
            },
            $haystack
        );

        $result = preg_replace(
            '/<ac:adf-extension[^>]*>(.*?)<\/ac:adf-extension>/is',
            '$1',
            $result
        );

        return $result;
    }
}
