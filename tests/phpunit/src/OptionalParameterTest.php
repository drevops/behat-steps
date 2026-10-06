<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that an optional string parameter defaults to NULL, never to ''.
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

  /**
   * Provides every class, interface and trait declared under `src/`.
   *
   * @return array<string, array{string}>
   *   Fully qualified type names, keyed by the path relative to `src/`.
   */
  public static function dataProviderOptionalParametersDefaultToNull(): array {
    $root = dirname(__DIR__, 3) . '/src';
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

    $types = [];
    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));
      $type = 'DrevOps\\BehatSteps\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

      if (!class_exists($type) && !interface_exists($type) && !trait_exists($type)) {
        continue;
      }

      $types[$relative] = [$type];
    }

    ksort($types);

    return $types;
  }

}
