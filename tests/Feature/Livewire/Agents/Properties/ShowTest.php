<?php

use App\Livewire\Agents\Properties\Show;
use App\Models\Property;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->agent()->create());
    app()->setLocale('es'); // Set Spanish locale for translations
});

describe('humanizeAmenity', function () {
    it('translates known amenities', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->humanizeAmenity('swimming_pool'))->toBe('Alberca')
            ->and($instance->humanizeAmenity('gym'))->toBe('Gimnasio')
            ->and($instance->humanizeAmenity('elevator'))->toBe('Elevador')
            ->and($instance->humanizeAmenity('pet_friendly'))->toBe('Mascotas permitidas');
    });

    it('handles case insensitivity', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->humanizeAmenity('SWIMMING_POOL'))->toBe('Alberca')
            ->and($instance->humanizeAmenity('Gym'))->toBe('Gimnasio');
    });

    it('falls back to humanized version for unknown amenities', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->humanizeAmenity('unknown_amenity'))->toBe('Unknown amenity')
            ->and($instance->humanizeAmenity('some_fancy_feature'))->toBe('Some fancy feature');
    });
});

describe('formatTargetAudience', function () {
    it('translates known audience types', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->formatTargetAudience(['young_professionals']))->toBe('Profesionales')
            ->and($instance->formatTargetAudience(['couples']))->toBe('Parejas')
            ->and($instance->formatTargetAudience(['families']))->toBe('Familias');
    });

    it('joins multiple audience types with commas', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->formatTargetAudience(['young_professionals', 'couples']))
            ->toBe('Profesionales, Parejas');
    });

    it('handles string input', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->formatTargetAudience('students'))->toBe('Estudiantes');
    });

    it('falls back for unknown audience types', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->formatTargetAudience(['digital_nomads']))->toBe('Digital nomads');
    });
});

describe('formatOccupancyType', function () {
    it('translates known occupancy types', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->formatOccupancyType('single_person_or_couple'))->toBe('Individual/Pareja')
            ->and($instance->formatOccupancyType('family'))->toBe('Familia')
            ->and($instance->formatOccupancyType('roommates'))->toBe('Roomies');
    });

    it('falls back for unknown occupancy types', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->formatOccupancyType('digital_nomads'))->toBe('Digital nomads');
    });
});

describe('formatPropertyCondition', function () {
    it('translates known property conditions', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->formatPropertyCondition('excellent'))->toBe('Excelente')
            ->and($instance->formatPropertyCondition('good'))->toBe('Bueno')
            ->and($instance->formatPropertyCondition('fair'))->toBe('Regular')
            ->and($instance->formatPropertyCondition('needs_work'))->toBe('Necesita trabajo');
    });

    it('falls back for unknown property conditions', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->formatPropertyCondition('pristine'))->toBe('Pristine');
    });
});

describe('getLandmarkIcon', function () {
    it('returns correct emoji for known types', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->getLandmarkIcon('university'))->toBe('🎓')
            ->and($instance->getLandmarkIcon('park'))->toBe('🌳')
            ->and($instance->getLandmarkIcon('shopping_mall'))->toBe('🛒')
            ->and($instance->getLandmarkIcon('hospital'))->toBe('🏥')
            ->and($instance->getLandmarkIcon('metro'))->toBe('🚇')
            ->and($instance->getLandmarkIcon('church'))->toBe('⛪');
    });

    it('returns default pin for unknown types', function () {
        $property = Property::factory()->create();

        $component = Livewire::test(Show::class, ['property' => $property]);
        $instance = $component->instance();

        expect($instance->getLandmarkIcon('unknown'))->toBe('📍')
            ->and($instance->getLandmarkIcon('random'))->toBe('📍');
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
