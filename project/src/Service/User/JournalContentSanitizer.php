<?php

declare(strict_types=1);

namespace App\Service\User;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final readonly class JournalContentSanitizer
{
    public function __construct(
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    public function sanitize(?string $content): string
    {
        return trim($this->sanitizer->sanitize((string) $content));
    }
}

