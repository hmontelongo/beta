<?php

namespace App\Enums;

enum OperationType: string
{
    case Rent = 'rent';
    case Sale = 'sale';

    /**
     * Get Spanish label for the operation type.
     */
    public function labelEs(): string
    {
        return match ($this) {
            self::Rent => 'Renta',
            self::Sale => 'Venta',
        };
    }

    /**
     * Get Spanish plural label for the operation type.
     */
    public function labelEsPlural(): string
    {
        return match ($this) {
            self::Rent => 'Rentas',
            self::Sale => 'Ventas',
        };
    }
}
