<?php

use App\Enums\UserRole;
use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->agent = User::factory()->create(['role' => UserRole::Agent]);
});

describe('Agent Profile Fields in Profile Page', function () {
    it('shows agent fields for agents on profile page', function () {
        $this->actingAs($this->agent)
            ->get(route('agents.profile.edit'))
            ->assertSuccessful()
            ->assertSee('Agent Profile');
    });

    it('loads existing agent profile data on mount', function () {
        $this->agent->update([
            'phone' => '+52 33 1234 5678',
            'whatsapp' => '+52 33 8765 4321',
            'business_name' => 'Test Inmobiliaria',
            'tagline' => 'Tu hogar ideal',
            'brand_color' => '#3B82F6',
            'default_whatsapp_message' => 'Hola {client_name}!',
        ]);

        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->assertSet('phone', '+52 33 1234 5678')
            ->assertSet('whatsapp', '+52 33 8765 4321')
            ->assertSet('businessName', 'Test Inmobiliaria')
            ->assertSet('tagline', 'Tu hogar ideal')
            ->assertSet('brandColor', '#3B82F6')
            ->assertSet('defaultWhatsappMessage', 'Hola {client_name}!');
    });

    it('saves agent profile fields', function () {
        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('name', $this->agent->name)
            ->set('email', $this->agent->email)
            ->set('phone', '+52 33 1111 2222')
            ->set('whatsapp', '+52 33 3333 4444')
            ->set('businessName', 'Nueva Inmobiliaria')
            ->set('tagline', 'El mejor servicio')
            ->set('brandColor', '#10B981')
            ->set('defaultWhatsappMessage', 'Mensaje personalizado')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->agent->refresh();

        expect($this->agent->phone)->toBe('+52 33 1111 2222');
        expect($this->agent->whatsapp)->toBe('+52 33 3333 4444');
        expect($this->agent->business_name)->toBe('Nueva Inmobiliaria');
        expect($this->agent->tagline)->toBe('El mejor servicio');
        expect($this->agent->brand_color)->toBe('#10B981');
        expect($this->agent->default_whatsapp_message)->toBe('Mensaje personalizado');
    });

    it('clears optional fields when empty', function () {
        $this->agent->update([
            'business_name' => 'Old Name',
            'tagline' => 'Old Tagline',
        ]);

        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('name', $this->agent->name)
            ->set('email', $this->agent->email)
            ->set('businessName', '')
            ->set('tagline', '')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->agent->refresh();

        expect($this->agent->business_name)->toBeNull();
        expect($this->agent->tagline)->toBeNull();
    });

    it('validates brand color format', function () {
        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('name', $this->agent->name)
            ->set('email', $this->agent->email)
            ->set('brandColor', 'invalid')
            ->call('updateProfileInformation')
            ->assertHasErrors(['brandColor']);

        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('name', $this->agent->name)
            ->set('email', $this->agent->email)
            ->set('brandColor', '#GGG')
            ->call('updateProfileInformation')
            ->assertHasErrors(['brandColor']);
    });

    it('accepts valid hex colors', function () {
        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('name', $this->agent->name)
            ->set('email', $this->agent->email)
            ->set('brandColor', '#FF5733')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->agent->refresh();
        expect($this->agent->brand_color)->toBe('#FF5733');
    });
});

describe('Avatar Upload', function () {
    it('uploads avatar successfully', function () {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('avatar', $file)
            ->call('saveAvatar')
            ->assertHasNoErrors();

        $this->agent->refresh();

        expect($this->agent->avatar_path)->not->toBeNull();
        Storage::disk('public')->assertExists($this->agent->avatar_path);
    });

    it('deletes old avatar when uploading new one', function () {
        // Upload first avatar
        $file1 = UploadedFile::fake()->image('avatar1.jpg');
        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('avatar', $file1)
            ->call('saveAvatar');

        $this->agent->refresh();
        $oldPath = $this->agent->avatar_path;
        Storage::disk('public')->assertExists($oldPath);

        // Upload second avatar
        $file2 = UploadedFile::fake()->image('avatar2.jpg');
        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('avatar', $file2)
            ->call('saveAvatar');

        $this->agent->refresh();

        // Old avatar should be deleted
        Storage::disk('public')->assertMissing($oldPath);
        // New avatar should exist
        Storage::disk('public')->assertExists($this->agent->avatar_path);
    });

    it('deletes avatar when requested', function () {
        $file = UploadedFile::fake()->image('avatar.jpg');
        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('avatar', $file)
            ->call('saveAvatar');

        $this->agent->refresh();
        $avatarPath = $this->agent->avatar_path;

        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->call('deleteAvatar');

        $this->agent->refresh();

        expect($this->agent->avatar_path)->toBeNull();
        Storage::disk('public')->assertMissing($avatarPath);
    });

    it('validates avatar file type', function () {
        $file = UploadedFile::fake()->create('document.txt', 100);

        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('avatar', $file)
            ->call('saveAvatar')
            ->assertHasErrors(['avatar']);
    });

    it('validates avatar file size', function () {
        $file = UploadedFile::fake()->image('avatar.jpg')->size(3000); // 3MB

        Livewire::actingAs($this->agent)
            ->test(Profile::class)
            ->set('avatar', $file)
            ->assertHasErrors(['avatar']);
    });
});

describe('User Model Accessors', function () {
    it('returns avatar URL when avatar exists', function () {
        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $file->store("avatars/{$this->agent->id}", 'public');

        $this->agent->update(['avatar_path' => $path]);

        expect($this->agent->avatar_url)->toContain($path);
    });

    it('returns null for avatar URL when no avatar', function () {
        expect($this->agent->avatar_url)->toBeNull();
    });

    it('hasAvatar returns true when avatar exists', function () {
        $this->agent->update(['avatar_path' => 'avatars/1/test.jpg']);

        expect($this->agent->hasAvatar())->toBeTrue();
    });

    it('hasAvatar returns false when no avatar', function () {
        expect($this->agent->hasAvatar())->toBeFalse();
    });

    it('display_name returns business name when set', function () {
        $this->agent->update(['business_name' => 'Test Business']);

        expect($this->agent->display_name)->toBe('Test Business');
    });

    it('display_name returns personal name when no business name', function () {
        expect($this->agent->display_name)->toBe($this->agent->name);
    });
});

describe('Non-agent users', function () {
    it('does not show agent fields for admin users', function () {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertSuccessful()
            ->assertDontSee('Agent Profile');
    });

    it('does not load agent fields for admin users', function () {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(Profile::class)
            ->assertSet('phone', '')
            ->assertSet('whatsapp', '')
            ->assertSet('businessName', '');
    });
});
