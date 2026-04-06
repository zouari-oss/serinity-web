<?php

declare(strict_types=1);

namespace App\Dto\Mood;

use Symfony\Component\Validator\Constraints as Assert;

final class MoodHistoryFilterRequest
{
    #[Assert\Length(max: 100)]
    public ?string $search = null;

    #[Assert\Choice(choices: ['MOMENT', 'DAY'])]
    public ?string $momentType = null;

    #[Assert\Date]
    public ?string $fromDate = null;

    #[Assert\Date]
    public ?string $toDate = null;

    #[Assert\Range(min: 1, max: 5)]
    public ?int $level = null;

    #[Assert\Positive]
    public int $page = 1;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 20;
}
