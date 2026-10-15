<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts the return contract of every capability create, update and delete.
 *
 * A teardown deletes whatever a scenario created without checking first, so
 * a create returns a stub and a delete returns nothing. An update and a copy
 * return a stub as a create does.
 *
 * CONTRIBUTING.md states the contract, including that a delete tolerates a
 * missing target.
 */
#[CoversNothing]
class CapabilityContractTest extends UnitTestCase {

  /**
   * Assert that a create, an update or a copy returns an entity stub.
   *
   * @param class-string $interface
   *   The capability interface declaring the method.
   * @param string $method
   *   The method name.
   */
  #[DataProvider('dataProviderCreateReturnsStub')]
  public function testCreateReturnsStub(string $interface, string $method): void {
    $type = static::reflect($interface)->getMethod($method)->getReturnType();

    $declared = $type instanceof \ReflectionType ? (string) $type : 'no return type';

    $this->assertSame(EntityStubInterface::class, $declared, sprintf('A capability create, update or copy returns the stub: "%s(): %s", not "%s(): %s".', $method, EntityStubInterface::class, $method, $declared));
  }

  public static function dataProviderCreateReturnsStub(): \Iterator {
    yield from static::collectCapabilityMethods('/^(?:create|place|update|duplicate)[A-Z]/');
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
    $type = static::reflect($interface)->getMethod($method)->getReturnType();

    $declared = $type instanceof \ReflectionType ? (string) $type : 'no return type';

    $this->assertSame('void', $declared, sprintf('A capability delete returns nothing and tolerates a missing target: "%s(): void", not "%s(): %s".', $method, $method, $declared));
  }

  public static function dataProviderDeleteReturnsVoid(): \Iterator {
    // An entity delete opens with its verb, while the config and state
    // deletes keep their subsystem prefix: 'deleteNode()', 'configDelete()'.
    yield from static::collectCapabilityMethods('/^delete[A-Z]|Delete$/');
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
  protected static function collectCapabilityMethods(string $pattern): \Iterator {
    $files = array_merge(glob(realpath(__DIR__ . '/../../../src/Backend/Capability') . '/*Interface.php') ?: [], glob(realpath(__DIR__ . '/../../../src/Backend/Drupal/Capability') . '/*Interface.php') ?: []);

    foreach ($files as $file) {
      $namespace = str_contains($file, '/Backend/Drupal/') ? 'DrevOps\\BehatSteps\\Backend\\Drupal\\Capability\\' : 'DrevOps\\BehatSteps\\Backend\\Capability\\';
      $interface = $namespace . basename($file, '.php');

      if (!interface_exists($interface)) {
        continue;
      }

      foreach (static::reflect($interface)->getMethods() as $method) {
        if (preg_match($pattern, $method->getName()) === 1) {
          yield basename($file, '.php') . '::' . $method->getName() => [$interface, $method->getName()];
        }
      }
    }
  }

}
