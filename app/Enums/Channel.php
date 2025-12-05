<?php

namespace App\Enums;

class Channel
{
    const PUBLIC = 'public';
    const BETA = 'beta';

    /**
     * Get all channel values
     */
    public static function all(): array
    {
        return [
            self::PUBLIC,
            self::BETA,
        ];
    }

    /**
     * Check if a channel value is valid
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::all(), true);
    }
}

