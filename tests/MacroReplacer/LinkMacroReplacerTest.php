<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Tests\MacroReplacer;

use Artemeon\Confluence\MacroReplacer\LinkMacroReplacer;
use Artemeon\Confluence\MacroReplacer\OtherMacroRemover;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for #35420: links to other Confluence pages must keep their text,
 * instead of being removed entirely by {@see OtherMacroRemover}.
 */
final class LinkMacroReplacerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function links(): array
    {
        return [
            'plain text link body' => [
                '<p>See <ac:link><ri:page ri:content-title="Contracts" /><ac:plain-text-link-body><![CDATA[contracts & more]]></ac:plain-text-link-body></ac:link> here</p>',
                '<p>See contracts &amp; more here</p>',
            ],
            'rich text link body' => [
                '<p>See <ac:link ac:anchor="top"><ri:page ri:content-title="Contracts" /><ac:link-body><strong>Contracts</strong></ac:link-body></ac:link> here</p>',
                '<p>See <strong>Contracts</strong> here</p>',
            ],
            'page link without body' => [
                '<p>See <ac:link><ri:page ri:content-title="Contracts" /></ac:link> here</p>',
                '<p>See Contracts here</p>',
            ],
            'attachment link without body' => [
                '<p>See <ac:link><ri:attachment ri:filename="manual.pdf" /></ac:link> here</p>',
                '<p>See manual.pdf here</p>',
            ],
            'user link without body' => [
                '<p>Ask <ac:link><ri:user ri:account-id="123" /></ac:link> here</p>',
                '<p>Ask  here</p>',
            ],
            'multiple links' => [
                '<ac:link><ri:page ri:content-title="A" /></ac:link>, <ac:link><ri:page ri:content-title="B" /></ac:link>',
                'A, B',
            ],
        ];
    }

    #[DataProvider('links')]
    public function testReplacesLinkWithItsText(string $input, string $expected): void
    {
        $this->assertSame($expected, (new LinkMacroReplacer())->replace($input));
    }

    public function testLinkTextSurvivesOtherMacroRemover(): void
    {
        $input = '<p>See <ac:link><ri:page ri:content-title="Contracts" /><ac:plain-text-link-body><![CDATA[Contracts]]></ac:plain-text-link-body></ac:link> here</p>';

        $output = (new OtherMacroRemover())->replace((new LinkMacroReplacer())->replace($input));

        $this->assertSame('<p>See Contracts here</p>', $output);
    }
}
