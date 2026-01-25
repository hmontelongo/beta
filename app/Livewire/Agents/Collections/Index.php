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

    public function updatedSearch(): void
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
            ->with(['client', 'properties.listings', 'properties.propertyImages'])
            ->where('name', '!=', Collection::DRAFT_NAME)
            ->withCount('properties')
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('client_name', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderByDesc('updated_at')
            ->paginate(12);
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

        $collection->update([
            'is_public' => true,
            'shared_at' => now(),
        ]);

        $this->dispatch('open-url', url: $collection->getWhatsAppShareUrl());
    }

    public function onLinkCopied(int $id): void
    {
        $collection = auth()->user()->collections()->findOrFail($id);

        $collection->update([
            'is_public' => true,
            'shared_at' => now(),
        ]);

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
