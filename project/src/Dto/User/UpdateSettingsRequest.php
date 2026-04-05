<?php

declare(strict_types=1);

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateSettingsRequest
{
    #[Assert\Choice(choices: ['system', 'light', 'dark'])]
    public string $theme = 'system';

    public bool $notifications = true;

    public bool $compactView = false;
}
