<?php

declare(strict_types=1);

namespace ResilientClientTests\Unit\Adapters;

use PDO;
use PHPUnit\Framework\TestCase;
use ResilientClient\CircuitBreaker\Adapters\PDODatabaseAdapter;

class PDODatabaseAdapterTest extends TestCase
{
    use AdaptersTrait;

    private PDO $pdo;
    private PDODatabaseAdapter $adapter;
    private string $table = 'circuit_breaker';

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->createTable();

        $adapter = new PDODatabaseAdapter($this->pdo);
        $adapter->setKey('default');
        $adapter->setTable($this->table);
        $this->adapter = $adapter;
    }

    public function testLoadStateEmpty(): void
    {
        $loadedState = $this->adapter->loadState();

        $this->assertEmpty($loadedState);
    }

    public function testLoadState(): void
    {
        $this->createTestRecord();

        $loadedState = $this->adapter->loadState();

        $expectedState = $this->createStateData('CLOSED', 2, time());

        $this->assertEquals($expectedState, $loadedState);
    }

    public function testSaveStateInsert(): void
    {
        $state = $this->createStateData('CLOSED', 1, time());

        $this->adapter->saveState($state);

        $loadedState = $this->adapter->loadState();

        $this->assertEquals($state, $loadedState);
    }

    public function testSaveStateUpdate(): void
    {
        $this->createTestRecord();

        $updatedState = $this->createStateData('CLOSED', 2, time());

        $this->adapter->saveState($updatedState);

        $loadedState = $this->adapter->loadState();

        $this->assertEquals($updatedState, $loadedState);
    }

    public function testSanitizeKeyRemovesSpecialCharactersAndConvertsToLowercase()
    {
        $inputKey = 'This is an Example Key!@#';
        $expectedResult = 'this_is_an_example_key';
        $result = $this->invokeMethod($this->adapter, 'sanitizeKey', [$inputKey]);

        $this->assertEquals($expectedResult, $result);
    }

    private function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
