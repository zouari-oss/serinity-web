<?php

declare(strict_types=1);

namespace App\Dto\Mood;

use Symfony\Component\Validator\Constraints as Assert;

final class MoodSummaryRequest
{
    #[Assert\Range(min: 1, max: 90)]
    public int $days = 7;

    #[Assert\Date]
    public ?string $fromDate = null;

    #[Assert\Date]
    public ?string $toDate = null;
}
