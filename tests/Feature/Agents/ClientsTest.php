<?php

use App\Enums\UserRole;
use App\Livewire\Agents\Clients\Index as ClientsIndex;
use App\Livewire\Agents\Clients\Show as ClientsShow;
use App\Models\Client;
use App\Models\Collection;
use App\Models\CollectionView;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->agent = User::factory()->create(['role' => UserRole::Agent]);
});

describe('Client Model Computed Properties', function () {
    it('computes total views across all collections', function () {
        $client = Client::factory()->for($this->agent)->create();

        $collection1 = Collection::factory()->for($this->agent)->create(['client_id' => $client->id]);
        $collection2 = Collection::factory()->for($this->agent)->create(['client_id' => $client->id]);

        // Create views for collections
        CollectionView::insert([
            ['collection_id' => $collection1->id, 'ip_address' => '1.1.1.1', 'viewed_at' => now()],
            ['collection_id' => $collection1->id, 'ip_address' => '1.1.1.2', 'viewed_at' => now()],
            ['collection_id' => $collection1->id, 'ip_address' => '1.1.1.3', 'viewed_at' => now()],
            ['collection_id' => $collection1->id, 'ip_address' => '1.1.1.4', 'viewed_at' => now()],
            ['collection_id' => $collection1->id, 'ip_address' => '1.1.1.5', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.1', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.2', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.3', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.4', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.5', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.6', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.7', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.8', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.9', 'viewed_at' => now()],
            ['collection_id' => $collection2->id, 'ip_address' => '2.2.2.10', 'viewed_at' => now()],
        ]);

        $client->load('collections.views');
        expect($client->total_views)->toBe(15);
    });

    it('returns zero views when no collections', function () {
        $client = Client::factory()->for($this->agent)->create();

        expect($client->total_views)->toBe(0);
    });

    it('computes last activity from most recently updated collection', function () {
        $client = Client::factory()->for($this->agent)->create();

        // Create two collections with different updated_at timestamps
        $olderCollection = Collection::factory()->for($this->agent)->create([
            'client_id' => $client->id,
            'updated_at' => now()->subDays(7),
        ]);

        $newerCollection = Collection::factory()->for($this->agent)->create([
            'client_id' => $client->id,
            'updated_at' => now()->subDay(),
        ]);

        $client->load('collections');
        expect($client->last_activity->timestamp)->toBe($newerCollection->updated_at->timestamp);
    });

    it('falls back to client updated_at when no collections', function () {
        $client = Client::factory()->for($this->agent)->create();

        // Last activity should be client's updated_at when no collections
        expect($client->last_activity->timestamp)->toBe($client->updated_at->timestamp);
    });

    it('generates WhatsApp URL correctly', function () {
        $client = Client::factory()->for($this->agent)->create([
            'whatsapp' => '+52 33 1234 5678',
        ]);

        expect($client->whatsapp_url)->toBe('https://wa.me/523312345678');
    });

    it('returns null WhatsApp URL when no phone', function () {
        $client = Client::factory()->for($this->agent)->create([
            'whatsapp' => null,
        ]);

        expect($client->whatsapp_url)->toBeNull();
    });
});

describe('Clients Index Page', function () {
    it('requires authentication', function () {
        $this->get(route('agents.clients.index'))
            ->assertRedirect(route('login'));
    });

    it('lists clients for authenticated agent', function () {
        $this->actingAs($this->agent);

        Client::factory()->for($this->agent)->create(['name' => 'John Doe']);
        Client::factory()->for($this->agent)->create(['name' => 'Jane Smith']);

        Livewire::test(ClientsIndex::class)
            ->assertStatus(200)
            ->assertSee('John Doe')
            ->assertSee('Jane Smith');
    });

    it('does not show other users clients', function () {
        $this->actingAs($this->agent);

        $otherUser = User::factory()->create();
        Client::factory()->for($otherUser)->create(['name' => 'Other User Client']);
        Client::factory()->for($this->agent)->create(['name' => 'My Client']);

        Livewire::test(ClientsIndex::class)
            ->assertSee('My Client')
            ->assertDontSee('Other User Client');
    });

    it('can search clients by name', function () {
        $this->actingAs($this->agent);

        Client::factory()->for($this->agent)->create(['name' => 'Maria Garcia']);
        Client::factory()->for($this->agent)->create(['name' => 'Carlos Mendoza']);

        Livewire::test(ClientsIndex::class)
            ->set('search', 'Maria')
            ->assertSee('Maria Garcia')
            ->assertDontSee('Carlos Mendoza');
    });

    it('can search clients by email', function () {
        $this->actingAs($this->agent);

        Client::factory()->for($this->agent)->create([
            'name' => 'Client A',
            'email' => 'unique@email.com',
        ]);
        Client::factory()->for($this->agent)->create([
            'name' => 'Client B',
            'email' => 'other@email.com',
        ]);

        Livewire::test(ClientsIndex::class)
            ->set('search', 'unique@email')
            ->assertSee('Client A')
            ->assertDontSee('Client B');
    });

    it('can search clients by phone', function () {
        $this->actingAs($this->agent);

        Client::factory()->for($this->agent)->create([
            'name' => 'Client A',
            'whatsapp' => '+52 33 1234 5678',
        ]);
        Client::factory()->for($this->agent)->create([
            'name' => 'Client B',
            'whatsapp' => '+52 55 9876 5432',
        ]);

        Livewire::test(ClientsIndex::class)
            ->set('search', '1234')
            ->assertSee('Client A')
            ->assertDontSee('Client B');
    });

    it('can create a new client', function () {
        $this->actingAs($this->agent);

        Livewire::test(ClientsIndex::class)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->set('newClientName', 'New Client')
            ->set('newClientWhatsapp', '+52 33 1234 5678')
            ->set('newClientEmail', 'new@client.com')
            ->set('newClientNotes', 'Test notes')
            ->call('createClient')
            ->assertSet('showCreateModal', false);

        expect($this->agent->clients()->where('name', 'New Client')->exists())->toBeTrue();

        $client = $this->agent->clients()->where('name', 'New Client')->first();
        expect($client->whatsapp)->toBe('+52 33 1234 5678');
        expect($client->email)->toBe('new@client.com');
        expect($client->notes)->toBe('Test notes');
    });

    it('validates client name is required', function () {
        $this->actingAs($this->agent);

        Livewire::test(ClientsIndex::class)
            ->call('openCreateModal')
            ->set('newClientName', '')
            ->call('createClient')
            ->assertHasErrors(['newClientName' => 'required']);
    });

    it('validates client email format', function () {
        $this->actingAs($this->agent);

        Livewire::test(ClientsIndex::class)
            ->call('openCreateModal')
            ->set('newClientName', 'Test')
            ->set('newClientEmail', 'invalid-email')
            ->call('createClient')
            ->assertHasErrors(['newClientEmail' => 'email']);
    });

    it('can delete a client', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create(['name' => 'To Delete']);
        $clientId = $client->id;

        Livewire::test(ClientsIndex::class)
            ->call('deleteClient', $clientId);

        expect(Client::find($clientId))->toBeNull();
    });

    it('nullifies collection client_id when deleting client', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create();
        $collection = Collection::factory()->for($this->agent)->create(['client_id' => $client->id]);

        Livewire::test(ClientsIndex::class)
            ->call('deleteClient', $client->id);

        $collection->refresh();
        expect($collection->client_id)->toBeNull();
    });

    it('cannot delete another users client', function () {
        $this->actingAs($this->agent);

        $otherUser = User::factory()->create();
        $client = Client::factory()->for($otherUser)->create();

        expect(fn () => Livewire::test(ClientsIndex::class)
            ->call('deleteClient', $client->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    it('can open WhatsApp for client', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create([
            'whatsapp' => '+52 33 1234 5678',
        ]);

        Livewire::test(ClientsIndex::class)
            ->call('openWhatsApp', $client->id)
            ->assertDispatched('open-url', url: 'https://wa.me/523312345678');
    });

    it('shows collections count for each client', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create(['name' => 'Test Client']);
        Collection::factory()->for($this->agent)->count(3)->create(['client_id' => $client->id]);

        Livewire::test(ClientsIndex::class)
            ->assertSee('3 colecciones');
    });

    it('shows empty state when no clients', function () {
        $this->actingAs($this->agent);

        Livewire::test(ClientsIndex::class)
            ->assertSee('No tienes clientes')
            ->assertSee('Agrega tu primer cliente');
    });
});

describe('Client Detail Page', function () {
    it('requires authentication', function () {
        $client = Client::factory()->create();

        $this->get(route('agents.clients.show', $client))
            ->assertRedirect(route('login'));
    });

    it('shows client details', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create([
            'name' => 'Test Client',
            'whatsapp' => '+52 33 1234 5678',
            'email' => 'test@client.com',
            'notes' => 'Some notes about this client',
        ]);

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->assertStatus(200)
            ->assertSee('Test Client')
            ->assertSee('+52 33 1234 5678')
            ->assertSee('test@client.com')
            ->assertSee('Some notes about this client');
    });

    it('cannot view another users client', function () {
        $this->actingAs($this->agent);

        $otherUser = User::factory()->create();
        $client = Client::factory()->for($otherUser)->create();

        // Returns 404 to hide resource existence
        Livewire::test(ClientsShow::class, ['client' => $client])
            ->assertStatus(404);
    });

    it('can edit client inline', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create([
            'name' => 'Old Name',
        ]);

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->call('startEditing')
            ->assertSet('editing', true)
            ->assertSet('editName', 'Old Name')
            ->set('editName', 'New Name')
            ->set('editWhatsapp', '+52 55 9999 8888')
            ->call('saveClient')
            ->assertSet('editing', false);

        $client->refresh();
        expect($client->name)->toBe('New Name');
        expect($client->whatsapp)->toBe('+52 55 9999 8888');
    });

    it('can cancel editing', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create([
            'name' => 'Original Name',
        ]);

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->call('startEditing')
            ->set('editName', 'Changed Name')
            ->call('cancelEditing')
            ->assertSet('editing', false);

        $client->refresh();
        expect($client->name)->toBe('Original Name');
    });

    it('validates edited client name', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create();

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->call('startEditing')
            ->set('editName', '')
            ->call('saveClient')
            ->assertHasErrors(['editName' => 'required']);
    });

    it('validates edited client email', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create();

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->call('startEditing')
            ->set('editEmail', 'invalid')
            ->call('saveClient')
            ->assertHasErrors(['editEmail' => 'email']);
    });

    it('can delete client from detail page', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create();
        $clientId = $client->id;

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->call('deleteClient')
            ->assertRedirect(route('agents.clients.index'));

        expect(Client::find($clientId))->toBeNull();
    });

    it('lists client collections', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create();
        Collection::factory()->for($this->agent)->create([
            'client_id' => $client->id,
            'name' => 'First Collection',
        ]);
        Collection::factory()->for($this->agent)->create([
            'client_id' => $client->id,
            'name' => 'Second Collection',
        ]);

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->assertSee('First Collection')
            ->assertSee('Second Collection');
    });

    it('excludes draft collections from list', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create();
        Collection::factory()->for($this->agent)->create([
            'client_id' => $client->id,
            'name' => Collection::DRAFT_NAME,
        ]);
        Collection::factory()->for($this->agent)->create([
            'client_id' => $client->id,
            'name' => 'Real Collection',
        ]);

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->assertSee('Real Collection')
            ->assertDontSee(Collection::DRAFT_NAME);
    });

    it('shows activity stats', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create();
        $collection1 = Collection::factory()->for($this->agent)->create(['client_id' => $client->id]);
        $collection2 = Collection::factory()->for($this->agent)->create(['client_id' => $client->id]);

        // Create 10 views for collection1
        for ($i = 0; $i < 10; $i++) {
            CollectionView::create([
                'collection_id' => $collection1->id,
                'ip_address' => "10.0.0.{$i}",
                'viewed_at' => now(),
            ]);
        }
        // Create 5 views for collection2
        for ($i = 0; $i < 5; $i++) {
            CollectionView::create([
                'collection_id' => $collection2->id,
                'ip_address' => "20.0.0.{$i}",
                'viewed_at' => now(),
            ]);
        }

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->assertSee('15'); // Total views
    });

    it('shows empty state when no collections', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create();

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->assertSee('Aun no hay colecciones para este cliente');
    });

    it('can open WhatsApp', function () {
        $this->actingAs($this->agent);

        $client = Client::factory()->for($this->agent)->create([
            'whatsapp' => '+52 33 1234 5678',
        ]);

        Livewire::test(ClientsShow::class, ['client' => $client])
            ->call('openWhatsApp')
            ->assertDispatched('open-url', url: 'https://wa.me/523312345678');
    });
});

describe('Navigation', function () {
    it('has clients link in agent navigation', function () {
        $this->actingAs($this->agent);

        $this->get(route('agents.properties.index'))
            ->assertSee('Clientes');
    });

    it('has clients link in profile dropdown', function () {
        $this->actingAs($this->agent);

        $this->get(route('agents.properties.index'))
            ->assertSee('Mis Clientes');
    });
});
