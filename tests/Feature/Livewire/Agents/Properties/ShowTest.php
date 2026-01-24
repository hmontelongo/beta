<?php

use App\Livewire\Agents\Properties\Show;
use App\Models\Property;
use App\Models\User;
use App\Services\CollectionPropertyPresenter;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->agent()->create());
    app()->setLocale('es'); // Set Spanish locale for translations
});

describe('humanizeAmenity', function () {
    it('translates known amenities', function () {
        expect(CollectionPropertyPresenter::humanizeAmenity('swimming_pool'))->toBe('Alberca')
            ->and(CollectionPropertyPresenter::humanizeAmenity('gym'))->toBe('Gimnasio')
            ->and(CollectionPropertyPresenter::humanizeAmenity('elevator'))->toBe('Elevador')
            ->and(CollectionPropertyPresenter::humanizeAmenity('pet_friendly'))->toBe('Mascotas permitidas');
    });

    it('handles case insensitivity', function () {
        expect(CollectionPropertyPresenter::humanizeAmenity('SWIMMING_POOL'))->toBe('Alberca')
            ->and(CollectionPropertyPresenter::humanizeAmenity('Gym'))->toBe('Gimnasio');
    });

    it('falls back to humanized version for unknown amenities', function () {
        expect(CollectionPropertyPresenter::humanizeAmenity('unknown_amenity'))->toBe('Unknown amenity')
            ->and(CollectionPropertyPresenter::humanizeAmenity('some_fancy_feature'))->toBe('Some fancy feature');
    });
});

describe('formatTargetAudience', function () {
    it('translates known audience types', function () {
        expect(CollectionPropertyPresenter::formatTargetAudience(['young_professionals']))->toBe('Profesionales')
            ->and(CollectionPropertyPresenter::formatTargetAudience(['couples']))->toBe('Parejas')
            ->and(CollectionPropertyPresenter::formatTargetAudience(['families']))->toBe('Familias');
    });

    it('joins multiple audience types with commas', function () {
        expect(CollectionPropertyPresenter::formatTargetAudience(['young_professionals', 'couples']))
            ->toBe('Profesionales, Parejas');
    });

    it('handles string input', function () {
        expect(CollectionPropertyPresenter::formatTargetAudience('students'))->toBe('Estudiantes');
    });

    it('falls back for unknown audience types', function () {
        expect(CollectionPropertyPresenter::formatTargetAudience(['digital_nomads']))->toBe('Digital nomads');
    });
});

describe('formatOccupancyType', function () {
    it('translates known occupancy types', function () {
        expect(CollectionPropertyPresenter::formatOccupancyType('single_person_or_couple'))->toBe('Individual/Pareja')
            ->and(CollectionPropertyPresenter::formatOccupancyType('family'))->toBe('Familia')
            ->and(CollectionPropertyPresenter::formatOccupancyType('roommates'))->toBe('Roomies');
    });

    it('falls back for unknown occupancy types', function () {
        expect(CollectionPropertyPresenter::formatOccupancyType('digital_nomads'))->toBe('Digital nomads');
    });
});

describe('formatPropertyCondition', function () {
    it('translates known property conditions', function () {
        expect(CollectionPropertyPresenter::formatPropertyCondition('excellent'))->toBe('Excelente')
            ->and(CollectionPropertyPresenter::formatPropertyCondition('good'))->toBe('Bueno')
            ->and(CollectionPropertyPresenter::formatPropertyCondition('fair'))->toBe('Regular')
            ->and(CollectionPropertyPresenter::formatPropertyCondition('needs_work'))->toBe('Necesita trabajo');
    });

    it('falls back for unknown property conditions', function () {
        expect(CollectionPropertyPresenter::formatPropertyCondition('pristine'))->toBe('Pristine');
    });
});

describe('getLandmarkIcon', function () {
    it('returns correct emoji for known types', function () {
        expect(CollectionPropertyPresenter::getLandmarkIcon('university'))->toBe('🎓')
            ->and(CollectionPropertyPresenter::getLandmarkIcon('park'))->toBe('🌳')
            ->and(CollectionPropertyPresenter::getLandmarkIcon('shopping_mall'))->toBe('🛒')
            ->and(CollectionPropertyPresenter::getLandmarkIcon('hospital'))->toBe('🏥')
            ->and(CollectionPropertyPresenter::getLandmarkIcon('metro'))->toBe('🚇')
            ->and(CollectionPropertyPresenter::getLandmarkIcon('church'))->toBe('⛪');
    });

    it('returns default pin for unknown types', function () {
        expect(CollectionPropertyPresenter::getLandmarkIcon('unknown'))->toBe('📍')
            ->and(CollectionPropertyPresenter::getLandmarkIcon('random'))->toBe('📍');
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
