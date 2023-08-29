# Resilient Client

[![Latest Stable Version](http://poser.pugx.org/endisha/resilient-client/v)](https://packagist.org/packages/endisha/resilient-client)
[![Total Downloads](http://poser.pugx.org/endisha/resilient-client/downloads)](https://packagist.org/packages/endisha/resilient-client)

Resilient Client is a PHP package designed to implement a circuit breaker pattern to enhance stability and prevent cascading failure requests.

### What is circuit breaker pattern?

The Circuit Breaker is a design pattern used in software development. It is used to detect and prevent cascading request failures due to slow network connectivity, timeouts, unavailability, or unexpected system difficulties.

## Requirements

- PHP ^8.1 or higher

## Installation

You can install the package via Composer by running the following command:

```
composer require endisha/resilient-client
```

## Usage

To use the package in your project, include the Composer autoload file:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

Implementing the circuit breaker pattern:

```php
use ResilientClient\CircuitBreaker;
use ResilientClient\CircuitBreaker\Adapters\FileStorageAdapter;

$adapter = new FileStorageAdapter;
$adapter->setPath(__DIR__ . '/storage/');

$circuitBreaker = new CircuitBreaker($adapter);
// The maximum number of consecutive failures that can occur before the circuit breaker trips and enters a [failed] state
$circuitBreaker->setFailureThreshold(3);
// The duration that the circuit breaker waits in an [open] state before allowing test requests to determine if the service has recovered (In seconds)
$circuitBreaker->setResetTimeout(300);
```

The `CircuitBreaker` constructor also allows setting a custom key as the second parameter; the default value is `default`


```php
$circuitBreaker = new CircuitBreaker($adapter, 'github-cb');
```

## Adapters

The package supports two types of adapters, each serving as a method of state storage for the circuit breaker pattern:

* File Storage Adapter: Stores states within files.
* PDO Database Adapter: Uses PDO to store states in a database.

#### File Storage Adapter

To use the File Storage Adapter and save circuit states to files use `FileStorageAdapter` and follow these steps:

```php
use ResilientClient\CircuitBreaker\Adapters\FileStorageAdapter;
$adapter = new FileStorageAdapter;
$adapter->setPath('path/to/storage');
```

#### Database (PDO) Adapter

To use the Database (PDO) Adapter and work with PDO to save states in the `circuit_breaker` table, you need to create the custom table first:

```sql
CREATE TABLE `circuit_breaker` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `state` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `failure_count` int(11) NOT NULL,
  `last_failure_time` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
```

Create an instance of `PDODatabaseAdapter` class

```php
use ResilientClient\CircuitBreaker\Adapters\PDODatabaseAdapter;
use PDO;

// PDO connection
$pdo = new PDO("mysql:host=localhost;dbname=name", 'user', 'pass');
// Use PDODatabaseAdapter
$adapter = new PDODatabaseAdapter($pdo);
$adapter->setTable('circuit_breaker'); //table: circuit_breaker
```

## How it Works

- To check if the circuit is open (not available), use:
```php
$circuit->isNotAvaiable();
```

- To mark a request as successful:
```php
$circuit->success();
```

- To mark a request as failed:
```php
$circuit->failure();
```

### Example

```php
use ResilientClient\CircuitBreaker\Adapters\FileStorageAdapter;
use ResilientClient\CircuitBreaker\CircuitBreaker;
use ResilientClient\CircuitBreaker\Exceptions\CircuitBreakerException;

$adapter = new FileStorageAdapter;
$adapter->setPath(__DIR__ . '/storage/');

$circuitBreaker = new CircuitBreaker($adapter);
$circuitBreaker->setFailureThreshold(3);
$circuitBreaker->setResetTimeout(300);

try {

    if ($circuitBreaker->isNotAvailable()) {
        throw new CircuitBreakerException('Service is unreachable');
    }

    // Send request
    // ....
    $successRequest = true;
    // ....

    if ($successRequest) {
        $circuitBreaker->success();
    } else {
        $circuitBreaker->failure();
    }
} catch (\CircuitBreakerException $e) {
    echo $e->getMessage();
}
```

## Guzzle Integration

The package supports integration with Guzzle through the `CircuitBreakerGuzzleMiddleware` middleware. This middleware is automatically implemented when `ConnectException` exceptions are thrown due to problems connecting to remote servers or establishing connections. To implement it, follow these steps:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use ResilientClient\CircuitBreaker\Middleware\CircuitBreakerGuzzleMiddleware;

$stack = HandlerStack::create(new CurlHandler());
$middleware = new CircuitBreakerGuzzleMiddleware($circuitBreaker);
$stack->push($middleware);

$args['handler'] = $stack;
$client = new Client($args);
// etc..
```

Additionally, you can specify certain HTTP status codes as failure requests. For example, you might want to treat errors like 301 (Moved Permanently) and 302 (Found) as failure responses:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use ResilientClient\CircuitBreaker\Middleware\CircuitBreakerGuzzleMiddleware;

$stack = HandlerStack::create(new CurlHandler());
$middleware = new CircuitBreakerGuzzleMiddleware($circuitBreaker);
$middleware->setAsFailureRequestCodes(301, 302);
$stack->push($middleware);

$args['handler'] = $stack;
$client = new Client($args);
// etc..
```

#### Handling Exceptions

When the circuit breaker is in an open state, it throws the `ResilientClient\CircuitBreaker\Exceptions\CircuitBreakerException` exception class. This exception serves as a signal that the circuit is currently open and requests to the service are not allowed. You can catch this exception to implement custom handling logic in your application when the circuit is open.

#### Example usage:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use ResilientClient\CircuitBreaker\Adapters\FileStorageAdapter;
use ResilientClient\CircuitBreaker\CircuitBreaker;
use ResilientClient\CircuitBreaker\Middleware\CircuitBreakerGuzzleMiddleware;

$adapter = new FileStorageAdapter;
$adapter->setPath(__DIR__ . '/storage/');

$circuitBreaker = new CircuitBreaker($adapter);
$circuitBreaker->setFailureThreshold(3);
$circuitBreaker->setResetTimeout(300);

$stack = HandlerStack::create(new CurlHandler());

$middleware = new CircuitBreakerGuzzleMiddleware($circuitBreaker);
$middleware->setAsFailureRequestCodes(301, 302);
$stack->push($middleware);

$args['handler'] = $stack;
$client = new Client($args);
// etc..
```

## Tests
```
composer test
```

## License

The Resilient Client package is open-source software licensed under the [MIT](https://opensource.org/licenses/MIT) License.

