<?php

namespace ResilientClient\CircuitBreaker;

use ResilientClient\CircuitBreaker\Adapters\StorageAdapterInterface;

class CircuitBreaker
{
    private const STATE_CLOSED = 'CLOSED';

    private const STATE_OPEN = 'OPEN';

    private const STATE_HALF_OPEN = 'HALF_OPEN';

    private StorageAdapterInterface $adapter;

    private string $state = self::STATE_CLOSED;

    private int $failureCount = 0;

    private int $failureThreshold;

    private int $resetTimeout;

    private ?int $lastFailureTime = null;

    public function __construct(
        StorageAdapterInterface $adapter,
        ?string $key = 'default',
        int $failureThreshold = 3,
        int $resetTimeout = 20
    ) {
        $this->adapter = $adapter;
        $this->failureThreshold = $failureThreshold;
        $this->resetTimeout = $resetTimeout;
        $this->adapter->setKey($key);
        $this->load();
    }

    public function setFailureThreshold(int $threshold): void
    {
        $this->failureThreshold = $threshold;
    }

    public function setResetTimeout(int $timeout): void
    {
        $this->resetTimeout = $timeout;
    }

    public function isNotAvailable(): bool
    {
        if ($this->state !== self::STATE_OPEN) {
            return false;
        }

        if ($this->isResetTimeoutExpired()) {
            $this->reset();

            return false;
        }

        return true;
    }

    public function success(): void
    {
        $this->setClosed();
        $this->save();
    }

    public function failure(): void
    {
        if ($this->state == self::STATE_HALF_OPEN) {
            $this->setOpen();
        } else {
            $this->failureCount++;
            if ($this->failureCount >= $this->failureThreshold) {
                $this->setOpen();
            }
        }
        $this->save();
    }

    private function isResetTimeoutExpired(): bool
    {
        return time() - $this->lastFailureTime >= $this->resetTimeout;
    }

    private function reset(): void
    {
        $this->setHalfOpen();
        $this->save();
    }

    private function setOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->lastFailureTime = time();
    }

    private function setHalfOpen(): void
    {
        $this->state = self::STATE_HALF_OPEN;
        $this->failureCount = 0;
        $this->lastFailureTime = null;
    }

    private function setClosed(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
    }

    private function save(): void
    {
        $this->adapter->saveState([
            'state' => $this->state,
            'failureCount' => $this->failureCount,
            'lastFailureTime' => $this->lastFailureTime,
        ]);
    }

    private function load(): void
    {
        $state = $this->adapter->loadState();
        if (!empty($state)) {
            $this->state = $state['state'] ?? $this->state;
            $this->failureCount = $state['failureCount'] ?? (int) $this->failureCount;
            $this->lastFailureTime = $state['lastFailureTime'] ?? (int) $this->lastFailureTime;
        }
    }
}
