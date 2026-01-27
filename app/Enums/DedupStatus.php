<?php

namespace App\Enums;

enum DedupStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Waiting = 'waiting';
    case Grouped = 'grouped';
    case Unique = 'unique';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Processing => 'blue',
            self::Waiting => 'amber',
            self::Grouped => 'purple',
            self::Unique => 'amber',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Cancelled => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Processing => 'arrow-path',
            self::Waiting => 'pause-circle',
            self::Grouped => 'squares-2x2',
            self::Unique => 'sparkles',
            self::Completed => 'check-circle',
            self::Failed => 'x-mark',
            self::Cancelled => 'no-symbol',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Waiting => 'Waiting',
            self::Grouped => 'In Group',
            self::Unique => 'Unique',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
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

    public function isUnique(): bool
    {
        return $this === self::Unique;
    }

    public function isWaiting(): bool
    {
        return $this === self::Waiting;
    }
}
