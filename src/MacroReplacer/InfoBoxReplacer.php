<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

class InfoBoxReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        $haystack = preg_replace_callback(
            '/<div class="(info|note|tip|warning)"[^>]*>\s*<ac:rich-text-body>(.*?)<\/ac:rich-text-body>\s*<\/div>/is',
            function ($matches) {
                $type = strtolower($matches[1]);
                $content = $matches[2];

                if (preg_match('/^\s*<p[^>]*>(.*?)<\/p>\s*$/is', $content, $inner)) {
                    $content = $inner[1];
                }

                return sprintf(
                    $this->infoBoxStyling(),
                    $content
                );
            },
            $haystack
        );

        return $haystack;
    }

    private function infoBoxStyling(): string
    {
        return '
        <div class="flex flex-row items-start bg-blue-100 gap-2 p-4">
            <agp-icon name="info-circle" size="lg" type="duotone" class="mt-1 [--fa-primary-color:var(--color-white)] [--fa-secondary-color:var(--color-primary-500)] [--fa-secondary-opacity:1]"></agp-icon>
            <div>%s</div>
        </div>
        ';
    }
}
