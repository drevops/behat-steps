<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts the return contract of every capability create and delete method.
 *
 * A teardown deletes whatever a scenario created without checking first, so
 * a create hands back a stub and a delete returns nothing. CONTRIBUTING.md
 * states the contract, including that a delete tolerates a missing target.
 */
#[CoversNothing]
class CapabilityContractTest extends UnitTestCase {

  /**
   * Assert that a create method returns an entity stub.
   *
   * @param class-string $interface
   *   The capability interface declaring the method.
   * @param string $method
   *   The method name.
   */
  #[DataProvider('dataProviderCreateReturnsStub')]
  public function testCreateReturnsStub(string $interface, string $method): void {
    $type = (new \ReflectionMethod($interface, $method))->getReturnType();

    $declared = $type instanceof \ReflectionType ? (string) $type : 'no return type';

    $this->assertSame(EntityStubInterface::class, $declared, sprintf('A capability create returns the stub: "%s(): %s", not "%s(): %s".', $method, EntityStubInterface::class, $method, $declared));
  }

  public static function dataProviderCreateReturnsStub(): \Iterator {
    yield from static::capabilityMethods('/(?:Create|Place)$/');
  }

  /**
   * Assert that a delete method returns nothing.
   *
   * @param class-string $interface
   *   The capability interface declaring the method.
   * @param string $method
   *   The method name.
   */
  #[DataProvider('dataProviderDeleteReturnsVoid')]
  public function testDeleteReturnsVoid(string $interface, string $method): void {
    $type = (new \ReflectionMethod($interface, $method))->getReturnType();

    $declared = $type instanceof \ReflectionType ? (string) $type : 'no return type';

    $this->assertSame('void', $declared, sprintf('A capability delete returns nothing and tolerates a missing target: "%s(): void", not "%s(): %s".', $method, $method, $declared));
  }

  public static function dataProviderDeleteReturnsVoid(): \Iterator {
    yield from static::capabilityMethods('/Delete$/');
  }

  /**
   * Yield the capability methods whose name matches a pattern.
   *
   * @param string $pattern
   *   The regular expression a method name must match.
   *
   * @return \Iterator<string, array{class-string, string}>
   *   Interface and method name pairs, keyed by 'Interface::method'.
   */
  protected static function capabilityMethods(string $pattern): \Iterator {
    $files = glob(realpath(__DIR__ . '/../../../src/Backend/Capability') . '/*Interface.php') ?: [];

    foreach ($files as $file) {
      $interface = 'DrevOps\\BehatSteps\\Backend\\Capability\\' . basename($file, '.php');

      if (!interface_exists($interface)) {
        continue;
      }

      foreach ((new \ReflectionClass($interface))->getMethods() as $method) {
        if (preg_match($pattern, $method->getName()) === 1) {
          yield basename($file, '.php') . '::' . $method->getName() => [$interface, $method->getName()];
        }
      }
    }
  }

}
