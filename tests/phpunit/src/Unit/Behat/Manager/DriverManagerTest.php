<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Behat\Manager\DriverManager;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
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
#[CoversClass(DriverManager::class)]
class DriverManagerTest extends TestCase {

  public function testImplementsInterface(): void {
    $manager = new DriverManager();

    $this->assertInstanceOf(DriverManagerInterface::class, $manager);
  }

  public function testConstructorRegistersDrivers(): void {
    $driver = $this->createDriverMock(TRUE);

    $manager = new DriverManager(['Alpha' => $driver]);

    $this->assertSame(['alpha' => $driver], $manager->getDrivers());
  }

  public function testRegisterDriverLowercasesName(): void {
    $driver = $this->createDriverMock(TRUE);
    $manager = new DriverManager();

    $manager->registerDriver('FooBar', $driver);

    $this->assertArrayHasKey('foobar', $manager->getDrivers());
  }

  public function testGetDriversReturnsEmptyByDefault(): void {
    $manager = new DriverManager();

    $this->assertSame([], $manager->getDrivers());
  }

  public function testScenarioDriversAreEmptyByDefault(): void {
    $manager = new DriverManager();

    $this->assertSame([], $manager->getScenarioDrivers());
  }

  public function testScenarioDriversKeepTheOrderTheyWereGivenIn(): void {
    $manager = new DriverManager(['a' => $this->createDriverMock(TRUE), 'b' => $this->createDriverMock(TRUE)]);

    $manager->setScenarioDrivers(['second' => 'B', 'FIRST' => 'a']);

    $this->assertSame(['second' => 'b', 'first' => 'a'], $manager->getScenarioDrivers());
  }

