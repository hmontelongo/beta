<?php

namespace App\Services\AI;

use App\Enums\ApiOperation;

readonly class ClaudeCallContext
{
    public function __construct(
        public ApiOperation $operation,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?string $jobClass = null,
    ) {}

    public static function forListing(int $listingId, ?string $jobClass = null): self
    {
        return new self(
            operation: ApiOperation::PropertyCreation,
            entityType: 'listing',
            entityId: $listingId,
            jobClass: $jobClass,
        );
    }

    public static function forListingGroup(int $groupId, ?string $jobClass = null): self
    {
        return new self(
            operation: ApiOperation::PropertyCreation,
            entityType: 'listing_group',
            entityId: $groupId,
            jobClass: $jobClass,
        );
    }
}
