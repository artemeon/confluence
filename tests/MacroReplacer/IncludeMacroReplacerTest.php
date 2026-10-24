<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Tests\MacroReplacer;

use Artemeon\Confluence\Endpoint\Dto\ConfluencePage;
use Artemeon\Confluence\MacroReplacer\IncludeMacroReplacer;
use PHPUnit\Framework\TestCase;

/**
 * #29438: content included from another Confluence page via the "include" macro must be shown in full.
 */
final class IncludeMacroReplacerTest extends TestCase
{
    private const INCLUDE_PRODUCT_GROUPS = '<ac:structured-macro ac:name="include" ac:schema-version="1" data-layout="default"><ac:parameter ac:name=""><ac:link><ri:page ri:content-title="Use Case: Waren- &amp; Produktgruppen" ri:version-at-save="7" /></ac:link></ac:parameter></ac:structured-macro>';
    private const INCLUDE_UNITS = '<ac:structured-macro ac:name="include" ac:schema-version="1"><ac:parameter ac:name=""><ac:link><ri:page ri:space-key="DOK" ri:content-title="Organisationseinheiten" /></ac:link></ac:parameter></ac:structured-macro>';
    private const INCLUDE_MISSING = '<ac:structured-macro ac:name="include" ac:schema-version="1"><ac:parameter ac:name=""><ac:link><ri:page ri:content-title="Missing" /></ac:link></ac:parameter></ac:structured-macro>';

    public function testReplacesIncludesWithTheIncludedPageContent(): void
    {
        $replacer = $this->replacer();

        self::assertSame(
            '<h1>Contracts</h1><p>groups</p><ac:image><ri:attachment ri:filename="groups.png" /></ac:image><p>units</p><p>end</p>',
            $replacer->replace('<h1>Contracts</h1>' . self::INCLUDE_PRODUCT_GROUPS . self::INCLUDE_UNITS . self::INCLUDE_MISSING . '<p>end</p>'),
        );
    }

    public function testFindsTheIdsOfResolvableIncludedPages(): void
    {
        self::assertSame(
            ['1', '2'],
            $this->replacer()->findIncludedPageIds(self::INCLUDE_PRODUCT_GROUPS . self::INCLUDE_MISSING . self::INCLUDE_UNITS . self::INCLUDE_PRODUCT_GROUPS),
        );
    }

    public function testUsesTheContentAsOfConstruction(): void
    {
        $page = $this->page('2', 'Organisationseinheiten', '<p>units</p>');
        $replacer = new IncludeMacroReplacer([$page]);
        $page->setContent('<p>already converted</p>');

        self::assertSame('<p>units</p>', $replacer->replace(self::INCLUDE_UNITS));
    }

    private function replacer(): IncludeMacroReplacer
    {
        return new IncludeMacroReplacer([
            $this->page('1', 'Use Case: Waren- & Produktgruppen', '<p>groups</p><ac:image><ri:attachment ri:filename="groups.png" /></ac:image>'),
            $this->page('2', 'Organisationseinheiten', '<p>units</p>'),
        ]);
    }

    private function page(string $id, string $title, string $content): ConfluencePage
    {
        return new ConfluencePage(['id' => $id, 'title' => $title, 'body' => ['storage' => ['value' => $content]]]);
    }
}
