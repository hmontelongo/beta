<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Agent => 'Agent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'red',
            self::Agent => 'blue',
        };
    }

    public function canAccessAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function canAccessAgents(): bool
    {
        return true;
    }
}
