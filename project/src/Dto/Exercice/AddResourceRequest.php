<?php

declare(strict_types=1);

namespace App\Dto\Exercice;

use Symfony\Component\Validator\Constraints as Assert;

final class AddResourceRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    public string $title = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 40)]
    public string $resourceType = '';

    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 512)]
    public string $resourceUrl = '';
}
