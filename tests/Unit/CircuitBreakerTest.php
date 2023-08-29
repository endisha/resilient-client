<?php

declare(strict_types=1);

namespace ResilientClientTests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ResilientClient\CircuitBreaker\Adapters\StorageAdapterInterface;
use ResilientClient\CircuitBreaker\CircuitBreaker;

class CircuitBreakerTest extends TestCase
{
    public function testIsAvailableWhenCircuitIsClosed(): void
    {
        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $adapterMock->method('loadState')->willReturn([
            'state' => 'CLOSED',
            'lastFailureTime' => time()
        ]);

        $circuitBreaker = new CircuitBreaker($adapterMock);
        $isNotAvailable = $circuitBreaker->isNotAvailable();
        $this->assertfalse($isNotAvailable);
    }

    public function testIsAvailableWhenCircuitIsHalfOpen(): void
    {
        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $adapterMock->method('loadState')->willReturn([
            'state' => 'HALF_OPEN',
            'lastFailureTime' => time()
        ]);

        $circuitBreaker = new CircuitBreaker($adapterMock);
        $isNotAvailable = $circuitBreaker->isNotAvailable();
        $this->assertfalse($isNotAvailable);

        $circuitBreaker = new CircuitBreaker($adapterMock);
        $isNotAvailable = $circuitBreaker->isNotAvailable();
        $this->assertfalse($isNotAvailable);
    }

    public function testIsNotAvailableWhenCircuitIsOpen(): void
    {
        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $adapterMock->method('loadState')->willReturn([
            'state' => 'OPEN',
            'lastFailureTime' => time()
        ]);

        $circuitBreaker = new CircuitBreaker($adapterMock);
        $circuitBreaker->failure();
        $isNotAvailable = $circuitBreaker->isNotAvailable();
        $this->assertTrue($isNotAvailable);
    }

    public function testIsNotAvailableWhenCircuitChangedFromOpenToClosedState(): void
    {
        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $adapterMock->method('loadState')->willReturn([
            'state' => 'OPEN',
            'lastFailureTime' => time() - 10000
        ]);

        $circuitBreaker = new CircuitBreaker($adapterMock);
        $circuitBreaker->failure();
        $isNotAvailable = $circuitBreaker->isNotAvailable();
        $this->assertFalse($isNotAvailable);
    }

    public function testHalfOpenAfterResetSuccessRestoresClosedState()
    {
        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $adapterMock->method('loadState')->willReturn([
            'state' => 'HALF_OPEN',
            'lastFailureTime' => time()
        ]);

        $circuitBreaker = new CircuitBreaker($adapterMock);
        $circuitBreaker->failure();
        $isNotAvailable = $circuitBreaker->isNotAvailable();
        $this->assertTrue($isNotAvailable);

        $circuitBreaker->success();
        $isNotAvailable = $circuitBreaker->isNotAvailable();
        $this->assertFalse($isNotAvailable);
    }
}
