<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that capability methods name the verb before the entity.
 *
 * A capability interface carries no prefix, so the verb opens the name:
 * `createNode()`, not `nodeCreate()`. CONTRIBUTING.md states the convention.
 */
#[CoversNothing]
class CapabilityMethodNamingTest extends UnitTestCase {

  /**
   * Assert that a method carrying `Create` opens with it.
   *
   * @param class-string $interface
   *   The capability interface to check.
   */
  #[DataProvider('dataProviderCreateOpensName')]
  public function testCreateOpensName(string $interface): void {
    $violations = [];
    foreach ((new \ReflectionClass($interface))->getMethods() as $method) {
      $name = $method->getName();
      preg_match_all('/[A-Z][a-z0-9]*/', ucfirst($name), $words);

      if (!in_array('Create', $words[0], TRUE) || str_starts_with($name, 'create')) {
        continue;
      }

      $violations[] = $name;
    }

    $this->assertSame([], $violations, 'Open a capability method with its verb: "createNode", not "nodeCreate".');
  }

  public static function dataProviderCreateOpensName(): array {
    return static::discoverCapabilityInterfaces();
  }

  /**
   * Assert that every `create<Noun>()` has a `delete<Noun>()` beside it.
   *
   * A capability that creates an entity also deletes it, so teardown removes
   * the entity through the capability that created it.
   *
   * @param class-string $interface
   *   The capability interface to check.
   */
  #[DataProvider('dataProviderCreateHasDelete')]
  public function testCreateHasDelete(string $interface): void {
    $reflection = new \ReflectionClass($interface);

    $violations = [];
    foreach ($reflection->getMethods() as $method) {
      $name = $method->getName();

      if (!str_starts_with($name, 'create')) {
        continue;
      }

      $delete = 'delete' . substr($name, strlen('create'));

      if ($reflection->hasMethod($delete)) {
        continue;
      }

      $violations[] = sprintf('%s() has no %s()', $name, $delete);
    }

    $this->assertSame([], $violations, 'Pair every capability "create<Noun>()" with a "delete<Noun>()" on the same interface: "createNode" with "deleteNode".');
  }

  public static function dataProviderCreateHasDelete(): array {
    return static::discoverCapabilityInterfaces();
  }

  /**
   * Return every capability interface, keyed by its short name.
   *
   * @return array<string, array{string}>
   *   Fully qualified interface names, as data provider rows.
   */
  protected static function discoverCapabilityInterfaces(): array {
    $files = glob(dirname(__DIR__, 3) . '/src/Backend/Capability/*Interface.php') ?: [];

    $interfaces = [];
    foreach ($files as $file) {
      $name = basename($file, '.php');
      $interfaces[$name] = ['DrevOps\\BehatSteps\\Backend\\Capability\\' . $name];
    }

    ksort($interfaces);

    return $interfaces;
  }

}
