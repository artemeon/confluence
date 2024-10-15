<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

class HtmlCleaner implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        $haystack = $this->removeUnmatchedClosingTags($haystack);
        $haystack = $this->removeEmptyTags($haystack);
        $haystack = $this->selfClosingTags($haystack);
        return $this->fixMissingClosingTags($haystack);

        //return preg_replace('/<([a-zA-Z0-9]+)(\s*[^>]*)>\s*<\/\1>/', '', $haystack);
    }

    // 1. Entferne überflüssige schließende Tags, die keine passenden öffnenden Tags haben
    private function removeUnmatchedClosingTags(string $haystack): string
    {
        $openingTagPattern = '/<([a-zA-Z0-9]+)(\s*[^>]*)>/'; // Öffnende Tags
        preg_match_all($openingTagPattern, $haystack, $openingMatches);
        $openingTags = $openingMatches[1]; // Liste der öffnenden Tag-Namen

        return preg_replace_callback('/<\/([a-zA-Z0-9]+)>/', function ($matches) use ($openingTags) {
            $closingTag = $matches[1];
            if (in_array($closingTag, $openingTags)) {
                return $matches[0]; // Behalte passende schließende Tags
            } else {
                return ''; // Entferne überflüssige schließende Tags
            }
        }, $haystack);
    }

    // 2. Entferne leere HTML-Tags wie <li></li>, <p></p> usw.
    private function removeEmptyTags(string $haystack): string
    {
        return preg_replace('/<([a-zA-Z0-9]+)(\s*[^>]*)>\s*<\/\1>/', '', $haystack);
    }

    // 3. Formatiere selbstschließende Tags korrekt (wie <img>, <br/>)
    private function selfClosingTags(string $haystack)
    {
        return preg_replace('/<([a-zA-Z0-9]+)([^>]*)\/?>/', '<$1$2 />', $haystack);
    }

    // 4. Fehlende schließende Tags ergänzen für alle Tags
    private function fixMissingClosingTags(string $haystack): string
    {
        // Finde alle öffnenden und schließenden Tags
        preg_match_all('/<([a-zA-Z0-9]+)(\s*[^>]*)>/', $haystack, $openTags);
        preg_match_all('/<\/([a-zA-Z0-9]+)>/', $haystack, $closeTags);

        // Erstelle einen Stack, um die hierarchische Struktur von Tags zu verfolgen
        $stack = [];

        // Durchlaufe den Inhalt und füge fehlende schließende Tags hinzu
        $haystack = preg_replace_callback(
            '/<([a-zA-Z0-9]+)(\s*[^>]*)>|<\/([a-zA-Z0-9]+)>/',
            function ($matches) use (&$stack) {
                if (!empty($matches[1])) {
                    // Wenn es ein öffnendes Tag ist
                    array_push($stack, $matches[1]);
                    return $matches[0]; // Behalte das öffnende Tag
                } else {
                    // Wenn es ein schließendes Tag ist
                    $closingTag = $matches[3];
                    if (!empty($stack) && end($stack) === $closingTag) {
                        array_pop($stack); // Tag korrekt geschlossen
                        return $matches[0]; // Behalte das schließende Tag
                    } else {
                        // Entferne das schließende Tag, da es kein passendes öffnendes Tag hat
                        return '';
                    }
                }
            },
            $haystack
        );

        // Füge fehlende schließende Tags am Ende hinzu, falls noch welche im Stack sind
        while (!empty($stack)) {
            $tag = array_pop($stack);
            $haystack .= "</$tag>"; // Schließe das Tag
        }

        return $haystack;
    }
}