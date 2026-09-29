<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistry;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the driver registry the extension hands to every context.
 */
#[CoversClass(DriverRegistry::class)]
class DriverRegistryTest extends TestCase {

  public function testImplementsInterface(): void {
    $registry = new DriverRegistry();

    $this->assertInstanceOf(DriverRegistryInterface::class, $registry);
  }

  public function testConstructorRegistersDrivers(): void {
    $driver = $this->createDriverMock(TRUE);

    $registry = new DriverRegistry(['Alpha' => $driver]);

    $this->assertSame(['alpha' => $driver], $registry->getDrivers());
  }

  public function testRegisterDriverLowercasesName(): void {
    $driver = $this->createDriverMock(TRUE);
    $registry = new DriverRegistry();

    $registry->registerDriver('FooBar', $driver);

    $this->assertArrayHasKey('foobar', $registry->getDrivers());
  }

  public function testGetDriversReturnsEmptyByDefault(): void {
    $registry = new DriverRegistry();

    $this->assertSame([], $registry->getDrivers());
  }

  public function testScenarioDriversAreEmptyByDefault(): void {
    $registry = new DriverRegistry();

    $this->assertSame([], $registry->getScenarioDrivers());
  }

  public function testScenarioDriversKeepTheOrderTheyWereGivenIn(): void {
    $registry = new DriverRegistry(['a' => $this->createDriverMock(TRUE), 'b' => $this->createDriverMock(TRUE)]);

    $registry->setScenarioDrivers(['second' => 'B', 'FIRST' => 'a']);

    $this->assertSame(['second' => 'b', 'first' => 'a'], $registry->getScenarioDrivers());
  }

