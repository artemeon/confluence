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
                switch ($type) {
                    case 'panel':
                        $backgroundColor = 'bg-yellow-100';
                        $iconHtml = '<agp-icon name="lightbulb" size="xl" type="duotone" class="mt-1" styles="--fa-primary-color:var(--color-gray-500);--fa-secondary-opacity:1;--fa-secondary-color:var(--color-yellow-700);"></agp-icon>';
                        break;
                    case 'info':
                        $backgroundColor = 'bg-blue-100';
                        $iconHtml = '<agp-icon name="info-circle" size="xl" type="duotone" class="mt-1" styles="--fa-primary-color:var(--color-white);--fa-secondary-opacity:1;--fa-secondary-color:var(--color-blue-700);"></agp-icon>';
                        break;
                    default:
                        $backgroundColor = '';
                        $iconHtml = '';
                        break;
                }

                if (preg_match('/<ac:rich-text-body[^>]*>(.*?)<\/ac:rich-text-body>/is', $content, $inner)) {
                    $content = $inner[1];
                }

                return sprintf(
                    '
                    <div class="flex flex-row items-start %s gap-2 px-4 mb-4">
                        <p>%s</p>
                        <div>%s</div>
                    </div>
                    ',
                    $backgroundColor,
                    $iconHtml,
                    $content
                );
            },
            $haystack
        );
    }
}
