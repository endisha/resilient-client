<?php

namespace ResilientClient\CircuitBreaker\Adapters;

interface StorageAdapterInterface
{
    public function setKey(string $key): void;

    /**
     * Load the circuit breaker state from the storage.
     *
     * @return array<string, string> The loaded state.
     */
    public function loadState(): array;

    /**
     * Save the circuit breaker state to the storage.
     *
     * @param array<string, int|null> $state The state to save.
     *
     * @return void
     */
    public function saveState(array $state): void;
}
