<?php

namespace App\Enums;

enum DedupStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Matched = 'matched';
    case New = 'new';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Processing => 'blue',
            self::Matched => 'purple',
            self::New => 'green',
            self::NeedsReview => 'amber',
            self::Failed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Processing => 'arrow-path',
            self::Matched => 'link',
            self::New => 'plus-circle',
            self::NeedsReview => 'question-mark-circle',
            self::Failed => 'x-mark',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Processing;
    }
}
