<?php

declare(strict_types=1);

namespace ResilientClientTests\Unit\Middleware;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ResilientClient\CircuitBreaker\Adapters\StorageAdapterInterface;
use ResilientClient\CircuitBreaker\CircuitBreaker;
use ResilientClient\CircuitBreaker\Exceptions\CircuitBreakerException;
use ResilientClient\CircuitBreaker\Middleware\CircuitBreakerGuzzleMiddleware;

class CircuitBreakerGuzzleMiddlewareTest extends TestCase
{
    public function testSuccessfulRequest(): void
    {
        $expectedStatusCode = 200;

        $mockHandler = new MockHandler([new Response(200)]);
        $handlerStack = HandlerStack::create($mockHandler);

        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $circuitBreaker = new CircuitBreaker($adapterMock);
        $circuitBreaker->setFailureThreshold(1);
        $circuitBreaker->setResetTimeout(300);

        $handlerStack->push(new CircuitBreakerGuzzleMiddleware($circuitBreaker));

        $client = new Client(['handler' => $handlerStack]);

        $response = $client->get($this->getStatsUrl($expectedStatusCode));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($circuitBreaker->isNotAvailable());

        $circuitBreaker->failure();
        $this->assertTrue($circuitBreaker->isNotAvailable());
    }

    public function testCircuitBreakerFailureRequest(): void
    {
        $expectedStatusCode = 200;

        $mockHandler = new MockHandler([
            new ConnectException('Connection error', new Request('GET', $this->getStatsUrl($expectedStatusCode)))
        ]);
        $handlerStack = HandlerStack::create($mockHandler);

        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $circuitBreaker = new CircuitBreaker($adapterMock);
        $circuitBreaker->setFailureThreshold(1);
        $circuitBreaker->setResetTimeout(300);
        $circuitBreaker->failure();
        $circuitBreaker->failure();

        $handlerStack->push(new CircuitBreakerGuzzleMiddleware($circuitBreaker));

        $client = new Client(['handler' => $handlerStack]);

        $this->expectException(CircuitBreakerException::class);
        $client->get($this->getStatsUrl(200));

        $this->assertTrue($circuitBreaker->isNotAvailable());
    }

    public function testConnectExceptionFailureRequest(): void
    {
        $expectedStatusCode = 200;

        $mockHandler = new MockHandler([
            new ConnectException('Connection error', new Request('GET', $this->getStatsUrl($expectedStatusCode)))
        ]);
        $handlerStack = HandlerStack::create($mockHandler);

        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $circuitBreaker = new CircuitBreaker($adapterMock);
        $circuitBreaker->setFailureThreshold(1);
        $circuitBreaker->setResetTimeout(300);

        $handlerStack->push(new CircuitBreakerGuzzleMiddleware($circuitBreaker));

        $client = new Client(['handler' => $handlerStack]);

        $this->expectException(ConnectException::class);
        $client->get($this->getStatsUrl(200));

        $this->assertTrue($circuitBreaker->isNotAvailable());
    }

    public function testSetAsFailureRequestWithCustomCodes(): void
    {
        $expectedStatusCode = 302;

        // Mock a successful response
        $mockHandler = new MockHandler([
            new Response($expectedStatusCode),
            new Response($expectedStatusCode)
        ]);
        $handlerStack = HandlerStack::create($mockHandler);

        $adapterMock = $this->createMock(StorageAdapterInterface::class);
        $circuitBreaker = new CircuitBreaker($adapterMock);
        $circuitBreaker->setFailureThreshold(1);
        $circuitBreaker->setResetTimeout(300);

        $middleware = new CircuitBreakerGuzzleMiddleware($circuitBreaker);
        $middleware->setAsFailureRequestCodes($expectedStatusCode);
        $handlerStack->push($middleware);

        $client = new Client(['handler' => $handlerStack]);

        $this->expectException(CircuitBreakerException::class);

        for ($i = 0; $i < 2; $i++) {
            $client->get($this->getStatsUrl($expectedStatusCode));
        }

        $this->assertTrue($circuitBreaker->isNotAvailable());
    }

    private function getStatsUrl($code)
    {
        return 'https://httpstat.us/' . $code;
    }
}
