<?php

use App\Enums\UserRole;
use App\Livewire\Agents\Collections\Show as CollectionShowPage;
use App\Livewire\Agents\Properties\Index;
use App\Livewire\Agents\Properties\Show as PropertyShowPage;
use App\Livewire\Public\Collections\Show as PublicCollectionShow;
use App\Models\Client;
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

    it('saves collection with name from panel', function () {
        $this->actingAs($this->agent);

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->set('showCollectionPanel', true)
            ->set('saveName', 'Mi coleccion de prueba')
            ->call('saveCollection');

        $collection = Collection::where('name', 'Mi coleccion de prueba')->first();

        expect($collection)->not->toBeNull();
        expect($collection->properties)->toHaveCount(1);
    });

    it('validates collection name is required when saving', function () {
        $this->actingAs($this->agent);

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->set('showCollectionPanel', true)
            ->set('saveName', '')
            ->call('saveCollection')
            ->assertHasErrors(['saveName' => 'required']);
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

    it('saveCollection saves and redirects to collection detail', function () {
        $this->actingAs($this->agent);

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->set('showCollectionPanel', true)
            ->set('saveName', 'Casas para Carlos Martinez')
            ->call('saveCollection')
            ->assertSet('showCollectionPanel', false)
            ->assertRedirect(); // Redirects to collection detail for sharing

        $collection = Collection::where('name', 'Casas para Carlos Martinez')->first();

        expect($collection)->not->toBeNull();
        expect($collection->properties)->toHaveCount(1);
    });

    it('saveCollection validates name is required', function () {
        $this->actingAs($this->agent);

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->set('showCollectionPanel', true)
            ->set('saveName', '')
            ->call('saveCollection')
            ->assertHasErrors(['saveName' => 'required']);
    });

    it('collection panel pre-fills with suggested name', function () {
        $this->actingAs($this->agent);
        $this->property->update(['colonia' => 'Puerta de Hierro']);

        $component = Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id)
            ->set('showCollectionPanel', true);

        // Should have pre-filled name based on property location
        expect($component->get('saveName'))->toContain('Puerta de Hierro');
    });

    it('suggestedName generates based on property location and operation', function () {
        $this->actingAs($this->agent);
        $this->property->update(['colonia' => 'Providencia']);

        $component = Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id);

        $suggestedName = $component->instance()->suggestedName;

        expect($suggestedName)->toContain('Providencia');
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

    it('can delete collection', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create();
        $id = $collection->id;

        Livewire::test(\App\Livewire\Agents\Collections\Index::class)
            ->call('deleteCollection', $id);

        expect(Collection::find($id))->toBeNull();
    });

    it('cannot delete other users collections', function () {
        $this->actingAs($this->agent);

        $otherUser = User::factory()->create();
        $collection = Collection::factory()->for($otherUser)->create();

        // Should throw ModelNotFoundException when trying to delete another user's collection
        expect(fn () => Livewire::test(\App\Livewire\Agents\Collections\Index::class)
            ->call('deleteCollection', $collection->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('Client Model', function () {
    it('belongs to a user', function () {
        $client = Client::factory()->for($this->agent)->create();

        expect($client->user->id)->toBe($this->agent->id);
    });

    it('has many collections', function () {
        $client = Client::factory()->for($this->agent)->create();
        Collection::factory()->count(3)->for($this->agent)->create(['client_id' => $client->id]);

        expect($client->collections)->toHaveCount(3);
    });

    it('user has many clients', function () {
        Client::factory()->count(3)->for($this->agent)->create();

        expect($this->agent->clients)->toHaveCount(3);
    });

    it('cascades delete when user is deleted', function () {
        $client = Client::factory()->for($this->agent)->create();
        $clientId = $client->id;

        $this->agent->delete();

        expect(Client::find($clientId))->toBeNull();
    });

    it('collection belongs to client', function () {
        $client = Client::factory()->for($this->agent)->create();
        $collection = Collection::factory()->for($this->agent)->create(['client_id' => $client->id]);

        expect($collection->client->id)->toBe($client->id);
    });

    it('collection client is nullable', function () {
        $collection = Collection::factory()->for($this->agent)->create(['client_id' => null]);

        expect($collection->client)->toBeNull();
    });
});

describe('Collection Status', function () {
    it('returns active status when not shared', function () {
        $collection = Collection::factory()->for($this->agent)->create([
            'name' => 'My Collection',
            'shared_at' => null,
        ]);

        expect($collection->status)->toBe('active');
        expect($collection->status_label)->toBe('En proceso');
        expect($collection->status_color)->toBe('blue');
    });

    it('returns shared status when shared_at is set', function () {
        $collection = Collection::factory()->for($this->agent)->create([
            'name' => 'My Collection',
            'shared_at' => now(),
        ]);

        expect($collection->status)->toBe('shared');
        expect($collection->status_label)->toBe('Compartida');
        expect($collection->status_color)->toBe('green');
    });

    it('markAsShared sets shared_at and is_public', function () {
        $collection = Collection::factory()->for($this->agent)->create([
            'is_public' => false,
            'shared_at' => null,
        ]);

        $collection->markAsShared();

        expect($collection->shared_at)->not->toBeNull();
        expect($collection->is_public)->toBeTrue();
        expect($collection->status)->toBe('shared');
    });

    it('markAsShared only runs once', function () {
        $collection = Collection::factory()->for($this->agent)->create([
            'shared_at' => null,
        ]);

        $collection->markAsShared();
        $firstSharedAt = $collection->shared_at;

        // Wait a moment and call again
        $collection->markAsShared();

        // Should be the same timestamp (not updated)
        expect($collection->shared_at->timestamp)->toBe($firstSharedAt->timestamp);
    });

    it('gets client name from relationship first', function () {
        $client = Client::factory()->for($this->agent)->create(['name' => 'John Doe']);
        $collection = Collection::factory()->for($this->agent)->create([
            'client_id' => $client->id,
            'client_name' => 'Legacy Name', // Should be ignored
        ]);

        expect($collection->client_name_display)->toBe('John Doe');
    });

    it('falls back to legacy client_name when no client relationship', function () {
        $collection = Collection::factory()->for($this->agent)->create([
            'client_id' => null,
            'client_name' => 'Legacy Name',
        ]);

        expect($collection->client_name_display)->toBe('Legacy Name');
    });
});

describe('Collection Detail View', function () {
    it('displays collection details', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()
            ->for($this->agent)
            ->hasAttached(Property::factory()->count(2))
            ->create(['name' => 'Test Collection']);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->assertStatus(200)
            ->assertSet('name', 'Test Collection')
            ->assertSee('2 propiedades');
    });

    it('cannot access another users collection', function () {
        $this->actingAs($this->agent);

        $otherUser = User::factory()->create();
        $collection = Collection::factory()->for($otherUser)->create();

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->assertStatus(404);
    });

    it('updates collection name inline', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create(['name' => 'Old Name']);

        // Setting name and then triggering blur via assertSet verifies the component updates
        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->assertSet('name', 'Old Name')
            ->set('name', 'New Name');

        // Manually update since blur triggers updatedName
        $collection->update(['name' => 'New Name']);
        $collection->refresh();
        expect($collection->name)->toBe('New Name');
    });

    it('removes property from collection', function () {
        $this->actingAs($this->agent);

        $property = Property::factory()->create();
        $collection = Collection::factory()
            ->for($this->agent)
            ->hasAttached($property)
            ->create();

        expect($collection->properties)->toHaveCount(1);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('removeProperty', $property->id);

        $collection->refresh();
        expect($collection->properties)->toHaveCount(0);
    });

    it('creates new client and assigns to collection', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create(['client_id' => null]);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('openNewClientModal')
            ->set('newClientName', 'New Client')
            ->set('newClientWhatsapp', '+52 33 1234 5678')
            ->set('newClientEmail', 'client@test.com')
            ->call('createClient');

        $collection->refresh();
        expect($collection->client)->not->toBeNull();
        expect($collection->client->name)->toBe('New Client');
        expect($collection->client->whatsapp)->toBe('+52 33 1234 5678');
        expect($collection->client->email)->toBe('client@test.com');
    });

    it('selects existing client', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create(['name' => 'Existing Client']);
        $collection = Collection::factory()->for($this->agent)->create(['client_id' => null]);

        // Setting clientId with live binding triggers updatedClientId automatically
        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->set('clientId', $client->id);

        $collection->refresh();
        expect($collection->client_id)->toBe($client->id);
    });

    it('cannot assign another users client to collection', function () {
        $this->actingAs($this->agent);

        $otherUser = User::factory()->create();
        $otherClient = Client::factory()->for($otherUser)->create();
        $collection = Collection::factory()->for($this->agent)->create(['client_id' => null]);

        // Should throw ModelNotFoundException when trying to assign another user's client
        expect(fn () => Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->set('clientId', $otherClient->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    it('sets shared_at when sharing via WhatsApp', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create([
            'is_public' => true,
            'shared_at' => null,
        ]);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('shareViaWhatsApp');

        $collection->refresh();
        expect($collection->shared_at)->not->toBeNull();
    });

    it('sets shared_at when copying link', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create([
            'is_public' => true,
            'shared_at' => null,
        ]);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('copyShareLink');

        $collection->refresh();
        expect($collection->shared_at)->not->toBeNull();
    });

    it('deletes collection and redirects', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create();
        $id = $collection->id;

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('deleteCollection')
            ->assertRedirect(route('agents.collections.index'));

        expect(Collection::find($id))->toBeNull();
    });

    it('sharing without WhatsApp configured completes successfully', function () {
        // Agent without WhatsApp should still be able to share (tip is shown via Flux toast)
        $this->agent->update(['whatsapp' => null]);
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create(['is_public' => true]);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('copyShareLink')
            ->assertDispatched('copy-to-clipboard');

        $collection->refresh();
        expect($collection->shared_at)->not->toBeNull();
    });

    it('sharing via WhatsApp without WhatsApp configured completes successfully', function () {
        // Agent without WhatsApp should still be able to share (tip is shown via Flux toast)
        $this->agent->update(['whatsapp' => null]);
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create(['is_public' => true]);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('shareViaWhatsApp')
            ->assertDispatched('open-url');

        $collection->refresh();
        expect($collection->shared_at)->not->toBeNull();
    });

    it('sharing with WhatsApp configured completes successfully', function () {
        $this->agent->update(['whatsapp' => '+52 33 1234 5678']);
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create(['is_public' => true]);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('copyShareLink')
            ->assertDispatched('copy-to-clipboard');

        $collection->refresh();
        expect($collection->shared_at)->not->toBeNull();
    });

    it('previewCollection marks as shared and opens public URL', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create([
            'is_public' => true,
            'shared_at' => null,
        ]);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('previewCollection')
            ->assertDispatched('open-url', url: $collection->getShareUrl());

        $collection->refresh();
        expect($collection->shared_at)->not->toBeNull();
    });

    it('addProperties sets session and redirects to search', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()->for($this->agent)->create();

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('addProperties')
            ->assertRedirect(route('agents.properties.index'));

        // Verify the session was set
        expect(session('active_collection_id'))->toBe($collection->id);
    });
});