  public function testSetScenarioDriversRejectsAnUnregisteredDriver(): void {
    $registry = new DriverRegistry(['a' => $this->createDriverMock(TRUE)]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Driver "ghost" is not registered. Registered drivers: a.');

    $registry->setScenarioDrivers(['ghost' => 'ghost']);
  }

  public function testSetScenarioDriversReportsWhenNothingIsRegistered(): void {
    $registry = new DriverRegistry();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Registered drivers: none.');

    $registry->setScenarioDrivers(['ghost' => 'ghost']);
  }

  public function testGetDriverResolvesTheTagName(): void {
    $driver = $this->createDriverMock(TRUE);
    $registry = new DriverRegistry(['acme-jsonapi' => $driver]);
    $registry->setScenarioDrivers(['api' => 'acme-jsonapi']);

    $this->assertSame($driver, $registry->getDriver('API'));
  }

  public function testGetDriverRejectsNameOutsideTheScenarioOrder(): void {
    $registry = new DriverRegistry(['a' => $this->createDriverMock(TRUE), 'b' => $this->createDriverMock(TRUE)]);
    $registry->setScenarioDrivers(['a' => 'a']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Driver "b" is not available to this scenario. Available drivers: a.');

    $registry->getDriver('b');
  }

  public function testGetDriverReportsAnEmptyScenarioOrder(): void {
    $registry = new DriverRegistry();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Available drivers: none.');

    $registry->getDriver('a');
  }

  public function testGetDriverBootstrapsWhenNeeded(): void {
    $driver = $this->createDriverMock(FALSE);
    $driver->expects($this->once())->method('bootstrap');
    $registry = new DriverRegistry(['test' => $driver]);
    $registry->setScenarioDrivers(['test' => 'test']);

    $registry->getDriver('test');
  }

  public function testGetDriverSkipsBootstrapWhenAlreadyBootstrapped(): void {
    $driver = $this->createDriverMock(TRUE);
    $driver->expects($this->never())->method('bootstrap');
    $registry = new DriverRegistry(['test' => $driver]);
    $registry->setScenarioDrivers(['test' => 'test']);

    $registry->getDriver('test');
  }

  public function testGetDriverForReturnsTheFirstDriverWithTheCapability(): void {
    $first = $this->createCacheDriverMock(TRUE);
    $second = $this->createCacheDriverMock(TRUE);
    $registry = new DriverRegistry(['first' => $first, 'second' => $second]);
    $registry->setScenarioDrivers(['first' => 'first', 'second' => 'second']);

    $this->assertSame($first, $registry->getDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetDriverForSkipsDriversWithoutTheCapability(): void {
    $plain = $this->createDriverMock(TRUE);
    $capable = $this->createCacheDriverMock(TRUE);
    $registry = new DriverRegistry(['plain' => $plain, 'capable' => $capable]);
    $registry->setScenarioDrivers(['plain' => 'plain', 'capable' => 'capable']);

    $this->assertSame($capable, $registry->getDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetDriverForBootstrapsOnlyTheDriverItReturns(): void {
    $plain = $this->createDriverMock(FALSE);
    $plain->expects($this->never())->method('bootstrap');

    $capable = $this->createCacheDriverMock(FALSE);
    $capable->expects($this->once())->method('bootstrap');

    $registry = new DriverRegistry(['plain' => $plain, 'capable' => $capable]);
    $registry->setScenarioDrivers(['plain' => 'plain', 'capable' => 'capable']);

    $registry->getDriverFor(CacheCapabilityInterface::class);
  }

  public function testGetDriverForNamesTheCapabilityAndTheOrderWhenNoneMatches(): void {
    $registry = new DriverRegistry(['plain' => $this->createDriverMock(TRUE)]);
    $registry->setScenarioDrivers(['plain' => 'plain']);

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No driver provides "%s". Drivers available to this scenario, in order: plain.', CacheCapabilityInterface::class));

    $registry->getDriverFor(CacheCapabilityInterface::class);
  }

  public function testHasCapabilityReadsTheScenarioOrderWithoutBootstrapping(): void {
    $capable = $this->createCacheDriverMock(FALSE);
    $capable->expects($this->never())->method('bootstrap');

    $registry = new DriverRegistry(['capable' => $capable]);
    $registry->setScenarioDrivers(['capable' => 'capable']);

    $this->assertTrue($registry->hasCapability(CacheCapabilityInterface::class));
    $this->assertFalse($registry->hasCapability(ContentCapabilityInterface::class));
  }

  public function testHasCapabilityIgnoresRegisteredDriverOutsideTheScenarioOrder(): void {
    $registry = new DriverRegistry(['capable' => $this->createCacheDriverMock(TRUE), 'plain' => $this->createDriverMock(TRUE)]);
    $registry->setScenarioDrivers(['plain' => 'plain']);

    $this->assertFalse($registry->hasCapability(CacheCapabilityInterface::class));
  }

  public function testGetResolvedDriverForReturnsNullUntilStepAsksForIt(): void {
    $registry = new DriverRegistry(['capable' => $this->createCacheDriverMock(TRUE)]);
    $registry->setScenarioDrivers(['capable' => 'capable']);

    $this->assertNull($registry->getResolvedDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetResolvedDriverForReturnsDriverTheScenarioResolved(): void {
    $capable = $this->createCacheDriverMock(TRUE);
    $registry = new DriverRegistry(['capable' => $capable]);
    $registry->setScenarioDrivers(['capable' => 'capable']);

    $registry->getDriverFor(CacheCapabilityInterface::class);

    $this->assertSame($capable, $registry->getResolvedDriverFor(CacheCapabilityInterface::class));
    $this->assertNull($registry->getResolvedDriverFor(ContentCapabilityInterface::class));
  }

  public function testGetResolvedDriverForAnswersInScenarioOrder(): void {
    $first = $this->createCacheDriverMock(TRUE);
    $second = $this->createCacheDriverMock(TRUE);
    $registry = new DriverRegistry(['first' => $first, 'second' => $second]);
    $registry->setScenarioDrivers(['first' => 'first', 'second' => 'second']);

    // Reached in the reverse of the scenario's order, as a step asking for a
    // capability only the second driver provides would do.
    $registry->getDriver('second');
    $registry->getDriver('first');

    $this->assertSame($first, $registry->getResolvedDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetResolvedDriverForSkipsAnUnreachedDriverAheadInTheOrder(): void {
    $first = $this->createCacheDriverMock(TRUE);
    $second = $this->createCacheDriverMock(TRUE);
    $registry = new DriverRegistry(['first' => $first, 'second' => $second]);
    $registry->setScenarioDrivers(['first' => 'first', 'second' => 'second']);

    $registry->getDriver('second');

    $this->assertSame($second, $registry->getResolvedDriverFor(CacheCapabilityInterface::class));
  }

  public function testTheNextScenarioForgetsWhatThePreviousOneResolved(): void {
    $capable = $this->createCacheDriverMock(TRUE);
    $registry = new DriverRegistry(['capable' => $capable]);
    $registry->setScenarioDrivers(['capable' => 'capable']);
    $registry->getDriverFor(CacheCapabilityInterface::class);

    $registry->setScenarioDrivers(['capable' => 'capable']);

    $this->assertNull($registry->getResolvedDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetEnvironmentReturnsNullByDefault(): void {
    $registry = new DriverRegistry();

    $this->assertNull($registry->getEnvironment());
  }

  public function testSetAndGetEnvironment(): void {
    $environment = $this->createMock(Environment::class);
    $registry = new DriverRegistry();

    $registry->setEnvironment($environment);

    $this->assertSame($environment, $registry->getEnvironment());
  }

  /**
   * Creates a driver double reporting the given bootstrap state.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The driver double.
   */
  protected function createDriverMock(bool $bootstrapped): DriverInterface&MockObject {
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('isBootstrapped')->willReturn($bootstrapped);

    return $driver;
  }

  /**
   * Creates a cache-capable driver double reporting the bootstrap state.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The driver double.
   */
  protected function createCacheDriverMock(bool $bootstrapped): DriverInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Driver\DriverInterface&\PHPUnit\Framework\MockObject\MockObject $driver */
    $driver = $this->createMockForIntersectionOfInterfaces([DriverInterface::class, CacheCapabilityInterface::class]);
    $driver->method('isBootstrapped')->willReturn($bootstrapped);

    return $driver;
  }

}
