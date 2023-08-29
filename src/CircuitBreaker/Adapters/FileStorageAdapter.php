<?php

namespace ResilientClient\CircuitBreaker\Adapters;

use ResilientClient\CircuitBreaker\Exceptions\MissingStorageDirectoryException;

class FileStorageAdapter implements StorageAdapterInterface
{
    private string $path = __DIR__;

    private string $key = 'default';

    public function setPath(string $path): void
    {
        if (!empty($path)) {
            $this->path = $path;
        }
    }

    public function setKey(string $key = 'default'): void
    {
        if (!empty($key)) {
            $this->key = $this->sanitizeKey($key);
        }
    }

    public function loadState(): array
    {
        $storageFile = $this->getStorageFile();
        return $this->getData($storageFile);
    }

    public function saveState(array $state): void
    {
        $storageFile = $this->getStorageFile();
        $this->saveData($storageFile, $state);
    }

    /**
     * @param array<string, int|string|null> $state The array containing the updated state.
     * @return void
     */
    private function saveData(string $storageFile, array $state): void
    {
        $fp = fopen($storageFile, 'w');
        if ($fp) {
            fwrite($fp, serialize($state));
            fclose($fp);
        }
    }

    /**
     * @return array<string, int|string|null> $state The array containing the updated state.
     */
    private function getData(string $storageFile): array
    {
        $data = [];
        if (file_exists($storageFile)) {
            $fp = fopen($storageFile, 'r');
            $contents = fread($fp, filesize($storageFile));
            $data = unserialize($contents);
            fclose($fp);
        }
        return $data;
    }

    private function getStorageFile(): string
    {
        if (!file_exists($this->path)) {
            throw new MissingStorageDirectoryException('Storage directory does not exist');
        }

        $path = $this->path . '/circuit_breaker';
        if (!empty($this->key)) {
            $path .= '.' . $this->key;
        }
        return $path;
    }

    private function sanitizeKey(string $key): string
    {
        return rtrim(preg_replace('/_+/', '_', preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($key))), '_');
    }
}
