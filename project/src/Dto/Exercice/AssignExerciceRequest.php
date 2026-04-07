<?php

declare(strict_types=1);

namespace App\Dto\Exercice;

use Symfony\Component\Validator\Constraints as Assert;

final class AssignExerciceRequest
{
    #[Assert\NotBlank]
    public string $userId = '';

    #[Assert\Positive]
    public int $exerciceId = 0;
}