  public function testSetScenarioDriversRejectsAnUnregisteredDriver(): void {
    $manager = new DriverManager(['a' => $this->createDriverMock(TRUE)]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Driver "ghost" is not registered. Registered drivers: a.');

    $manager->setScenarioDrivers(['ghost' => 'ghost']);
  }

  public function testSetScenarioDriversReportsWhenNothingIsRegistered(): void {
    $manager = new DriverManager();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Registered drivers: none.');

    $manager->setScenarioDrivers(['ghost' => 'ghost']);
  }

  public function testGetDriverResolvesTheTagName(): void {
    $driver = $this->createDriverMock(TRUE);
    $manager = new DriverManager(['acme-jsonapi' => $driver]);
    $manager->setScenarioDrivers(['api' => 'acme-jsonapi']);

    $this->assertSame($driver, $manager->getDriver('API'));
  }

  public function testGetDriverRejectsNameOutsideTheScenarioOrder(): void {
    $manager = new DriverManager(['a' => $this->createDriverMock(TRUE), 'b' => $this->createDriverMock(TRUE)]);
    $manager->setScenarioDrivers(['a' => 'a']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Driver "b" is not available to this scenario. Available drivers: a.');

    $manager->getDriver('b');
  }

  public function testGetDriverReportsAnEmptyScenarioOrder(): void {
    $manager = new DriverManager();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Available drivers: none.');

    $manager->getDriver('a');
  }

  public function testGetDriverBootstrapsWhenNeeded(): void {
    $driver = $this->createDriverMock(FALSE);
    $driver->expects($this->once())->method('bootstrap');
    $manager = new DriverManager(['test' => $driver]);
    $manager->setScenarioDrivers(['test' => 'test']);

    $manager->getDriver('test');
  }

  public function testGetDriverSkipsBootstrapWhenAlreadyBootstrapped(): void {
    $driver = $this->createDriverMock(TRUE);
    $driver->expects($this->never())->method('bootstrap');
    $manager = new DriverManager(['test' => $driver]);
    $manager->setScenarioDrivers(['test' => 'test']);

    $manager->getDriver('test');
  }

  public function testGetDriverForReturnsTheFirstDriverWithTheCapability(): void {
    $first = $this->createCacheDriverMock(TRUE);
    $second = $this->createCacheDriverMock(TRUE);
    $manager = new DriverManager(['first' => $first, 'second' => $second]);
    $manager->setScenarioDrivers(['first' => 'first', 'second' => 'second']);

    $this->assertSame($first, $manager->getDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetDriverForSkipsDriversWithoutTheCapability(): void {
    $plain = $this->createDriverMock(TRUE);
    $capable = $this->createCacheDriverMock(TRUE);
    $manager = new DriverManager(['plain' => $plain, 'capable' => $capable]);
    $manager->setScenarioDrivers(['plain' => 'plain', 'capable' => 'capable']);

    $this->assertSame($capable, $manager->getDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetDriverForBootstrapsOnlyTheDriverItReturns(): void {
    $plain = $this->createDriverMock(FALSE);
    $plain->expects($this->never())->method('bootstrap');

    $capable = $this->createCacheDriverMock(FALSE);
    $capable->expects($this->once())->method('bootstrap');

    $manager = new DriverManager(['plain' => $plain, 'capable' => $capable]);
    $manager->setScenarioDrivers(['plain' => 'plain', 'capable' => 'capable']);

    $manager->getDriverFor(CacheCapabilityInterface::class);
  }

  public function testGetDriverForNamesTheCapabilityAndTheOrderWhenNoneMatches(): void {
    $manager = new DriverManager(['plain' => $this->createDriverMock(TRUE)]);
    $manager->setScenarioDrivers(['plain' => 'plain']);

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No driver provides "%s". Drivers available to this scenario, in order: plain.', CacheCapabilityInterface::class));

    $manager->getDriverFor(CacheCapabilityInterface::class);
  }

  public function testHasCapabilityReadsTheScenarioOrderWithoutBootstrapping(): void {
    $capable = $this->createCacheDriverMock(FALSE);
    $capable->expects($this->never())->method('bootstrap');

    $manager = new DriverManager(['capable' => $capable]);
    $manager->setScenarioDrivers(['capable' => 'capable']);

    $this->assertTrue($manager->hasCapability(CacheCapabilityInterface::class));
    $this->assertFalse($manager->hasCapability(ContentCapabilityInterface::class));
  }

  public function testHasCapabilityIgnoresRegisteredDriverOutsideTheScenarioOrder(): void {
    $manager = new DriverManager(['capable' => $this->createCacheDriverMock(TRUE), 'plain' => $this->createDriverMock(TRUE)]);
    $manager->setScenarioDrivers(['plain' => 'plain']);

    $this->assertFalse($manager->hasCapability(CacheCapabilityInterface::class));
  }

  public function testGetResolvedDriverForReturnsNullUntilStepAsksForIt(): void {
    $manager = new DriverManager(['capable' => $this->createCacheDriverMock(TRUE)]);
    $manager->setScenarioDrivers(['capable' => 'capable']);

    $this->assertNull($manager->getResolvedDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetResolvedDriverForReturnsDriverTheScenarioResolved(): void {
    $capable = $this->createCacheDriverMock(TRUE);
    $manager = new DriverManager(['capable' => $capable]);
    $manager->setScenarioDrivers(['capable' => 'capable']);

    $manager->getDriverFor(CacheCapabilityInterface::class);

    $this->assertSame($capable, $manager->getResolvedDriverFor(CacheCapabilityInterface::class));
    $this->assertNull($manager->getResolvedDriverFor(ContentCapabilityInterface::class));
  }

  public function testGetResolvedDriverForAnswersInScenarioOrder(): void {
    $first = $this->createCacheDriverMock(TRUE);
    $second = $this->createCacheDriverMock(TRUE);
    $manager = new DriverManager(['first' => $first, 'second' => $second]);
    $manager->setScenarioDrivers(['first' => 'first', 'second' => 'second']);

    // Reached in the reverse of the scenario's order, as a step asking for a
    // capability only the second driver provides would do.
    $manager->getDriver('second');
    $manager->getDriver('first');

    $this->assertSame($first, $manager->getResolvedDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetResolvedDriverForSkipsAnUnreachedDriverAheadInTheOrder(): void {
    $first = $this->createCacheDriverMock(TRUE);
    $second = $this->createCacheDriverMock(TRUE);
    $manager = new DriverManager(['first' => $first, 'second' => $second]);
    $manager->setScenarioDrivers(['first' => 'first', 'second' => 'second']);

    $manager->getDriver('second');

    $this->assertSame($second, $manager->getResolvedDriverFor(CacheCapabilityInterface::class));
  }

  public function testTheNextScenarioForgetsWhatThePreviousOneResolved(): void {
    $capable = $this->createCacheDriverMock(TRUE);
    $manager = new DriverManager(['capable' => $capable]);
    $manager->setScenarioDrivers(['capable' => 'capable']);
    $manager->getDriverFor(CacheCapabilityInterface::class);

    $manager->setScenarioDrivers(['capable' => 'capable']);

    $this->assertNull($manager->getResolvedDriverFor(CacheCapabilityInterface::class));
  }

  public function testGetEnvironmentReturnsNullByDefault(): void {
    $manager = new DriverManager();

    $this->assertNull($manager->getEnvironment());
  }

  public function testSetAndGetEnvironment(): void {
    $environment = $this->createMock(Environment::class);
    $manager = new DriverManager();

    $manager->setEnvironment($environment);

    $this->assertSame($environment, $manager->getEnvironment());
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
