<?php

declare(strict_types=1);

namespace ResilientClientTests\Unit\Adapters;

trait AdaptersTrait
{
    private function deleteDirectory(string $path): void
    {
        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $path . '/' . $file;
                    if (is_dir($filePath)) {
                        $this->deleteDirectory($filePath);
                    } else {
                        unlink($filePath);
                    }
                }
            }
            rmdir($path);
        }
    }

    private function createStorageDirectory()
    {
        mkdir($this->testDir);
    }

    private function createTestRecord(): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO `{$this->table}` (`key`, `state`, `failure_count`, `last_failure_time`, `updated_at`) VALUES (:key, :state, :failureCount, :lastFailureTime, :updatedAt)");

        $parameters = [
            ':key' => 'default',
            ':updatedAt' => date('Y-m-d H:i:s'),
        ];

        $data = $this->createStateData('CLOSED', 2, time());
        foreach ($data as $key => $value) {
            $parameters[':' . $key] = $value;
        }

        $stmt->execute($parameters);
    }

    private function createTable(): void
    {
        $createTableSQL = "CREATE TABLE `{$this->table}` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `key` TEXT NOT NULL DEFAULT '',
            `state` TEXT NOT NULL DEFAULT '',
            `failure_count` INTEGER NOT NULL,
            `last_failure_time` INTEGER,
            `updated_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($createTableSQL);
    }

    private function createStateData($state = 'CLOSED', $failureCount = 1, $lastFailureTime = null): array
    {
        return [
            'state' => $state,
            'failureCount' => $failureCount,
            'lastFailureTime' => $lastFailureTime ?: time(),
        ];
    }
}
