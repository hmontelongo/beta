<?php

namespace App\Livewire\Agents\Clients;

use App\Models\Client;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.agent')]
#[Title('Mis Clientes')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    // Create client modal
    public bool $showCreateModal = false;

    public string $newClientName = '';

    public string $newClientWhatsapp = '';

    public string $newClientEmail = '';

    public string $newClientNotes = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Get paginated clients for the current user.
     *
     * @return LengthAwarePaginator<Client>
     */
    protected function getClients(): LengthAwarePaginator
    {
        return auth()->user()
            ->clients()
            ->withCount('collections')
            ->with('collections')
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('whatsapp', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('updated_at')
            ->paginate(12);
    }

    public function openCreateModal(): void
    {
        $this->reset(['newClientName', 'newClientWhatsapp', 'newClientEmail', 'newClientNotes']);
        $this->showCreateModal = true;
    }

    public function createClient(): void
    {
        $this->validate([
            'newClientName' => 'required|string|max:255',
            'newClientWhatsapp' => 'nullable|string|max:50',
            'newClientEmail' => 'nullable|email|max:255',
            'newClientNotes' => 'nullable|string|max:5000',
        ]);

        $client = auth()->user()->clients()->create([
            'name' => $this->newClientName,
            'whatsapp' => $this->newClientWhatsapp ?: null,
            'email' => $this->newClientEmail ?: null,
            'notes' => $this->newClientNotes ?: null,
        ]);

        $this->showCreateModal = false;
        $this->reset(['newClientName', 'newClientWhatsapp', 'newClientEmail', 'newClientNotes']);

        Flux::toast(
            heading: 'Cliente creado',
            text: $client->name,
            variant: 'success',
        );
    }

    public function deleteClient(int $id): void
    {
        $client = auth()->user()->clients()->findOrFail($id);
        $name = $client->name;

        // Nullify the client_id on any collections before deleting
        $client->collections()->update(['client_id' => null]);
        $client->delete();

        Flux::toast(
            heading: 'Cliente eliminado',
            text: $name,
            variant: 'success',
        );
    }

    public function openWhatsApp(int $id): void
    {
        $client = auth()->user()->clients()->findOrFail($id);

        if ($client->whatsapp_url) {
            $this->dispatch('open-url', url: $client->whatsapp_url);
        }
    }

    public function render(): View
    {
        return view('livewire.agents.clients.index', [
            'clients' => $this->getClients(),
        ]);
    }
}
