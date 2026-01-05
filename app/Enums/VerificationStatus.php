<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Responded = 'responded';
    case NoResponse = 'no_response';
    case Failed = 'failed';
}
