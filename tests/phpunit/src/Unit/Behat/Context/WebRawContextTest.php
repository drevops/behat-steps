<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Behat\Context\DriverAwareInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManager;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the plumbing every shipped context inherits.
 */
#[CoversClass(WebRawContext::class)]
class WebRawContextTest extends UnitTestCase {

  public function testImplementsDriverAwareInterface(): void {
    $this->assertInstanceOf(DriverAwareInterface::class, new WebRawContext());
  }

  /**
   * Tests that an uninitialized context reports what it is missing.
   *
   * @param string $method
   *   The accessor to call on an uninitialized context.
   * @param string $expected_message
   *   The message the accessor is expected to throw with.
   */
  #[DataProvider('dataProviderUninitializedContextNamesMissingCollaborator')]
  public function testUninitializedContextNamesMissingCollaborator(string $method, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    (new WebRawContext())->$method();
  }

  public static function dataProviderUninitializedContextNamesMissingCollaborator(): \Iterator {
    yield 'driver manager' => ['getDriverManager', 'The driver manager is available only after Behat has initialized the context.'];
    yield 'authentication manager' => ['getAuthenticationManager', 'The authentication manager is available only after Behat has initialized the context.'];
  }

  public function testTheDriverComesFromTheManager(): void {
    $driver = $this->createMock(DriverInterface::class);
    $context = $this->createContext($driver);

    $this->assertSame($driver, $context->getDriver('test'));
    $this->assertSame($driver, $context->driverFor(DriverInterface::class));
  }

  public function testTheRandomGeneratorComesFromTheDriver(): void {
    $random = new Random();
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('getRandom')->willReturn($random);

    $this->assertSame($random, $this->createContext($driver)->getRandom());
  }

  public function testDriverForNamesTheCapabilityWhenNoDriverProvidesIt(): void {
    $context = $this->createContext($this->createMock(DriverInterface::class));

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('No driver provides "%s".', CoreCapabilityInterface::class));

    $context->driverFor(CoreCapabilityInterface::class);
  }

  public function testDriverForBootstrapsOnceAndReturnsTheDriver(): void {
    $driver = $this->createMock(DrupalDriverInterface::class);
    $driver->method('isBootstrapped')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $driver->expects($this->once())->method('bootstrap');

    $context = $this->createContext($driver);

    $first = $context->driverFor(CoreCapabilityInterface::class);
    $second = $context->driverFor(CoreCapabilityInterface::class);

    $this->assertSame($driver, $first);
    $this->assertSame($driver, $second);
  }

  /**
   * Builds an initialized context over the given driver.
   *
   * @param \DrevOps\BehatSteps\Driver\DriverInterface $driver
   *   The driver the manager hands out.
   */
  protected function createContext(DriverInterface $driver): WebRawContext {
    $driver_manager = new DriverManager(['test' => $driver]);
    $driver_manager->setScenarioDrivers(['test' => 'test']);
    $driver_manager->setEnvironment($this->createMock(Environment::class));

    $context = new WebRawContext();
    $context->setDriverManager($driver_manager);
    $context->setDispatcher($this->createHookDispatcher());
    $context->setAuthenticationManager($this->createMock(AuthenticationManagerInterface::class));

    return $context;
  }

}
