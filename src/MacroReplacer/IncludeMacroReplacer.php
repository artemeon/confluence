<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;

/**
 * Replace the "include" macro (Include Page) with the storage content of the included page.
 *
 * Must run first, so the other replacers also convert the included content (images, panels, …).
 * Only one level is resolved: include macros inside the included content are left to {@see OtherMacroRemover}.
 */
class IncludeMacroReplacer implements MacroReplacerInterface
{
    private const PATTERN = '/<ac:structured-macro\s+ac:name="include"[^>]*>(.*?)<\/ac:structured-macro>/is';

    /**
     * @var array<string, array{id: string, content: string}>
     */
    private array $pagesByTitle = [];

    /**
     * @param ConfluencePage[] $pages the pages that can be included, usually all pages of the space.
     *                                Their content is copied, so later changes to the pages do not leak into the output.
     */
    public function __construct(array $pages)
    {
        foreach ($pages as $page) {
            $id = $page->getId();
            $title = $page->getTitle();
            if ($id !== null && $title !== null) {
                $this->pagesByTitle[$title] = ['id' => $id, 'content' => $page->getContent()];
            }
        }
    }

    public function replace(string $haystack): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn (array $match): string => $this->findIncludedPage($match[1])['content'] ?? '',
            $haystack
        ) ?? $haystack;
    }

    /**
     * Returns the IDs of the pages included by the given content, e.g. to download their attachments as well.
     *
     * @return list<string>
     */
    public function findIncludedPageIds(string $haystack): array
    {
        preg_match_all(self::PATTERN, $haystack, $matches);

        $ids = [];
        foreach ($matches[1] as $macroBody) {
            $id = $this->findIncludedPage($macroBody)['id'] ?? null;
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @return array{id: string, content: string}|null
     */
    private function findIncludedPage(string $macroBody): ?array
    {
        // ri:space-key is ignored, the lookup only knows the given pages. Resolve it once cross-space includes are needed.
        if (!preg_match('/<ri:page\s[^>]*ri:content-title="([^"]*)"/i', $macroBody, $title)) {
            return null;
        }

        return $this->pagesByTitle[html_entity_decode($title[1], ENT_QUOTES | ENT_HTML5)] ?? null;
    }
}
