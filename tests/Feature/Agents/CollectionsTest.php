<?php

use App\Enums\UserRole;
use App\Livewire\Agents\Properties\Index;
use App\Livewire\Public\Collections\Show as PublicCollectionShow;
use App\Models\Collection;
use App\Models\Property;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->agent = User::factory()->create(['role' => UserRole::Agent]);
    $this->property = Property::factory()->create();
});

describe('Collection Model', function () {
    it('auto-generates share token on creation', function () {
        $collection = Collection::factory()->create(['share_token' => null]);

        expect($collection->share_token)->not->toBeNull();
        expect($collection->share_token)->toHaveLength(16);
    });

    it('generates unique share tokens', function () {
        $collection1 = Collection::factory()->create();
        $collection2 = Collection::factory()->create();

        expect($collection1->share_token)->not->toBe($collection2->share_token);
    });

    it('belongs to a user', function () {
        $collection = Collection::factory()->for($this->agent)->create();

        expect($collection->user->id)->toBe($this->agent->id);
    });

    it('has many properties through pivot', function () {
        $collection = Collection::factory()->for($this->agent)->create();
        $properties = Property::factory()->count(3)->create();

        $collection->properties()->attach($properties->pluck('id'));

        expect($collection->properties)->toHaveCount(3);
    });

    it('is accessible when public and not expired', function () {
        $collection = Collection::factory()->public()->create();

        expect($collection->isAccessible())->toBeTrue();
    });

    it('is not accessible when private', function () {
        $collection = Collection::factory()->create(['is_public' => false]);

        expect($collection->isAccessible())->toBeFalse();
    });

    it('is not accessible when expired', function () {
        $collection = Collection::factory()->public()->expired()->create();

        expect($collection->isAccessible())->toBeFalse();
    });

    it('generates share URL correctly', function () {
        $collection = Collection::factory()->create(['share_token' => 'abc123']);

        expect($collection->getShareUrl())->toContain('/c/abc123');
    });

    it('generates WhatsApp share URL', function () {
        $collection = Collection::factory()->create([
            'name' => 'Mi coleccion',
            'share_token' => 'abc123',
        ]);

        $url = $collection->getWhatsAppShareUrl();

        expect($url)->toContain('wa.me');
        expect($url)->toContain('Mi'); // Check for name presence (encoding may vary)
    });
});

describe('Agent Collection Management', function () {
    it('creates a collection when adding first property', function () {
        $this->actingAs($this->agent);

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id);

        expect($this->agent->collections()->count())->toBe(1);
        expect($this->agent->collections->first()->properties)->toHaveCount(1);
    });

    it('adds properties to existing collection', function () {
        $this->actingAs($this->agent);
        $property2 = Property::factory()->create();

        $component = Livewire::test(Index::class);

        $component->call('toggleCollection', $this->property->id);
        $component->call('toggleCollection', $property2->id);

        expect($this->agent->collections()->count())->toBe(1);
        expect($this->agent->collections->first()->properties)->toHaveCount(2);
    });

    it('removes properties from collection', function () {
        $this->actingAs($this->agent);

        $component = Livewire::test(Index::class);

        $component->call('toggleCollection', $this->property->id);
        $component->call('toggleCollection', $this->property->id); // Toggle off

        expect($this->agent->collections->first()->properties)->toHaveCount(0);
    });

    it('clears all properties from collection', function () {
        $this->actingAs($this->agent);
        $property2 = Property::factory()->create();

        $component = Livewire::test(Index::class);

        $component->call('toggleCollection', $this->property->id);
        $component->call('toggleCollection', $property2->id);
        $component->call('clearCollection');

        expect($this->agent->collections->first()->properties)->toHaveCount(0);
    });

    it('saves collection with name via quick share', function () {
        $this->actingAs($this->agent);

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->call('openShareModal')
            ->set('shareName', 'Mi coleccion de prueba')
            ->call('quickShareCopyLink');

        $collection = Collection::where('name', 'Mi coleccion de prueba')->first();

        expect($collection)->not->toBeNull();
        expect($collection->is_public)->toBeTrue();
        expect($collection->properties)->toHaveCount(1);
    });

    it('validates collection name is required when sharing', function () {
        $this->actingAs($this->agent);

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->call('openShareModal')
            ->set('shareName', '')
            ->call('quickShareCopyLink')
            ->assertHasErrors(['shareName' => 'required']);
    });

    it('isInCollection returns true for added properties', function () {
        $this->actingAs($this->agent);

        $component = Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id);

        expect($component->instance()->isInCollection($this->property->id))->toBeTrue();
    });

    it('isInCollection returns false for non-added properties', function () {
        $this->actingAs($this->agent);
        $property2 = Property::factory()->create();

        $component = Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id);

        expect($component->instance()->isInCollection($property2->id))->toBeFalse();
    });
});

