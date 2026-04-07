<?php

declare(strict_types=1);

namespace App\Dto\Exercice;

use Symfony\Component\Validator\Constraints as Assert;

final class FavoriteToggleRequest
{
    #[Assert\Choice(choices: ['EXERCICE', 'RESOURCE'])]
    public string $favoriteType = 'EXERCICE';

    #[Assert\Positive]
    public int $itemId = 0;
}
