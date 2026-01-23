<?php

namespace App\Livewire\Agents\Collections;

use App\Models\Collection;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.agent')]
#[Title('Mis Colecciones')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'all'; // all, public, private

    // Edit modal state
    public bool $showEditModal = false;

    public ?int $editingCollectionId = null;

    public string $editName = '';

    public string $editClientName = '';

    public string $editClientWhatsapp = '';

    public bool $editIsPublic = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Get paginated collections for the current user.
     *
     * @return LengthAwarePaginator<Collection>
     */
    protected function getCollections(): LengthAwarePaginator
    {
        return auth()->user()
            ->collections()
            ->where('name', '!=', Collection::DRAFT_NAME)
            ->withCount('properties')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('client_name', 'like', "%{$this->search}%"))
            ->when($this->filter === 'public', fn ($q) => $q->where('is_public', true))
            ->when($this->filter === 'private', fn ($q) => $q->where('is_public', false))
            ->orderByDesc('updated_at')
            ->paginate(12);
    }

    public function editCollection(int $id): void
    {
        $collection = auth()->user()->collections()->findOrFail($id);

        $this->editingCollectionId = $id;
        $this->editName = $collection->name;
        $this->editClientName = $collection->client_name ?? '';
        $this->editClientWhatsapp = $collection->client_whatsapp ?? '';
        $this->editIsPublic = $collection->is_public;
        $this->showEditModal = true;
    }

    public function updateCollection(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editClientName' => 'nullable|string|max:255',
            'editClientWhatsapp' => 'nullable|string|max:20',
        ]);

        $collection = auth()->user()->collections()->findOrFail($this->editingCollectionId);
        $collection->update([
            'name' => $this->editName,
            'client_name' => $this->editClientName ?: null,
            'client_whatsapp' => $this->editClientWhatsapp ?: null,
            'is_public' => $this->editIsPublic,
        ]);

        $this->showEditModal = false;
        $this->reset(['editingCollectionId', 'editName', 'editClientName', 'editClientWhatsapp', 'editIsPublic']);

        Flux::toast(
            heading: 'Coleccion actualizada',
            text: $collection->name,
            variant: 'success',
        );
    }

    public function deleteCollection(int $id): void
    {
        $collection = auth()->user()->collections()->findOrFail($id);
        $name = $collection->name;
        $collection->delete();

        Flux::toast(
            heading: 'Coleccion eliminada',
            text: $name,
            variant: 'success',
        );
    }

    public function shareViaWhatsApp(int $id): void
    {
        $collection = auth()->user()->collections()->findOrFail($id);

        if (! $collection->is_public) {
            $collection->update(['is_public' => true]);
        }

        $this->dispatch('open-url', url: $collection->getWhatsAppShareUrl());
    }

    public function copyShareLink(int $id): void
    {
        $collection = auth()->user()->collections()->findOrFail($id);

        if (! $collection->is_public) {
            $collection->update(['is_public' => true]);
        }

        $this->dispatch('copy-to-clipboard', text: $collection->getShareUrl());

        Flux::toast(
            heading: 'Link copiado',
            text: 'El link ha sido copiado al portapapeles',
            variant: 'success',
        );
    }

    public function render(): View
    {
        return view('livewire.agents.collections.index', [
            'collections' => $this->getCollections(),
        ]);
    }
}
