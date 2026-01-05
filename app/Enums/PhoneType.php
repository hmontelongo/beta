<?php

namespace App\Enums;

enum PhoneType: string
{
    case Whatsapp = 'whatsapp';
    case Mobile = 'mobile';
    case Landline = 'landline';
    case Unknown = 'unknown';
}
