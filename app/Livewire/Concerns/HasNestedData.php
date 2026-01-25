<?php

namespace App\Livewire\Concerns;

/**
 * Trait for Livewire components that need to manipulate nested array data.
 *
 * Components using this trait must define the property name via nestedDataProperty().
 * The property should be a public array (e.g., public array $data = []).
 */
trait HasNestedData
{
    /**
     * Get the name of the property that holds the nested data.
     * Override this method if your component uses a different property name.
     */
    protected function nestedDataProperty(): string
    {
        return 'data';
    }

    /**
     * Update a nested value in the data array.
     */
    public function updateValue(string $path, mixed $value): void
    {
        $property = $this->nestedDataProperty();
        $keys = explode('.', $path);
        $data = &$this->{$property};

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $data[$key] = $value === '' ? null : $value;
            } else {
                if (! isset($data[$key]) || ! is_array($data[$key])) {
                    $data[$key] = [];
                }
                $data = &$data[$key];
            }
        }
    }

    /**
     * Add an item to an array field (prevents duplicates).
     */
    public function addToArray(string $path, string $value): void
    {
        if (trim($value) === '') {
            return;
        }

        $property = $this->nestedDataProperty();
        $keys = explode('.', $path);
        $data = &$this->{$property};

        foreach ($keys as $key) {
            if (! isset($data[$key])) {
                $data[$key] = [];
            }
            $data = &$data[$key];
        }

        if (is_array($data) && ! in_array($value, $data, true)) {
            $data[] = $value;
        }
    }

    /**
     * Remove an item from an array field by index.
     */
    public function removeFromArray(string $path, int $index): void
    {
        $property = $this->nestedDataProperty();
        $keys = explode('.', $path);
        $data = &$this->{$property};

        foreach ($keys as $key) {
            if (! isset($data[$key])) {
                return;
            }
            $data = &$data[$key];
        }

        if (is_array($data) && isset($data[$index])) {
            unset($data[$index]);
            $data = array_values($data);
        }
    }
}
