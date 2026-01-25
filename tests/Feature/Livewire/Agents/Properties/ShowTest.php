<?php

use App\Livewire\Agents\Properties\Show;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyPresenter;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->agent()->create());
    app()->setLocale('es'); // Set Spanish locale for translations
});

describe('humanizeAmenity', function () {
    it('translates known amenities', function () {
        expect(PropertyPresenter::humanizeAmenity('swimming_pool'))->toBe('Alberca')
            ->and(PropertyPresenter::humanizeAmenity('gym'))->toBe('Gimnasio')
            ->and(PropertyPresenter::humanizeAmenity('elevator'))->toBe('Elevador')
            ->and(PropertyPresenter::humanizeAmenity('pet_friendly'))->toBe('Mascotas permitidas');
    });

    it('handles case insensitivity', function () {
        expect(PropertyPresenter::humanizeAmenity('SWIMMING_POOL'))->toBe('Alberca')
            ->and(PropertyPresenter::humanizeAmenity('Gym'))->toBe('Gimnasio');
    });

    it('falls back to humanized version for unknown amenities', function () {
        expect(PropertyPresenter::humanizeAmenity('unknown_amenity'))->toBe('Unknown amenity')
            ->and(PropertyPresenter::humanizeAmenity('some_fancy_feature'))->toBe('Some fancy feature');
    });
});

describe('formatTargetAudience', function () {
    it('translates known audience types', function () {
        expect(PropertyPresenter::formatTargetAudience(['young_professionals']))->toBe('Profesionales')
            ->and(PropertyPresenter::formatTargetAudience(['couples']))->toBe('Parejas')
            ->and(PropertyPresenter::formatTargetAudience(['families']))->toBe('Familias');
    });

    it('joins multiple audience types with commas', function () {
        expect(PropertyPresenter::formatTargetAudience(['young_professionals', 'couples']))
            ->toBe('Profesionales, Parejas');
    });

    it('handles string input', function () {
        expect(PropertyPresenter::formatTargetAudience('students'))->toBe('Estudiantes');
    });

    it('falls back for unknown audience types', function () {
        expect(PropertyPresenter::formatTargetAudience(['digital_nomads']))->toBe('Digital nomads');
    });
});

describe('formatOccupancyType', function () {
    it('translates known occupancy types', function () {
        expect(PropertyPresenter::formatOccupancyType('single_person_or_couple'))->toBe('Individual/Pareja')
            ->and(PropertyPresenter::formatOccupancyType('family'))->toBe('Familia')
            ->and(PropertyPresenter::formatOccupancyType('roommates'))->toBe('Roomies');
    });

    it('falls back for unknown occupancy types', function () {
        expect(PropertyPresenter::formatOccupancyType('digital_nomads'))->toBe('Digital nomads');
    });
});

describe('conditionLabel', function () {
    it('translates known property conditions', function () {
        expect(PropertyPresenter::conditionLabel('excellent'))->toBe('Excelente')
            ->and(PropertyPresenter::conditionLabel('good'))->toBe('Bueno')
            ->and(PropertyPresenter::conditionLabel('fair'))->toBe('Regular')
            ->and(PropertyPresenter::conditionLabel('needs_work'))->toBe('Necesita trabajo');
    });

    it('falls back for unknown property conditions', function () {
        expect(PropertyPresenter::conditionLabel('pristine'))->toBe('Pristine');
    });
});

describe('getLandmarkIcon', function () {
    it('returns correct emoji for known types', function () {
        expect(PropertyPresenter::getLandmarkIcon('university'))->toBe('🎓')
            ->and(PropertyPresenter::getLandmarkIcon('park'))->toBe('🌳')
            ->and(PropertyPresenter::getLandmarkIcon('shopping_mall'))->toBe('🛒')
            ->and(PropertyPresenter::getLandmarkIcon('hospital'))->toBe('🏥')
            ->and(PropertyPresenter::getLandmarkIcon('metro'))->toBe('🚇')
            ->and(PropertyPresenter::getLandmarkIcon('church'))->toBe('⛪');
    });

    it('returns default pin for unknown types', function () {
        expect(PropertyPresenter::getLandmarkIcon('unknown'))->toBe('📍')
            ->and(PropertyPresenter::getLandmarkIcon('random'))->toBe('📍');
    });
});

describe('hasRentalTermsData', function () {
    it('returns false when no rental terms', function () {
        $property = Property::factory()->create([
            'ai_extracted_data' => null,
        ]);

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->hasRentalTermsData)->toBeFalse();
    });

    it('returns false when terms array is empty', function () {
        $property = Property::factory()->create([
            'ai_extracted_data' => ['terms' => []],
        ]);

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->hasRentalTermsData)->toBeFalse();
    });

    it('returns true when deposit_months exists', function () {
        $property = Property::factory()->create([
            'ai_extracted_data' => ['terms' => ['deposit_months' => 2]],
        ]);

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->hasRentalTermsData)->toBeTrue();
    });

    it('returns true when pets_allowed is set', function () {
        $property = Property::factory()->create([
            'ai_extracted_data' => ['terms' => ['pets_allowed' => true]],
        ]);

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->hasRentalTermsData)->toBeTrue();
    });

    it('returns true when guarantor_required is set', function () {
        $property = Property::factory()->create([
            'ai_extracted_data' => ['terms' => ['guarantor_required' => false]],
        ]);

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->hasRentalTermsData)->toBeTrue();
    });
});

describe('categorizedAmenities', function () {
    it('returns amenities from amenities_categorized for scraped properties', function () {
        $property = Property::factory()->create([
            'ai_extracted_data' => [
                'amenities_categorized' => [
                    'unit' => ['ac', 'washing_machine'],
                    'building' => ['gym', 'pool'],
                ],
            ],
        ]);

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->categorizedAmenities)->toBe([
            'unit' => ['ac', 'washing_machine'],
            'building' => ['gym', 'pool'],
        ]);
    });

    it('returns amenities from amenities key for native properties', function () {
        $property = Property::factory()->native()->create([
            'ai_extracted_data' => [
                'amenities' => [
                    'unit' => ['closet', 'balcony'],
                    'building' => ['rooftop'],
                    'services' => ['internet'],
                ],
            ],
        ]);

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->categorizedAmenities)->toBe([
            'unit' => ['closet', 'balcony'],
            'building' => ['rooftop'],
            'services' => ['internet'],
        ]);
    });
});

describe('renders correctly', function () {
    it('renders the property show page', function () {
        $property = Property::factory()->create([
            'address' => '123 Test Street',
            'colonia' => 'Test Colonia',
            'city' => 'Guadalajara',
        ]);

        Livewire::test(Show::class, ['property' => $property])
            ->assertStatus(200)
            ->assertSee('123 Test Street');
    });
});
