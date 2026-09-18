<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Behat\Manager\DriverManager;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
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

    $this->assertSame($driver, $manager->getDriver('alpha'));
    $this->assertCount(1, $manager->getDrivers());
  }

  public function testConstructorLowercasesDriverNames(): void {
    $driver = $this->createDriverMock(TRUE);

    $manager = new DriverManager(['MY_DRIVER' => $driver]);

    $this->assertSame($driver, $manager->getDriver('my_driver'));
  }

  public function testRegisterDriverLowercasesName(): void {
    $driver = $this->createDriverMock(TRUE);
    $manager = new DriverManager();

    $manager->registerDriver('FooBar', $driver);

    $this->assertSame($driver, $manager->getDriver('foobar'));
  }

  public function testGetDriverReturnsDefaultDriver(): void {
    $driver = $this->createDriverMock(TRUE);
    $manager = new DriverManager();
    $manager->registerDriver('default', $driver);
    $manager->setDefaultDriverName('default');

    $this->assertSame($driver, $manager->getDriver());
  }

  public function testGetDriverThrowsWithoutDefault(): void {
    $manager = new DriverManager();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Specify a Drupal driver to get.');

    $manager->getDriver();
  }

  public function testGetDriverThrowsForUnregisteredName(): void {
    $manager = new DriverManager();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Driver "ghost" is not registered');

    $manager->getDriver('ghost');
  }

  public function testGetDriverBootstrapsWhenNeeded(): void {
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('isBootstrapped')->willReturn(FALSE);
    $driver->expects($this->once())->method('bootstrap');
    $manager = new DriverManager(['test' => $driver]);

    $manager->getDriver('test');
  }

  public function testGetDriverSkipsBootstrapWhenAlreadyBootstrapped(): void {
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('isBootstrapped')->willReturn(TRUE);
    $driver->expects($this->never())->method('bootstrap');
    $manager = new DriverManager(['test' => $driver]);

    $manager->getDriver('test');
  }

  public function testGetDriversReturnsEmptyByDefault(): void {
    $manager = new DriverManager();

    $this->assertSame([], $manager->getDrivers());
  }

  public function testGetDriversReturnsAllRegistered(): void {
    $driver_a = $this->createDriverMock(TRUE);
    $driver_b = $this->createDriverMock(TRUE);
    $manager = new DriverManager();
    $manager->registerDriver('a', $driver_a);
    $manager->registerDriver('b', $driver_b);

    $drivers = $manager->getDrivers();

    $this->assertCount(2, $drivers);
    $this->assertSame($driver_a, $drivers['a']);
    $this->assertSame($driver_b, $drivers['b']);
  }

  public function testSetDefaultDriverNameThrowsForUnregistered(): void {
    $manager = new DriverManager();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Driver "missing" is not registered.');

    $manager->setDefaultDriverName('missing');
  }

  public function testSetDefaultDriverNameLowercases(): void {
    $driver = $this->createDriverMock(TRUE);
    $manager = new DriverManager();
    $manager->registerDriver('mydriver', $driver);

    $manager->setDefaultDriverName('MyDriver');

    $this->assertSame($driver, $manager->getDriver());
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
   */
  protected function createDriverMock(bool $bootstrapped): DriverInterface {
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('isBootstrapped')->willReturn($bootstrapped);

    return $driver;
  }

}
