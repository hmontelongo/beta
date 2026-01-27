<?php

namespace App\Enums;

enum ListingGroupStatus: string
{
    case PendingReview = 'pending_review';
    case PendingAi = 'pending_ai';
    case ProcessingAi = 'processing_ai';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::PendingReview => 'amber',
            self::PendingAi => 'blue',
            self::ProcessingAi => 'purple',
            self::Completed => 'green',
            self::Rejected => 'red',
            self::Failed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PendingReview => 'question-mark-circle',
            self::PendingAi => 'clock',
            self::ProcessingAi => 'sparkles',
            self::Completed => 'check-circle',
            self::Rejected => 'x-circle',
            self::Failed => 'exclamation-triangle',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Needs Review',
            self::PendingAi => 'Queued for AI',
            self::ProcessingAi => 'AI Processing',
            self::Completed => 'Property Created',
            self::Rejected => 'Rejected',
            self::Failed => 'AI Failed',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ProcessingAi;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PendingReview, self::PendingAi]);
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Failed]);
    }
}
