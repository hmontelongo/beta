<?php

namespace App\Enums;

enum ScrapeJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Running => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Running => 'arrow-path',
            self::Completed => 'check',
            self::Failed => 'x-mark',
        };
    }

    public function iconClass(): string
    {
        return match ($this) {
            self::Pending => 'text-zinc-400',
            self::Running => 'text-blue-500 animate-spin',
            self::Completed => 'text-green-500',
            self::Failed => 'text-red-500',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Running;
    }
}
