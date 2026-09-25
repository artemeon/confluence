<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

/**
 * Replace <ac:link> with its link text, dropping the link itself.
 *
 * Must run before {@see OtherMacroRemover}, which would otherwise remove the link together with its text.
 */
class LinkMacroReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        return preg_replace_callback(
            '/<ac:link\b[^>]*>(.*?)<\/ac:link>/is',
            function ($match) {
                $linkContent = $match[1];

                if (preg_match('/<ac:link-body[^>]*>(.*?)<\/ac:link-body>/is', $linkContent, $body)) {
                    return $body[1];
                }

                if (preg_match('/<ac:plain-text-link-body[^>]*>\s*<!\[CDATA\[(.*?)]]>\s*<\/ac:plain-text-link-body>/is', $linkContent, $body)) {
                    return htmlspecialchars($body[1], ENT_QUOTES | ENT_HTML5);
                }

                // Without a link body, Confluence displays the title of the linked page or attachment.
                if (preg_match('/ri:(?:content-title|filename)="([^"]*)"/i', $linkContent, $title)) {
                    return $title[1];
                }

                return '';
            },
            $haystack
        ) ?? $haystack;
    }
}
