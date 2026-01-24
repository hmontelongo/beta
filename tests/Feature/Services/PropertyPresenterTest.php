<?php

use App\Enums\PropertySubtype;
use App\Enums\PropertyType;
use App\Services\PropertyPresenter;

beforeEach(function () {
    // Ensure Spanish locale is set for translation tests
    app()->setLocale('es');
});

// ═══════════════════════════════════════════════════════════════════════════
// PRICE FORMATTING
// ═══════════════════════════════════════════════════════════════════════════

describe('formatPrice', function () {
    it('formats a sale price correctly', function () {
        $price = ['type' => 'sale', 'price' => 1500000, 'currency' => 'MXN'];

        $result = PropertyPresenter::formatPrice($price);

        expect($result)->toBe('$1,500,000');
    });

    it('formats a rent price with /mes suffix', function () {
        $price = ['type' => 'rent', 'price' => 25000, 'currency' => 'MXN'];

        $result = PropertyPresenter::formatPrice($price);

        expect($result)->toBe('$25,000/mes');
    });

    it('shows currency for non-MXN prices', function () {
        $price = ['type' => 'sale', 'price' => 500000, 'currency' => 'USD'];

        $result = PropertyPresenter::formatPrice($price);

        expect($result)->toBe('$500,000 USD');
    });

    it('returns Consultar for null price', function () {
        expect(PropertyPresenter::formatPrice(null))->toBe('Consultar');
    });

    it('returns Consultar for zero price', function () {
        $price = ['type' => 'sale', 'price' => 0];

        expect(PropertyPresenter::formatPrice($price))->toBe('Consultar');
    });

    it('can omit period suffix when requested', function () {
        $price = ['type' => 'rent', 'price' => 25000];

        $result = PropertyPresenter::formatPrice($price, withPeriod: false);

        expect($result)->toBe('$25,000');
    });
});

describe('formatPriceCompact', function () {
    it('formats millions correctly', function () {
        $price = ['price' => 2500000];

        expect(PropertyPresenter::formatPriceCompact($price))->toBe('$2.5M');
    });

    it('formats thousands correctly', function () {
        $price = ['price' => 45000];

        expect(PropertyPresenter::formatPriceCompact($price))->toBe('$45K');
    });

    it('formats small amounts without suffix', function () {
        $price = ['price' => 500];

        expect(PropertyPresenter::formatPriceCompact($price))->toBe('$500');
    });
});

describe('formatPricePerM2', function () {
    it('formats correctly', function () {
        expect(PropertyPresenter::formatPricePerM2(15000))->toBe('$15,000/m²');
    });

    it('returns empty string for null', function () {
        expect(PropertyPresenter::formatPricePerM2(null))->toBe('');
    });

    it('returns empty string for zero', function () {
        expect(PropertyPresenter::formatPricePerM2(0))->toBe('');
    });
});

describe('formatMaintenanceFee', function () {
    it('formats numeric fee', function () {
        expect(PropertyPresenter::formatMaintenanceFee(2500))->toBe('+ $2,500/mes');
    });

    it('formats array fee with period', function () {
        $fee = ['amount' => 3000, 'period' => 'monthly'];

        expect(PropertyPresenter::formatMaintenanceFee($fee))->toBe('+ $3,000/mes');
    });

    it('formats yearly fee', function () {
        $fee = ['amount' => 12000, 'period' => 'yearly'];

        expect(PropertyPresenter::formatMaintenanceFee($fee))->toBe('+ $12,000/año');
    });

    it('returns null for empty fee', function () {
        expect(PropertyPresenter::formatMaintenanceFee(null))->toBeNull();
    });
});

