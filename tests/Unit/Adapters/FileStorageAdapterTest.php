<?php

declare(strict_types=1);

namespace ResilientClientTests\Unit\Adapters;

use PHPUnit\Framework\TestCase;
use ResilientClient\CircuitBreaker\Adapters\FileStorageAdapter;
use ResilientClient\CircuitBreaker\Exceptions\MissingStorageDirectoryException;

class FileStorageAdapterTest extends TestCase
{
    use AdaptersTrait;

    private string $testDir =  __DIR__ . '/temp';
    private FileStorageAdapter $adapter;


    protected function setUp(): void
    {
        $this->createStorageDirectory();

        $adapter = new FileStorageAdapter();
        $adapter->setPath($this->testDir);
        $adapter->setKey('default');
        $this->adapter = $adapter;
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->testDir);
    }

    public function testLoadStates(): void
    {
        $data = $this->createStateData();

        $this->adapter->saveState($data);

        $loadedData = $this->adapter->loadState();

        $this->assertEquals($data, $loadedData);
    }

    public function testSaveState(): void
    {
        $data = $this->createStateData();

        $this->adapter->saveState($data);

        $loadedData = $this->adapter->loadState();

        $this->assertEquals($data, $loadedData);
    }

    public function testMissingStorageDirectoryException(): void
    {
        $this->expectException(MissingStorageDirectoryException::class);

        $this->adapter->setPath('/nonexistent_directory');
        $this->adapter->loadState();
    }

    public function testSanitizeKeyRemovesSpecialCharactersAndConvertsToLowercase()
    {
        $inputKey = 'This is an Example Key!@#';
        $expectedResult = 'this_is_an_example_key';

        $result = $this->invokeMethod(new FileStorageAdapter(), 'sanitizeKey', [$inputKey]);
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
