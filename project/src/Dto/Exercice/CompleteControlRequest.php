<?php

declare(strict_types=1);

namespace App\Dto\Exercice;

use Symfony\Component\Validator\Constraints as Assert;

final class CompleteControlRequest
{
    #[Assert\Length(max: 2000)]
    public ?string $feedback = null;

    #[Assert\PositiveOrZero]
    public int $activeSeconds = 0;
}
