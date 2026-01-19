<?php

namespace App\Models;

use App\Enums\RunFrequency;
use App\Enums\ScrapeRunStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SearchQuery extends Model
{
    /** @use HasFactory<\Database\Factories\SearchQueryFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'run_frequency' => RunFrequency::class,
            'next_run_at' => 'datetime',
            'auto_enabled' => 'boolean',
            'interval_value' => 'integer',
            'scheduled_day' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * @return HasMany<ScrapeRun, $this>
     */
    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class);
    }

    /**
     * @return HasOne<ScrapeRun, $this>
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(ScrapeRun::class)->latestOfMany();
    }

    /**
     * @return HasOne<ScrapeRun, $this>
     */
    public function activeRun(): HasOne
    {
        return $this->hasOne(ScrapeRun::class)
            ->whereIn('status', [
                ScrapeRunStatus::Pending,
                ScrapeRunStatus::Discovering,
                ScrapeRunStatus::Scraping,
            ])
            ->latestOfMany();
    }

    /**
     * Scope for queries that are due to run.
     *
     * @param  Builder<SearchQuery>  $query
     * @return Builder<SearchQuery>
     */
    public function scopeDueForRun(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('auto_enabled', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
    }

    /**
     * Check if this query is due for a scheduled run.
     */
    public function isDueForRun(): bool
    {
        if (! $this->is_active || ! $this->auto_enabled) {
            return false;
        }

        if ($this->next_run_at === null) {
            return true;
        }

        return $this->next_run_at->isPast();
    }

    /**
     * Check if this query has an active run in progress.
     */
    public function hasActiveRun(): bool
    {
        return $this->activeRun()->exists();
    }

    /**
     * Calculate the next run timestamp based on schedule configuration.
     */
    public function calculateNextRunAt(): ?Carbon
    {
        return match ($this->schedule_type) {
            'interval' => $this->calculateIntervalNext(),
            'daily' => $this->calculateDailyNext(),
            'weekly' => $this->calculateWeeklyNext(),
            default => null,
        };
    }

    protected function calculateIntervalNext(): Carbon
    {
        $minutes = match ($this->interval_unit) {
            'minutes' => $this->interval_value,
            'hours' => $this->interval_value * 60,
            'days' => $this->interval_value * 60 * 24,
            default => 60,
        };

        return now()->addMinutes($minutes);
    }

    protected function calculateDailyNext(): Carbon
    {
        $time = $this->scheduled_time ?? '03:00:00';
        $next = now()->setTimeFromTimeString($time);

        if ($next->isPast()) {
            $next->addDay();
        }

        return $next;
    }

    protected function calculateWeeklyNext(): Carbon
    {
        $time = $this->scheduled_time ?? '06:00:00';
        $dayOfWeek = $this->scheduled_day ?? Carbon::MONDAY;

        // Calculate this week's scheduled time
        $thisWeek = now()
            ->startOfWeek(Carbon::SUNDAY)
            ->addDays($dayOfWeek)
            ->setTimeFromTimeString($time);

        // If it's still in the future, use it
        if ($thisWeek->isFuture()) {
            return $thisWeek;
        }

        // Otherwise, use next week
        return $thisWeek->addWeek();
    }

    /**
     * Update the next_run_at timestamp based on the schedule configuration.
     */
    public function scheduleNextRun(): void
    {
        $this->update([
            'next_run_at' => $this->calculateNextRunAt(),
            'last_run_at' => now(),
        ]);
    }

    /**
     * Enable automatic scheduling with the given frequency (legacy method for backwards compatibility).
     */
    public function enableScheduling(RunFrequency $frequency): void
    {
        // Map old frequency to new schedule fields
        $mapping = match ($frequency) {
            RunFrequency::None => ['schedule_type' => 'interval', 'interval_value' => 1, 'interval_unit' => 'hours'],
            RunFrequency::Hourly => ['schedule_type' => 'interval', 'interval_value' => 1, 'interval_unit' => 'hours'],
            RunFrequency::Daily => ['schedule_type' => 'daily', 'interval_value' => 1, 'interval_unit' => 'days'],
            RunFrequency::Weekly => ['schedule_type' => 'weekly', 'interval_value' => 1, 'interval_unit' => 'days'],
            RunFrequency::Monthly => ['schedule_type' => 'interval', 'interval_value' => 30, 'interval_unit' => 'days'],
        };

        $this->update([
            'run_frequency' => $frequency,
            'schedule_type' => $mapping['schedule_type'],
            'interval_value' => $mapping['interval_value'],
            'interval_unit' => $mapping['interval_unit'],
            'auto_enabled' => true,
            'next_run_at' => $this->calculateNextRunAt(),
        ]);

        // Refresh to get updated values for calculateNextRunAt
        $this->refresh();
        $this->update(['next_run_at' => $this->calculateNextRunAt()]);
    }

    /**
     * Disable automatic scheduling.
     */
    public function disableScheduling(): void
    {
        $this->update([
            'auto_enabled' => false,
            'next_run_at' => null,
        ]);
    }

    /**
     * Get a human-readable description of the schedule.
     */
    public function getScheduleDescription(): string
    {
        if (! $this->auto_enabled) {
            return 'Manual only';
        }

        return match ($this->schedule_type) {
            'interval' => $this->getIntervalDescription(),
            'daily' => 'Daily at '.($this->scheduled_time ?? '03:00'),
            'weekly' => $this->getWeeklyDescription(),
            default => 'Unknown',
        };
    }

    protected function getIntervalDescription(): string
    {
        $value = $this->interval_value ?? 1;
        $unit = $this->interval_unit ?? 'hours';

        if ($value === 1) {
            return match ($unit) {
                'minutes' => 'Every minute',
                'hours' => 'Every hour',
                'days' => 'Every day',
                default => "Every {$value} {$unit}",
            };
        }

        return "Every {$value} {$unit}";
    }

    protected function getWeeklyDescription(): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $day = $days[$this->scheduled_day ?? 1] ?? 'Monday';
        $time = $this->scheduled_time ?? '06:00';

        return "Weekly on {$day} at {$time}";
    }
}
