<?php

namespace App\Livewire\Concerns;

use App\Models\Collection;

/**
 * Provides session-persisted active collection state for Livewire components.
 *
 * This trait enables the "active collection" concept where agents can:
 * - Add properties to a collection across multiple sessions
 * - Navigate to property details and back without losing selection
 * - Continue adding to a saved collection after naming it
 */
trait HasActiveCollection
{
    /** Active collection ID (synced with session) */
    public ?int $activeCollectionId = null;

    /**
     * Initialize active collection from session.
     * Call this in mount() after any other initialization.
     */
    protected function initializeActiveCollection(): void
    {
        $sessionId = session('active_collection_id');

        if ($sessionId) {
            // Validate the collection still exists and belongs to user
            $exists = auth()->user()
                ?->collections()
                ->where('id', $sessionId)
                ->exists();

            if ($exists) {
                $this->activeCollectionId = $sessionId;
            } else {
                // Collection was deleted or doesn't belong to user
                $this->clearActiveCollection();
            }
        }
    }

    /**
     * Set the active collection and persist to session.
     */
    protected function setActiveCollection(int $id): void
    {
        $this->activeCollectionId = $id;
        session(['active_collection_id' => $id]);
    }

    /**
     * Clear the active collection from state and session.
     */
    protected function clearActiveCollection(): void
    {
        $this->activeCollectionId = null;
        session()->forget('active_collection_id');
    }

    /**
     * Start a new collection (clear current selection).
     * This is the action for the "Nueva coleccion" button.
     */
    public function startNewCollection(): void
    {
        $this->clearActiveCollection();
        $this->clearCollectionCaches();
    }

    /**
     * Get the active collection model.
     */
    public function getActiveCollectionModel(): ?Collection
    {
        if (! $this->activeCollectionId) {
            return null;
        }

        return Collection::find($this->activeCollectionId);
    }

    /**
     * Ensure an active collection exists, creating a draft if needed.
     */
    protected function ensureActiveCollection(): Collection
    {
        if ($this->activeCollectionId) {
            $collection = Collection::find($this->activeCollectionId);
            if ($collection) {
                return $collection;
            }
        }

        // Create new draft collection
        $collection = auth()->user()->collections()->create([
            'name' => Collection::DRAFT_NAME,
        ]);

        $this->setActiveCollection($collection->id);

        return $collection;
    }

    /**
     * Clear collection-related computed property caches.
     * Override in component if additional caches need clearing.
     */
    protected function clearCollectionCaches(): void
    {
        // Components should override this to clear their specific computed properties
    }
}
