<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Tests\Endpoint\Dto;

use Artemeon\Confluence\Endpoint\Dto\ConfluenceAttachment;
use DateTime;
use PHPUnit\Framework\TestCase;

final class ConfluenceAttachmentTest extends TestCase
{
    public function testExposesIdAndTitleFromRawData(): void
    {
        $attachment = new ConfluenceAttachment(['id' => 'att123', 'title' => 'diagram.png']);

        self::assertSame('att123', $attachment->getId());
        self::assertSame('diagram.png', $attachment->getTitle());
    }

    public function testLastUpdatedIsNullWhenHistoryIsMissing(): void
    {
        $attachment = new ConfluenceAttachment(['id' => 'att123', 'title' => 'diagram.png']);

        self::assertNull($attachment->getLastUpdated());
    }

    public function testLastUpdatedIsParsedFromHistoryWhenPresent(): void
    {
        $attachment = new ConfluenceAttachment([
            'id' => 'att123',
            'title' => 'diagram.png',
            'history' => ['lastUpdated' => ['when' => '2021-06-15T10:30:00.000Z']],
        ]);

        $lastUpdated = $attachment->getLastUpdated();

        self::assertInstanceOf(DateTime::class, $lastUpdated);
        self::assertSame('2021-06-15', $lastUpdated->format('Y-m-d'));
    }
}
