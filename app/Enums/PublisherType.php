<?php

namespace App\Enums;

enum PublisherType: string
{
    case Individual = 'individual';
    case Agency = 'agency';
    case Developer = 'developer';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Agency => 'Agency',
            self::Developer => 'Developer',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Individual => 'blue',
            self::Agency => 'purple',
            self::Developer => 'amber',
            self::Unknown => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Individual => 'user',
            self::Agency => 'building-office',
            self::Developer => 'building-office-2',
            self::Unknown => 'question-mark-circle',
        };
    }
}
