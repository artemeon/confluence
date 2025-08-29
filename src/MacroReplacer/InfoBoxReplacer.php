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
                $boxStyling = match ($type) {
                    'info' => $this->infoBoxStyling(),
                    'panel' => $this->warningBoxStyling(),
                    default => '%s',
                };

                if (preg_match('/<ac:rich-text-body[^>]*>(.*?)<\/ac:rich-text-body>/is', $content, $inner)) {
                    $content = $inner[1];
                }

                return sprintf(
                    $boxStyling,
                    $content
                );
            },
            $haystack
        );
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

    private function warningBoxStyling(): string
    {
        return '
        <div class="flex flex-row items-start bg-yellow-100 gap-2 px-4">
            <p>
                <agp-icon name="lightbulb" size="lg" type="duotone" class="mt-1" styles="--fa-primary-color:var(--color-gray-500);--fa-secondary-opacity:1;--fa-secondary-color:var(--color-yellow-700);"></agp-icon>
            </p>
            <div>%s</div>
        </div>
        ';
    }
}
