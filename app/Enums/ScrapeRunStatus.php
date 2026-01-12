<?php

namespace App\Enums;

enum ScrapeRunStatus: string
{
    case Pending = 'pending';
    case Discovering = 'discovering';
    case Scraping = 'scraping';
    case Completed = 'completed';
    case Failed = 'failed';
    case Stopped = 'stopped';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Discovering => 'blue',
            self::Scraping => 'amber',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Stopped => 'orange',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Discovering => 'magnifying-glass',
            self::Scraping => 'arrow-path',
            self::Completed => 'check',
            self::Failed => 'x-mark',
            self::Stopped => 'stop',
        };
    }

    public function iconClass(): string
    {
        return match ($this) {
            self::Pending => 'text-zinc-400',
            self::Discovering => 'text-blue-500 animate-pulse',
            self::Scraping => 'text-amber-500 animate-spin',
            self::Completed => 'text-green-500',
            self::Failed => 'text-red-500',
            self::Stopped => 'text-orange-500',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Discovering, self::Scraping]);
    }
}
