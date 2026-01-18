<?php

namespace App\Enums;

enum RunFrequency: string
{
    case None = 'none';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Manual Only',
            self::Hourly => 'Every Hour',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    public function intervalMinutes(): ?int
    {
        return match ($this) {
            self::None => null,
            self::Hourly => 60,
            self::Daily => 60 * 24,
            self::Weekly => 60 * 24 * 7,
            self::Monthly => 60 * 24 * 30,
        };
    }

    public function nextRunAt(): ?\Carbon\Carbon
    {
        $minutes = $this->intervalMinutes();

        if ($minutes === null) {
            return null;
        }

        return now()->addMinutes($minutes);
    }
}
