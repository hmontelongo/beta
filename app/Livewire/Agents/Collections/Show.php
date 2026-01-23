<?php

namespace App\Livewire\Agents\Collections;

use App\Livewire\Concerns\ShowsWhatsAppTip;
use App\Models\Client;
use App\Models\Collection;
use Flux\Flux;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.agent')]
#[Title('Detalle de Coleccion')]
class Show extends Component
{
    use ShowsWhatsAppTip;

    public Collection $collection;

    // Editable fields
    public string $name = '';

    public ?int $clientId = null;

    public bool $isPublic = true;

    // New client modal
    public bool $showNewClientModal = false;

    public string $newClientName = '';

    public string $newClientWhatsapp = '';

    public string $newClientEmail = '';

    public string $newClientNotes = '';

    public function mount(Collection $collection): void
    {
        abort_unless($collection->user_id === auth()->id(), 404);

        $this->collection = $collection->load(['properties.listings', 'client']);
        $this->name = $collection->name;
        $this->clientId = $collection->client_id;
        $this->isPublic = $collection->is_public;
    }

    /**
     * Get all clients for the current user.
     *
     * @return SupportCollection<int, Client>
     */
    #[Computed]
    public function clients(): SupportCollection
    {
        return auth()->user()
            ->clients()
            ->withCount('collections')
            ->orderBy('name')
            ->get();
    }

    public function updatedName(): void
    {
        $this->validateOnly('name', ['name' => 'required|string|max:255']);
        $this->collection->update(['name' => $this->name]);
    }

    public function updatedClientId(): void
    {
        // Verify the client belongs to the current user
        if ($this->clientId !== null) {
            auth()->user()->clients()->findOrFail($this->clientId);
        }

        $this->collection->update(['client_id' => $this->clientId]);

        Flux::toast(
            text: 'Cliente actualizado',
            variant: 'success',
        );
    }

    public function updatedIsPublic(): void
    {
        $this->collection->update(['is_public' => $this->isPublic]);

        Flux::toast(
            text: $this->isPublic ? 'Coleccion publica' : 'Coleccion privada',
            variant: 'success',
        );
    }

    public function openNewClientModal(): void
    {
        $this->reset(['newClientName', 'newClientWhatsapp', 'newClientEmail', 'newClientNotes']);
        $this->showNewClientModal = true;
    }

    public function createClient(): void
    {
        $this->validate([
            'newClientName' => 'required|string|max:255',
            'newClientWhatsapp' => 'nullable|string|max:20',
            'newClientEmail' => 'nullable|email|max:255',
            'newClientNotes' => 'nullable|string|max:1000',
        ]);

        $client = auth()->user()->clients()->create([
            'name' => $this->newClientName,
            'whatsapp' => $this->newClientWhatsapp ?: null,
            'email' => $this->newClientEmail ?: null,
            'notes' => $this->newClientNotes ?: null,
        ]);

        $this->collection->update(['client_id' => $client->id]);
        $this->collection->refresh();
        $this->collection->load('client');
        $this->clientId = $client->id;
        $this->showNewClientModal = false;

        unset($this->clients);

        Flux::toast(
            heading: 'Cliente creado',
            text: $client->name,
            variant: 'success',
        );
    }

    /**
     * Reorder a property via drag and drop (Livewire 4 wire:sort).
     */
    public function reorderProperty(int|string $item, int $position): void
    {
        $this->collection->properties()->updateExistingPivot((int) $item, [
            'position' => $position,
        ]);

        $this->collection->load('properties.listings');
    }

    public function removeProperty(int $propertyId): void
    {
        // Verify user still owns this collection (defense in depth)
        abort_unless($this->collection->user_id === auth()->id(), 403);

        $this->collection->properties()->detach($propertyId);
        $this->collection->load('properties.listings');

        Flux::toast(
            text: 'Propiedad removida',
            variant: 'success',
        );
    }

    public function shareViaWhatsApp(): void
    {
        $this->collection->update([
            'is_public' => true,
            'shared_at' => now(),
        ]);
        $this->isPublic = true;

        $this->dispatch('open-url', url: $this->collection->getWhatsAppShareUrl());

        $this->showWhatsAppTipIfNeeded();
    }

    public function copyShareLink(): void
    {
        $this->collection->update([
            'is_public' => true,
            'shared_at' => now(),
        ]);
        $this->isPublic = true;

        $this->dispatch('copy-to-clipboard', text: $this->collection->getShareUrl());

        Flux::toast(
            heading: 'Link copiado',
            text: 'El link ha sido copiado al portapapeles',
            variant: 'success',
        );

        $this->showWhatsAppTipIfNeeded();
    }

    public function deleteCollection(): void
    {
        $this->collection->delete();

        Flux::toast(
            text: 'Coleccion eliminada',
            variant: 'success',
        );

        $this->redirectRoute('agents.collections.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.agents.collections.show');
    }
}
