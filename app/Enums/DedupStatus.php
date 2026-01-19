<?php

namespace App\Enums;

enum DedupStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Grouped = 'grouped';
    case Completed = 'completed';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Processing => 'blue',
            self::Grouped => 'purple',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Processing => 'arrow-path',
            self::Grouped => 'squares-2x2',
            self::Completed => 'check-circle',
            self::Failed => 'x-mark',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Grouped => 'In Group',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Processing;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }
}
