<?php

namespace ResilientClient\CircuitBreaker\Adapters;

use PDO;
use PDOStatement;

class PDODatabaseAdapter implements StorageAdapterInterface
{
    private string $key = 'default';
    private string $table = 'circuit_breakers';
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function setKey(string $key): void
    {
        if (!empty($key)) {
            $this->key = $this->sanitizeKey($key);
        }
    }

    public function setTable(string $table): void
    {
        if (!empty($table)) {
            $this->table = $table;
        }
    }

    public function loadState(): array
    {
        $result = $this->getExistingState();

        if (!empty($result)) {
            return [
                'state' => $result['state'],
                'failureCount' => (int) $result['failure_count'],
                'lastFailureTime' => (int) $result['last_failure_time'],
            ];
        }

        return [];
    }

    public function saveState(array $state): void
    {
        $existing = $this->getExistingState();

        if (is_null($existing)) {
            $this->insertState($state);
        } else {
            $this->updateState($state);
        }
    }

    /**
     * @return array<string, mixed>|null The existing state.
     */
    private function getExistingState(): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE `key` = :key");
        $stmt->bindParam(':key', $this->key);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        return $result;
    }


    /**
     * @param array<string, int|string|null> $state The state to be inserted.
     * @return void
     */
    private function insertState(array $state): void
    {
        $stmt = $this->pdo->prepare($this->getInsertQuery());
        $stmt->bindParam(':key', $this->key);
        $this->bindCommonParams($stmt, $state);
        $stmt->execute();
    }

    /**
     * @param array<string, int|string|null> $state The array containing the updated state.
     * @return void
     */
    private function updateState(array $state): void
    {
        $stmt = $this->pdo->prepare($this->getUpdateQuery());
        $stmt->bindParam(':key', $this->key);
        $this->bindCommonParams($stmt, $state);
        $stmt->execute();
    }

    /**
     * @param PDOStatement $stmt The PDOStatement object to bind the parameters to.
     * @param array<string, string> $state An array containing the state parameters.
     *                     - 'state': The value of the state parameter.
     *                     - 'failureCount': The value of the failure_count parameter.
     *                     - 'lastFailureTime': The value of the last_failure_time parameter.
     *
     * @return void
     */
    private function bindCommonParams(PDOStatement $stmt, array $state): void
    {
        $stmt->bindParam(':state', $state['state']);
        $stmt->bindParam(':failureCount', $state['failureCount']);
        $stmt->bindParam(':lastFailureTime', $state['lastFailureTime']);
        $stmt->bindValue(':updatedAt', date('Y-m-d H:i:s'));
    }

    private function getInsertQuery(): string
    {
        return "INSERT INTO `{$this->table}` (`key`, `state`, `failure_count`, `last_failure_time`, `updated_at`) VALUES (:key, :state, :failureCount, :lastFailureTime, :updatedAt)";
    }

    private function getUpdateQuery(): string
    {
        return "UPDATE `{$this->table}` SET `state` = :state, `failure_count` = :failureCount, `last_failure_time` = :lastFailureTime, `updated_at` = :updatedAt WHERE `key` = :key";
    }

    private function sanitizeKey(string $key): string
    {
        return rtrim(preg_replace('/_+/', '_', preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($key))), '_');
    }
}
