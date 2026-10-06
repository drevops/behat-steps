<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that no parameter defaults to ''.
 *
 * NULL is the one value that means "not given", so an empty string stays a
 * value a caller passes on purpose. CONTRIBUTING.md states the rule.
 */
#[CoversNothing]
class OptionalParameterTest extends UnitTestCase {

  /**
   * Assert that no parameter of a type under `src/` defaults to ''.
   *
   * @param class-string $type
   *   The class, interface or trait to check.
   */
  #[DataProvider('dataProviderOptionalParametersDefaultToNull')]
  public function testOptionalParametersDefaultToNull(string $type): void {
    $reflection = static::reflect($type);
    $violations = [];

    foreach ($reflection->getMethods() as $method) {
      // A parent or a composed trait reports its methods too, so only the
      // methods declared in this file are the type's own.
      if ($method->getFileName() !== $reflection->getFileName()) {
        continue;
      }

      foreach ($method->getParameters() as $parameter) {
        if ($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() === '') {
          $violations[] = sprintf('%s(): $%s', $method->getName(), $parameter->getName());
        }
      }
    }

    $this->assertSame([], $violations, 'Default an optional parameter to NULL with a nullable type, never to an empty string: "?string $key = NULL", not "string $key = \'\'".');
  }

  public static function dataProviderOptionalParametersDefaultToNull(): array {
    return static::discoverSourceTypes();
  }

}
