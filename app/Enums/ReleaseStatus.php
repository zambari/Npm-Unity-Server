<?php

namespace App\Enums;

class ReleaseStatus
{
    const UNKNOWN = 0;
    const PUBLISHED = 1;
    const UNPUBLISHED = 2;
  
    /**
     * Get all status values
     */
    public static function all(): array
    {
        return [
            self::UNKNOWN => 'UNKNOWN',
            self::PUBLISHED => 'published',
            self::UNPUBLISHED => 'unpublished',
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

    /**
     * Get the first/default status value
     */
    public static function default(): int
    {
        return self::PUBLISHED;
    }

    /**
     * Get Bootstrap badge class for status
     */
    public static function badge(?int $status): string
    {
        if ($status === null) {
            return 'bg-secondary';
        }
        
        switch ($status) {
            case self::PUBLISHED:
                return 'bg-success';
            case self::UNPUBLISHED:
                return 'bg-warning';
            case self::UNKNOWN:
            default:
                return 'bg-secondary';
        }
    }
}