describe('Public Collection Display', function () {
    it('displays public collection by share token', function () {
        $collection = Collection::factory()
            ->public()
            ->hasAttached(Property::factory()->count(2))
            ->create(['name' => 'Test Collection']);

        Livewire::test(PublicCollectionShow::class, ['collection' => $collection])
            ->assertStatus(200)
            ->assertSee('Test Collection')
            ->assertSee('2 propiedades');
    });

    it('returns 404 for private collections', function () {
        $collection = Collection::factory()->create(['is_public' => false]);

        Livewire::test(PublicCollectionShow::class, ['collection' => $collection])
            ->assertStatus(404);
    });

    it('returns 404 for expired collections', function () {
        $collection = Collection::factory()->public()->expired()->create();

        Livewire::test(PublicCollectionShow::class, ['collection' => $collection])
            ->assertStatus(404);
    });

    it('displays property cards in collection', function () {
        $property = Property::factory()->create(['colonia' => 'Providencia']);
        $collection = Collection::factory()
            ->public()
            ->hasAttached($property)
            ->create();

        Livewire::test(PublicCollectionShow::class, ['collection' => $collection])
            ->assertStatus(200)
            ->assertSee('Providencia');
    });
});

describe('User Collection Relationship', function () {
    it('user has many collections', function () {
        Collection::factory()->count(3)->for($this->agent)->create();

        expect($this->agent->collections)->toHaveCount(3);
    });

    it('cascades delete on user deletion', function () {
        $collection = Collection::factory()->for($this->agent)->create();
        $collectionId = $collection->id;

        $this->agent->delete();

        expect(Collection::find($collectionId))->toBeNull();
    });
});

describe('Client Association', function () {
    it('generates WhatsApp URL with client phone number', function () {
        $collection = Collection::factory()->create([
            'name' => 'Test',
            'share_token' => 'abc123',
            'client_whatsapp' => '+52 33 1234 5678',
        ]);

        $url = $collection->getWhatsAppShareUrl();

        expect($url)->toContain('wa.me/523312345678');
    });

    it('generates WhatsApp URL without phone when no client', function () {
        $collection = Collection::factory()->create([
            'name' => 'Test',
            'share_token' => 'abc123',
            'client_whatsapp' => null,
        ]);

        $url = $collection->getWhatsAppShareUrl();

        expect($url)->toContain('wa.me/?text=');
    });

    it('quick share makes collection public', function () {
        $this->actingAs($this->agent);

        $component = Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->call('openShareModal')
            ->set('shareName', 'Shared Collection')
            ->call('quickShareCopyLink');

        $collection = Collection::where('name', 'Shared Collection')->first();

        expect($collection->is_public)->toBeTrue();
    });

    it('keeps collection active after sharing', function () {
        $this->actingAs($this->agent);

        $component = Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->call('openShareModal')
            ->set('shareName', 'Persistent Collection')
            ->call('quickShareCopyLink');

        // activeCollectionId should NOT be null after sharing
        expect($component->get('activeCollectionId'))->not->toBeNull();
    });

    it('saveAndRedirect assigns default name to draft collection', function () {
        $this->actingAs($this->agent);

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->call('saveAndRedirect')
            ->assertRedirect(route('agents.collections.index'));

        // Collection should have auto-generated name (not draft)
        $collection = $this->agent->collections()->first();
        expect($collection->name)->not->toBe(Collection::DRAFT_NAME);
        expect($collection->name)->toContain('Coleccion');
    });
});

describe('Collections Management Page', function () {
    it('lists user collections', function () {
        $this->actingAs($this->agent);

        Collection::factory()
            ->for($this->agent)
            ->count(3)
            ->create();

        Livewire::test(\App\Livewire\Agents\Collections\Index::class)
            ->assertStatus(200);
    });

    it('can filter by public collections', function () {
        $this->actingAs($this->agent);

        Collection::factory()->for($this->agent)->public()->create(['name' => 'Public One']);
        Collection::factory()->for($this->agent)->create(['name' => 'Private One', 'is_public' => false]);

        Livewire::test(\App\Livewire\Agents\Collections\Index::class)
            ->set('filter', 'public')
            ->assertSee('Public One')
            ->assertDontSee('Private One');
    });

    it('can edit collection from management page', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create(['name' => 'Original Name']);

        Livewire::test(\App\Livewire\Agents\Collections\Index::class)
            ->call('editCollection', $collection->id)
            ->assertSet('editName', 'Original Name')
            ->set('editName', 'Updated Name')
            ->set('editClientName', 'New Client')
            ->call('updateCollection');

        $collection->refresh();
        expect($collection->name)->toBe('Updated Name');
        expect($collection->client_name)->toBe('New Client');
    });

    it('can delete collection', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create();
        $id = $collection->id;

        Livewire::test(\App\Livewire\Agents\Collections\Index::class)
            ->call('deleteCollection', $id);

        expect(Collection::find($id))->toBeNull();
    });

    it('cannot access other users collections', function () {
        $this->actingAs($this->agent);

        $otherUser = User::factory()->create();
        $collection = Collection::factory()->for($otherUser)->create();

        // Should throw ModelNotFoundException when trying to edit another user's collection
        expect(fn () => Livewire::test(\App\Livewire\Agents\Collections\Index::class)
            ->call('editCollection', $collection->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});
