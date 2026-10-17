<?php

declare(strict_types=1);

namespace KSFII\AssumptionManagement\Tests;

use KSFII\AssumptionManagement\AssumptionDefinition;
use KSFII\AssumptionManagement\AssumptionManager;
use KSFII\AssumptionManagement\AssumptionStorageInterface;
use KSFII\AssumptionManagement\AssumptionStorageException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \KSFII\AssumptionManagement\AssumptionDefinition
 */
class AssumptionDefinitionTest extends TestCase
{
    public function testCreateAssumption(): void
    {
        $assump = new AssumptionDefinition(
            key: 'test.assumption',
            category: 'test',
            value: 100,
            is_numeric: true,
            description: 'Test assumption',
            owner: 'test',
            created_by: 'test',
            created_date: new \DateTimeImmutable()
        );

        $this->assertEquals('test.assumption', $assump->key);
        $this->assertEquals('test', $assump->category);
        $this->assertEquals(100, $assump->value);
        $this->assertTrue($assump->is_numeric);
        $this->assertEquals('Test assumption', $assump->description);
    }

    public function testCreateNumericAssumption(): void
    {
        $assump = new AssumptionDefinition(
            key: 'tax.rate',
            category: 'economic',
            value: 0.21,
            is_numeric: true,
            description: 'Tax rate assumption',
            owner: 'system'
        );

        $this->assertTrue($assump->is_numeric);
    }

    public function testCreateStringAssumption(): void
    {
        $assump = new AssumptionDefinition(
            key: 'currency',
            category: 'macro',
            value: 'CAD',
            is_numeric: false,
            description: 'Currency symbol'
        );

        $this->assertFalse($assump->is_numeric);
    }
}

/**
 * @coversDefaultClass \KSFII\AssumptionManagement\AssumptionStorageInterface
 */
class AssumptionStorageInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(class_exists(\KSFII\AssumptionManagement\AssumptionStorageInterface::class));
    }

    public function testInterfaceHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(\KSFII\AssumptionManagement\AssumptionStorageInterface::class);
        
        $methods = ['store', 'retrieve', 'listAll', 'delete', 'exists'];
        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "Method {$method}() not found");
        }
    }
}

/**
 * @coversDefaultClass \KSFII\AssumptionManagement\AssumptionManager
 */
class AssumptionManagerTest extends TestCase
{
    private function createMockStorage(): AssumptionStorageInterface
    {
        return $this->createMock(\KSFII\AssumptionManagement\AssumptionStorageInterface::class);
    }

    public function testConstructor(): void
    {
        $storage = $this->createMockStorage();
        $manager = new AssumptionManager($storage);

        $this->assertInstanceOf(\KSFII\AssumptionManagement\AssumptionManager::class, $manager);
    }

    public function testConstructorWithLogger(): void
    {
        $storage = $this->createMockStorage();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $manager = new AssumptionManager($storage, $logger);

        $this->assertInstanceOf(\KSFII\AssumptionManagement\AssumptionManager::class, $manager);
    }
}