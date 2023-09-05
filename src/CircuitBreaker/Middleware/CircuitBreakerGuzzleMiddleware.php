<?php

namespace ResilientClient\CircuitBreaker\Middleware;

use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ResilientClient\CircuitBreaker\CircuitBreaker;
use ResilientClient\CircuitBreaker\Exceptions\CircuitBreakerException;
use Throwable;

class CircuitBreakerGuzzleMiddleware
{
    private CircuitBreaker $circuitBreaker;

    /** @var int[] */
    private array $customRequestCodes = [];

    /**
     * Constructs a new instance of the class.
     *
     * @param CircuitBreaker $circuitBreaker The circuit breaker object.
     */
    public function __construct(CircuitBreaker $circuitBreaker)
    {
        $this->circuitBreaker = $circuitBreaker;
    }

    /**
     * Sets the given array of request codes as failure request codes.
     *
     * @param int $codes The array of request codes to set.
     *
     * @return void
     */
    public function setAsFailureRequestCodes(int ...$codes): void
    {
        $this->customRequestCodes = $codes;
    }

    /**
     * Handle an incoming request.
     * and returns a promise that resolves to the response.
     *
     * @param callable $handler The callable handler to invoke.
     * @return callable A callable that takes a request and options, and returns a promise that resolves to a response.
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            if ($this->circuitBreaker->isNotAvailable()) {
                throw new CircuitBreakerException('Service is unreachable');
            }

            return $handler($request, $options)
                ->then(function (ResponseInterface $response) {
                    if ($this->isFailureCode($response->getStatusCode())) {
                        $this->circuitBreaker->failure();
                    } else {
                        $this->circuitBreaker->success();
                    }
                    return $response;
                }, function (Throwable $exception) {
                    if ($exception instanceof ConnectException) {
                        $this->circuitBreaker->failure();
                    }
                    throw $exception;
                });
        };
    }

    /**
     * Checks if a given code is a failure code.
     *
     * @param int $code The code to check.
     * @return bool Returns true if the code is a handled failure code, false otherwise.
     */
    private function isFailureCode(int $code): bool
    {
        return in_array($code, $this->customRequestCodes);
    }
}
