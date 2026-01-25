<?php

use App\Enums\OperationType;

describe('labelEs', function () {
    it('returns Renta for rent', function () {
        expect(OperationType::Rent->labelEs())->toBe('Renta');
    });

    it('returns Venta for sale', function () {
        expect(OperationType::Sale->labelEs())->toBe('Venta');
    });
});

describe('labelEsPlural', function () {
    it('returns Rentas for rent', function () {
        expect(OperationType::Rent->labelEsPlural())->toBe('Rentas');
    });

    it('returns Ventas for sale', function () {
        expect(OperationType::Sale->labelEsPlural())->toBe('Ventas');
    });
});
