<?php

declare(strict_types=1);

namespace App\Dto\Mood;

use Symfony\Component\Validator\Constraints as Assert;

final class MoodCreateRequest
{
    #[Assert\Date]
    public ?string $entryDate = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['MOMENT', 'DAY'])]
    public string $momentType = 'MOMENT';

    #[Assert\Range(min: 1, max: 5)]
    public int $moodLevel = 3;

    /** @var list<string> */
    #[Assert\Count(min: 1, max: 5)]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\Length(max: 64),
    ])]
    public array $emotionKeys = [];

    /** @var list<string> */
    #[Assert\Count(min: 1, max: 5)]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\Length(max: 64),
    ])]
    public array $influenceKeys = [];

    #[Assert\Length(max: 1000)]
    public ?string $note = null;
}