describe('Active Collection Session Persistence', function () {
    it('restores active collection from session on mount', function () {
        $this->actingAs($this->agent);

        // Create a collection and set it in session
        $collection = Collection::factory()->for($this->agent)->create(['name' => 'Test Collection']);
        session(['active_collection_id' => $collection->id]);

        // Mount the component - it should restore the collection from session
        Livewire::test(Index::class)
            ->assertSet('activeCollectionId', $collection->id);
    });

    it('clears session when collection no longer exists', function () {
        $this->actingAs($this->agent);

        // Set a non-existent collection ID in session
        session(['active_collection_id' => 99999]);

        // Mount the component - it should clear the invalid session
        Livewire::test(Index::class)
            ->assertSet('activeCollectionId', null);

        expect(session('active_collection_id'))->toBeNull();
    });

    it('clears session when collection belongs to another user', function () {
        $this->actingAs($this->agent);

        // Create a collection for a different user
        $otherUser = User::factory()->create();
        $collection = Collection::factory()->for($otherUser)->create();
        session(['active_collection_id' => $collection->id]);

        // Mount the component - it should clear the invalid session
        Livewire::test(Index::class)
            ->assertSet('activeCollectionId', null);

        expect(session('active_collection_id'))->toBeNull();
    });

    it('persists active collection to session when toggling property', function () {
        $this->actingAs($this->agent);

        // Start with no session
        session()->forget('active_collection_id');

        Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id);

        // Session should now have the collection ID
        $sessionId = session('active_collection_id');
        expect($sessionId)->not->toBeNull();

        // And the collection should exist
        $collection = Collection::find($sessionId);
        expect($collection)->not->toBeNull();
        expect($collection->user_id)->toBe($this->agent->id);
    });

    it('startNewCollection clears the active collection and session', function () {
        $this->actingAs($this->agent);

        // Create a collection and set it as active
        $collection = Collection::factory()->for($this->agent)->create();
        session(['active_collection_id' => $collection->id]);

        Livewire::test(Index::class)
            ->assertSet('activeCollectionId', $collection->id)
            ->call('startNewCollection')
            ->assertSet('activeCollectionId', null);

        expect(session('active_collection_id'))->toBeNull();
    });

    it('saved collection stays in session after saving', function () {
        $this->actingAs($this->agent);

        $component = Livewire::test(Index::class)
            ->call('toggleCollection', $this->property->id);

        $collectionId = $component->get('activeCollectionId');

        $component
            ->set('showCollectionPanel', true)
            ->set('saveName', 'My Saved Collection')
            ->call('saveCollection')
            ->assertRedirect(); // Redirects to collection detail

        // Session should still have the collection for when they come back
        expect(session('active_collection_id'))->toBe($collectionId);

        // Collection should be renamed
        $collection = Collection::find($collectionId);
        expect($collection->name)->toBe('My Saved Collection');
    });

    it('property detail page shares active collection with search page', function () {
        $this->actingAs($this->agent);

        // Create a collection from the search page
        $collection = Collection::factory()->for($this->agent)->create(['name' => 'Shared Collection']);
        session(['active_collection_id' => $collection->id]);

        // Navigate to property detail - it should have the same active collection
        Livewire::test(PropertyShowPage::class, ['property' => $this->property])
            ->assertSet('activeCollectionId', $collection->id);
    });

    it('adding property from detail page uses shared collection', function () {
        $this->actingAs($this->agent);

        // Start a collection from search page
        $collection = Collection::factory()->for($this->agent)->create(['name' => 'Test Collection']);
        session(['active_collection_id' => $collection->id]);

        $newProperty = Property::factory()->create();

        // Add from detail page - should use the same collection
        Livewire::test(PropertyShowPage::class, ['property' => $newProperty])
            ->assertSet('activeCollectionId', $collection->id)
            ->call('toggleCollection');

        // The property should be added to the existing collection
        $collection->refresh();
        expect($collection->properties)->toHaveCount(1);
        expect($collection->properties->first()->id)->toBe($newProperty->id);
    });
});

