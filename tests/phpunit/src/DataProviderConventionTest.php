<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Asserts that every data provider follows the settled provider conventions.
 *
 * A provider is named after the test it serves, declared after that test, and
 * declares the return type its body produces. Which of the 2 forms a class
 * uses is left to the class. CONTRIBUTING.md states the rules.
 */
#[CoversNothing]
class DataProviderConventionTest extends UnitTestCase {

  /**
   * Assert that a provider is named after the test it serves.
   *
   * The name is `dataProvider` followed by the test name without its `test`
   * prefix, so each provider serves exactly 1 test.
   *
   * @param class-string $class
   *   The test class to check.
   */
  #[DataProvider('dataProviderProvidersAreNamedAfterTheirTest')]
  public function testProvidersAreNamedAfterTheirTest(string $class): void {
    $reflection = new \ReflectionClass($class);

    $violations = [];

    foreach (static::ownMethods($reflection) as $test) {
      $expected ='dataProvider' . substr($test->getName(), strlen('test'));

      foreach (static::providerNames($test) as $name) {
        if ($name !== $expected) {
          $violations[] = sprintf('%s() is served by %s(), not %s().', $test->getName(), $name, $expected);
        }
      }
    }

    $this->assertSame([], $violations, sprintf('%s names a data provider against the convention in CONTRIBUTING.md.', $reflection->getShortName()));
  }

  public static function dataProviderProvidersAreNamedAfterTheirTest(): array {
    return static::discoverTestClasses();
  }

  /**
   * Assert that a provider is declared after the test it serves.
   *
   * Line numbers compare only within 1 file, so a provider declared outside
   * the test's own class is skipped.
   *
   * @param class-string $class
   *   The test class to check.
   */
  #[DataProvider('dataProviderProvidersAreDeclaredAfterTheirTest')]
  public function testProvidersAreDeclaredAfterTheirTest(string $class): void {
    $reflection = new \ReflectionClass($class);

    $violations = [];

    foreach (static::ownMethods($reflection) as $test) {
      foreach (static::providerNames($test) as $name) {
        if (!$reflection->hasMethod($name)) {
          continue;
        }

        $provider = $reflection->getMethod($name);

        if ($provider->getDeclaringClass()->getName() === $reflection->getName() && $provider->getStartLine() < $test->getEndLine()) {
          $violations[] = sprintf('%s() is declared before %s(), the test it serves.', $name, $test->getName());
        }
      }
    }

    $this->assertSame([], $violations, sprintf('%s places a data provider against the convention in CONTRIBUTING.md.', $reflection->getShortName()));
  }

  public static function dataProviderProvidersAreDeclaredAfterTheirTest(): array {
    return static::discoverTestClasses();
  }

  /**
   * Assert that a provider's return type matches the form of its body.
   *
   * A generator declares `\Iterator` and any other provider declares `array`.
   * An abstract provider has no body, so its implementations are checked
   * instead.
   *
   * @param class-string $class
   *   The test class to check.
   */
  #[DataProvider('dataProviderProviderReturnTypesMatchTheirBody')]
  public function testProviderReturnTypesMatchTheirBody(string $class): void {
    $reflection = new \ReflectionClass($class);

    $violations = [];

    foreach (static::ownMethods($reflection) as $provider) {
      if (!str_starts_with($provider->getName(), 'dataProvider') || $provider->isAbstract()) {
        continue;
      }

      $expected = $provider->isGenerator() ? \Iterator::class : 'array';
      $declared = static::returnTypeName($provider);

      if ($declared !== $expected) {
        $violations[] = sprintf('%s() %s but declares %s instead of %s.', $provider->getName(), $provider->isGenerator() ? 'is a generator' : 'is not a generator', $declared, $expected);
      }
    }

    $this->assertSame([], $violations, sprintf('%s types a data provider against the convention in CONTRIBUTING.md.', $reflection->getShortName()));
  }

  public static function dataProviderProviderReturnTypesMatchTheirBody(): array {
    return static::discoverTestClasses();
  }

  /**
   * Return every test class under `tests/phpunit/src`, keyed by name.
   *
   * Abstract bases stay in, because they declare tests and providers too.
   * Fixture directories hold no tests and are skipped by path.
   *
   * @return array<string, array{class-string}>
   *   Fully qualified test class names, as data provider rows.
   */
  protected static function discoverTestClasses(): array {
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__, \FilesystemIterator::SKIP_DOTS));

    $classes = [];

    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $relative = substr($file->getPathname(), strlen(__DIR__) + 1, -strlen('.php'));

      if (in_array('Fixtures', explode(DIRECTORY_SEPARATOR, $relative), TRUE)) {
        continue;
      }

      $class = __NAMESPACE__ . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

      if (!class_exists($class) || !is_subclass_of($class, TestCase::class)) {
        continue;
      }

      $classes[$class] = [$class];
    }

    ksort($classes);

    return $classes;
  }

  /**
   * Return the methods a class declares rather than inherits.
   *
   * @param \ReflectionClass<object> $reflection
   *   The test class.
   *
   * @return array<int, \ReflectionMethod>
   *   Methods declared by the class itself.
   */
  protected static function ownMethods(\ReflectionClass $reflection): array {
    return array_values(array_filter($reflection->getMethods(), static fn(\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $reflection->getName()));
  }

  /**
   * Return the names of the providers a test method declares.
   *
   * @param \ReflectionMethod $test
   *   The test method.
   *
   * @return array<int, string>
   *   Provider method names, in declaration order.
   */
  protected static function providerNames(\ReflectionMethod $test): array {
    $names = [];

    foreach ($test->getAttributes(DataProvider::class) as $attribute) {
      $names[] = $attribute->newInstance()->methodName();
    }

    return $names;
  }

  /**
   * Return a method's declared return type as a readable name.
   *
   * @param \ReflectionMethod $method
   *   The method.
   *
   * @return string
   *   The type name, or `no return type` when none is declared.
   */
  protected static function returnTypeName(\ReflectionMethod $method): string {
    $type = $method->getReturnType();

    if ($type === NULL) {
      return 'no return type';
    }

    return $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;
  }

}
