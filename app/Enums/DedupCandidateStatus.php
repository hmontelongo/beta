<?php

namespace App\Enums;

enum DedupCandidateStatus: string
{
    case Pending = 'pending';
    case ConfirmedMatch = 'confirmed_match';
    case ConfirmedDifferent = 'confirmed_different';
    case NeedsReview = 'needs_review';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::ConfirmedMatch => 'green',
            self::ConfirmedDifferent => 'blue',
            self::NeedsReview => 'amber',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::ConfirmedMatch => 'check-circle',
            self::ConfirmedDifferent => 'x-circle',
            self::NeedsReview => 'question-mark-circle',
        };
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::ConfirmedMatch, self::ConfirmedDifferent]);
    }
}
