<?php

namespace App\Enums;

enum AiEnrichmentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Processing => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Processing => 'sparkles',
            self::Completed => 'check',
            self::Failed => 'x-mark',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Processing;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
