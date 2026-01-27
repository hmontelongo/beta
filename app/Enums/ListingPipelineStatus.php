<?php

namespace App\Enums;

enum ListingPipelineStatus: string
{
    case AwaitingGeocoding = 'awaiting_geocoding';
    case AwaitingDedup = 'awaiting_dedup';
    case ProcessingDedup = 'processing_dedup';
    case WaitingForGroup = 'waiting_for_group';
    case NeedsReview = 'needs_review';
    case QueuedForAi = 'queued_for_ai';
    case ProcessingAi = 'processing_ai';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingGeocoding => 'Awaiting Geocoding',
            self::AwaitingDedup => 'Awaiting Dedup',
            self::ProcessingDedup => 'Processing',
            self::WaitingForGroup => 'Waiting for Group',
            self::NeedsReview => 'Needs Review',
            self::QueuedForAi => 'Queued for AI',
            self::ProcessingAi => 'AI Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AwaitingGeocoding, self::AwaitingDedup => 'zinc',
            self::ProcessingDedup => 'blue',
            self::WaitingForGroup => 'yellow',
            self::NeedsReview => 'amber',
            self::QueuedForAi, self::ProcessingAi => 'purple',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Cancelled => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::AwaitingGeocoding => 'map-pin',
            self::AwaitingDedup => 'clock',
            self::ProcessingDedup => 'arrow-path',
            self::WaitingForGroup => 'pause-circle',
            self::NeedsReview => 'eye',
            self::QueuedForAi => 'sparkles',
            self::ProcessingAi => 'cog-6-tooth',
            self::Completed => 'check-circle',
            self::Failed => 'x-circle',
            self::Cancelled => 'no-symbol',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::ProcessingDedup, self::ProcessingAi]);
    }
}