describe('operationTypeLabel', function () {
    it('returns Renta for rent', function () {
        expect(PropertyPresenter::operationTypeLabel('rent'))->toBe('Renta');
    });

    it('returns Venta for sale', function () {
        expect(PropertyPresenter::operationTypeLabel('sale'))->toBe('Venta');
    });

    it('returns Consultar for unknown', function () {
        expect(PropertyPresenter::operationTypeLabel('unknown'))->toBe('Consultar');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// PROPERTY SPECS (with proper Spanish accents)
// ═══════════════════════════════════════════════════════════════════════════

describe('formatBedrooms', function () {
    it('formats singular correctly with accent', function () {
        expect(PropertyPresenter::formatBedrooms(1))->toBe('1 recámara');
    });

    it('formats plural correctly with accent', function () {
        expect(PropertyPresenter::formatBedrooms(3))->toBe('3 recámaras');
    });

    it('formats abbreviated correctly', function () {
        expect(PropertyPresenter::formatBedrooms(3, abbrev: true))->toBe('3 rec');
    });

    it('returns empty string for null', function () {
        expect(PropertyPresenter::formatBedrooms(null))->toBe('');
    });
});

describe('formatBathrooms', function () {
    it('formats singular correctly with accent', function () {
        expect(PropertyPresenter::formatBathrooms(1))->toBe('1 baño');
    });

    it('formats plural correctly with accent', function () {
        expect(PropertyPresenter::formatBathrooms(2))->toBe('2 baños');
    });

    it('formats abbreviated correctly', function () {
        expect(PropertyPresenter::formatBathrooms(2, abbrev: true))->toBe('2 baños');
    });
});

describe('formatHalfBathrooms', function () {
    it('formats singular correctly', function () {
        expect(PropertyPresenter::formatHalfBathrooms(1))->toBe('1 medio baño');
    });

    it('formats plural correctly', function () {
        expect(PropertyPresenter::formatHalfBathrooms(2))->toBe('2 medios baños');
    });

    it('returns empty string for zero', function () {
        expect(PropertyPresenter::formatHalfBathrooms(0))->toBe('');
    });
});

describe('formatParking', function () {
    it('formats singular correctly', function () {
        expect(PropertyPresenter::formatParking(1))->toBe('1 estacionamiento');
    });

    it('formats plural correctly', function () {
        expect(PropertyPresenter::formatParking(2))->toBe('2 estacionamientos');
    });

    it('formats abbreviated correctly', function () {
        expect(PropertyPresenter::formatParking(2, abbrev: true))->toBe('2 est');
    });
});

describe('formatBuiltSize', function () {
    it('formats correctly', function () {
        expect(PropertyPresenter::formatBuiltSize(150))->toBe('150 m²');
    });

    it('formats large sizes with comma', function () {
        expect(PropertyPresenter::formatBuiltSize(1500))->toBe('1,500 m²');
    });

    it('returns empty string for null', function () {
        expect(PropertyPresenter::formatBuiltSize(null))->toBe('');
    });
});

describe('formatLotSize', function () {
    it('formats correctly with terreno label', function () {
        expect(PropertyPresenter::formatLotSize(200))->toBe('200 m² terreno');
    });
});

describe('formatAge', function () {
    it('formats singular year with proper accent', function () {
        expect(PropertyPresenter::formatAge(1))->toBe('1 año');
    });

    it('formats plural years with proper accent', function () {
        expect(PropertyPresenter::formatAge(5))->toBe('5 años');
    });

    it('formats zero as new construction', function () {
        expect(PropertyPresenter::formatAge(0))->toBe('Nuevo/Estrenar');
    });

    it('returns empty string for null', function () {
        expect(PropertyPresenter::formatAge(null))->toBe('');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// LABELS
// ═══════════════════════════════════════════════════════════════════════════

describe('propertyTypeLabel', function () {
    it('returns Spanish label for apartment', function () {
        expect(PropertyPresenter::propertyTypeLabel(PropertyType::Apartment))->toBe('Departamento');
    });

    it('returns Spanish label for house', function () {
        expect(PropertyPresenter::propertyTypeLabel(PropertyType::House))->toBe('Casa');
    });

    it('returns Propiedad for null', function () {
        expect(PropertyPresenter::propertyTypeLabel(null))->toBe('Propiedad');
    });
});

describe('propertySubtypeLabel', function () {
    it('returns Spanish label for penthouse', function () {
        expect(PropertyPresenter::propertySubtypeLabel(PropertySubtype::Penthouse))->toBe('Penthouse');
    });

    it('returns Spanish label for studio', function () {
        expect(PropertyPresenter::propertySubtypeLabel(PropertySubtype::Studio))->toBe('Estudio');
    });

    it('returns Spanish label for duplex with accent', function () {
        expect(PropertyPresenter::propertySubtypeLabel(PropertySubtype::Duplex))->toBe('Dúplex');
    });

    it('returns empty string for null', function () {
        expect(PropertyPresenter::propertySubtypeLabel(null))->toBe('');
    });
});

describe('conditionLabel', function () {
    it('returns Spanish label for excellent', function () {
        expect(PropertyPresenter::conditionLabel('excellent'))->toBe('Excelente');
    });

    it('returns Spanish label for good', function () {
        expect(PropertyPresenter::conditionLabel('good'))->toBe('Bueno');
    });

    it('returns empty string for null', function () {
        expect(PropertyPresenter::conditionLabel(null))->toBe('');
    });
});

describe('freshnessLabel', function () {
    it('returns Spanish label for fresh', function () {
        expect(PropertyPresenter::freshnessLabel('fresh'))->toBe('Reciente');
    });

    it('returns Spanish label for stale', function () {
        expect(PropertyPresenter::freshnessLabel('stale'))->toBe('Antiguo');
    });
});

describe('targetAudienceLabel', function () {
    it('returns Spanish label for families', function () {
        expect(PropertyPresenter::targetAudienceLabel('families'))->toBe('Familias');
    });

    it('returns Spanish label for professionals', function () {
        expect(PropertyPresenter::targetAudienceLabel('professionals'))->toBe('Profesionales');
    });
});

describe('formatTargetAudience', function () {
    it('formats array of audiences', function () {
        $audiences = ['families', 'professionals'];

        expect(PropertyPresenter::formatTargetAudience($audiences))->toBe('Familias, Profesionales');
    });

    it('formats single string audience', function () {
        expect(PropertyPresenter::formatTargetAudience('students'))->toBe('Estudiantes');
    });

    it('returns empty string for null', function () {
        expect(PropertyPresenter::formatTargetAudience(null))->toBe('');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// AMENITIES
// ═══════════════════════════════════════════════════════════════════════════

describe('humanizeAmenity', function () {
    it('translates swimming pool', function () {
        expect(PropertyPresenter::humanizeAmenity('swimming_pool'))->toBe('Alberca');
    });

    it('translates elevator', function () {
        expect(PropertyPresenter::humanizeAmenity('elevator'))->toBe('Elevador');
    });

    it('falls back to humanized form for unknown', function () {
        expect(PropertyPresenter::humanizeAmenity('some_unknown_amenity'))->toBe('Some unknown amenity');
    });
});

describe('getAmenityIcon', function () {
    it('returns pool icon', function () {
        expect(PropertyPresenter::getAmenityIcon('swimming_pool'))->toBe('🏊');
    });

    it('returns gym icon', function () {
        expect(PropertyPresenter::getAmenityIcon('gym'))->toBe('💪');
    });

    it('returns checkmark for unknown', function () {
        expect(PropertyPresenter::getAmenityIcon('unknown'))->toBe('✓');
    });
});

describe('getLandmarkIcon', function () {
    it('returns university icon', function () {
        expect(PropertyPresenter::getLandmarkIcon('university'))->toBe('🎓');
    });

    it('returns hospital icon', function () {
        expect(PropertyPresenter::getLandmarkIcon('hospital'))->toBe('🏥');
    });

    it('returns pin for unknown', function () {
        expect(PropertyPresenter::getLandmarkIcon('unknown'))->toBe('📍');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// LOCATION
// ═══════════════════════════════════════════════════════════════════════════

describe('formatLocation', function () {
    it('formats colonia and city', function () {
        expect(PropertyPresenter::formatLocation('Polanco', 'CDMX'))->toBe('Polanco, CDMX');
    });

    it('handles null colonia', function () {
        expect(PropertyPresenter::formatLocation(null, 'CDMX'))->toBe('CDMX');
    });

    it('handles null city', function () {
        expect(PropertyPresenter::formatLocation('Polanco', null))->toBe('Polanco');
    });

    it('handles both null', function () {
        expect(PropertyPresenter::formatLocation(null, null))->toBe('');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// ICONS
// ═══════════════════════════════════════════════════════════════════════════

describe('icon methods', function () {
    it('bedroomIcon returns SVG', function () {
        $icon = PropertyPresenter::bedroomIcon();

        expect($icon)->toContain('<svg');
        expect($icon)->toContain('stroke-linecap');
    });

    it('bathroomIcon returns SVG', function () {
        expect(PropertyPresenter::bathroomIcon())->toContain('<svg');
    });

    it('parkingIcon returns SVG', function () {
        expect(PropertyPresenter::parkingIcon())->toContain('<svg');
    });

    it('sizeIcon returns SVG', function () {
        expect(PropertyPresenter::sizeIcon())->toContain('<svg');
    });

    it('locationIcon returns SVG', function () {
        expect(PropertyPresenter::locationIcon())->toContain('<svg');
    });

    it('icon accepts custom class', function () {
        $icon = PropertyPresenter::bedroomIcon('w-4 h-4');

        expect($icon)->toContain('class="w-4 h-4"');
    });
});
