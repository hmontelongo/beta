<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when a listing is no longer available (removed, expired, etc.).
 * This exception should NOT trigger retries - the listing is permanently unavailable.
 */
class ListingUnavailableException extends Exception
{
    public function __construct(string $url, ?string $reason = null)
    {
        $message = "Listing no longer available: {$url}";
        if ($reason) {
            $message .= " ({$reason})";
        }

        parent::__construct($message);
    }
}
