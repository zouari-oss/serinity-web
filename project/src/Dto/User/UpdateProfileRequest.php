<?php

declare(strict_types=1);

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateProfileRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\Length(max: 255)]
    public ?string $username = null;

    #[Assert\Length(max: 255)]
    public ?string $firstName = null;

    #[Assert\Length(max: 255)]
    public ?string $lastName = null;

    #[Assert\Length(max: 100)]
    public ?string $country = null;

    #[Assert\Length(max: 100)]
    public ?string $state = null;

    #[Assert\Length(max: 500)]
    public ?string $aboutMe = null;

    public ?string $currentPassword = null;

    #[Assert\Length(min: 8, max: 255)]
    public ?string $newPassword = null;

    public ?string $confirmPassword = null;
}
