<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

class InfoBoxReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        return preg_replace_callback(
            '/<div class="(info|panel)"[^>]*>(.*?)<\/div>/is',
            function ($matches) {
                $type = strtolower($matches[1]);
                $content = $matches[2];

                if (preg_match('/<ac:rich-text-body[^>]*>(.*?)<\/ac:rich-text-body>/is', $content, $inner)) {
                    $content = $inner[1];
                }

                return sprintf(
                    '
                    <div class="confluence-%s">
                        <div>%s</div>
                    </div>
                    ',
                    $type,
                    $content
                );
            },
            $haystack
        );
    }
}
