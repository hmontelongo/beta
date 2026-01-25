<?php

namespace App\Livewire\Agents\Clients;

use App\Models\Client;
use App\Models\Collection;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.agent')]
class Show extends Component
{
    public Client $client;

    // Edit mode
    public bool $editing = false;

    public string $editName = '';

    public string $editWhatsapp = '';

    public string $editEmail = '';

    public string $editNotes = '';

    public function mount(Client $client): void
    {
        // Ensure the client belongs to the current user (404 to hide existence)
        abort_unless($client->user_id === auth()->id(), 404);

        $this->client = $client;
    }

    public function startEditing(): void
    {
        $this->editName = $this->client->name;
        $this->editWhatsapp = $this->client->whatsapp ?? '';
        $this->editEmail = $this->client->email ?? '';
        $this->editNotes = $this->client->notes ?? '';
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->resetValidation();
    }

    public function saveClient(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editWhatsapp' => 'nullable|string|max:50',
            'editEmail' => 'nullable|email|max:255',
            'editNotes' => 'nullable|string|max:5000',
        ]);

        $this->client->update([
            'name' => $this->editName,
            'whatsapp' => $this->editWhatsapp ?: null,
            'email' => $this->editEmail ?: null,
            'notes' => $this->editNotes ?: null,
        ]);

        $this->editing = false;

        Flux::toast(
            heading: 'Cliente actualizado',
            text: $this->client->name,
            variant: 'success',
        );
    }

    public function deleteClient(): void
    {
        $name = $this->client->name;

        // Nullify the client_id on any collections before deleting
        $this->client->collections()->update(['client_id' => null]);
        $this->client->delete();

        Flux::toast(
            heading: 'Cliente eliminado',
            text: $name,
            variant: 'success',
        );

        $this->redirect(route('agents.clients.index'), navigate: true);
    }

    public function openWhatsApp(): void
    {
        if ($this->client->whatsapp_url) {
            $this->dispatch('open-url', url: $this->client->whatsapp_url);
        }
    }

    /**
     * Get collections for this client with stats.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Collection>
     */
    protected function getCollections(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->client
            ->collections()
            ->where('name', '!=', Collection::DRAFT_NAME)
            ->withCount('properties')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.agents.clients.show', [
            'collections' => $this->getCollections(),
        ])->title($this->client->name);
    }
}
