<?php

namespace App\Enums;

class PackageStatus
{
    const PUBLISHED = 0;
    const REVOKED = 2;

    /**
     * Get all status values
     */
    public static function all(): array
    {
        return [
            self::PUBLISHED => 'published',
            self::REVOKED => 'revoked',
        ];
    }

    /**
     * Get status label by value
     */
    public static function label(int $value): string
    {
        return self::all()[$value] ?? 'unknown';
    }

    /**
     * Get status value by label
     */
    public static function value(string $label): ?int
    {
        $flipped = array_flip(self::all());
        return $flipped[$label] ?? null;
    }

    /**
     * Check if a status value is valid
     */
    public static function isValid(int $value): bool
    {
        return array_key_exists($value, self::all());
    }
}