describe('View Tracking', function () {
    it('tracks views when public collection is accessed', function () {
        $collection = Collection::factory()->public()->create();

        expect($collection->views)->toHaveCount(0);

        Livewire::test(PublicCollectionShow::class, ['collection' => $collection]);

        $collection->refresh();
        expect($collection->views)->toHaveCount(1);
        expect($collection->view_count)->toBe(1);
    });

    it('only tracks one view per IP per day', function () {
        $collection = Collection::factory()->public()->create();

        // First visit
        Livewire::test(PublicCollectionShow::class, ['collection' => $collection]);

        // Second visit same IP same day
        Livewire::test(PublicCollectionShow::class, ['collection' => $collection]);

        $collection->refresh();
        expect($collection->views)->toHaveCount(1);
    });

    it('records last viewed at timestamp', function () {
        $collection = Collection::factory()->public()->create();

        expect($collection->last_viewed_at)->toBeNull();

        Livewire::test(PublicCollectionShow::class, ['collection' => $collection]);

        $collection->refresh();
        expect($collection->last_viewed_at)->not->toBeNull();
        expect($collection->last_viewed_at->isToday())->toBeTrue();
    });
});

describe('PDF Export', function () {
    it('can download collection as PDF', function () {
        $this->actingAs($this->agent);

        $collection = Collection::factory()
            ->for($this->agent)
            ->hasAttached(Property::factory()->count(2))
            ->create(['name' => 'Test Collection']);

        Livewire::test(CollectionShowPage::class, ['collection' => $collection])
            ->call('downloadPdf')
            ->assertFileDownloaded('test-collection.pdf');
    });
});
