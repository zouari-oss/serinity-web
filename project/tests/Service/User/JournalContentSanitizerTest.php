<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Service\User\JournalContentSanitizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final class JournalContentSanitizerTest extends TestCase
{
    public function testSanitizeTrimsSanitizedContent(): void
    {
        $htmlSanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $htmlSanitizer
            ->expects(self::once())
            ->method('sanitize')
            ->with('<p>Hello mood journal</p>')
            ->willReturn('  Hello mood journal  ');

        $sanitizer = new JournalContentSanitizer($htmlSanitizer);

        self::assertSame('Hello mood journal', $sanitizer->sanitize('<p>Hello mood journal</p>'));
    }
}
