<?php

declare(strict_types=1);

namespace App\Service\User;

final class FatigueResolver
{
    public const LOW = 'low';
    public const MODERATE = 'moderate';
    public const HIGH = 'high';

    /**
     * @return list<array{value:string,label:string}>
     */
    public function options(): array
    {
        return [
            ['value' => self::LOW, 'label' => 'Low'],
            ['value' => self::MODERATE, 'label' => 'Moderate'],
            ['value' => self::HIGH, 'label' => 'High'],
        ];
    }

    public function resolve(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            self::LOW, self::HIGH => $normalized,
            default => self::MODERATE,
        };
    }

    public function label(string $value): string
    {
        return match ($this->resolve($value)) {
            self::LOW => 'Low',
            self::HIGH => 'High',
            default => 'Moderate',
        };
    }
}
