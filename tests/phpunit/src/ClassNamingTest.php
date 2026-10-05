<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Behat\Registry\UserRegistry;
use DrevOps\BehatSteps\Behat\ServiceContainer\BackendPass;
use DrevOps\BehatSteps\Behat\Tag;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that every class and namespace under `src/Behat` is named for a role.
 *
 * CONTRIBUTING.md states the rule.
 */
#[CoversNothing]
class ClassNamingTest extends UnitTestCase {

  /**
   * Suffixes that would describe every class in the package equally well.
   */
  protected const GENERIC_SUFFIXES = ['Manager', 'Handler', 'Helper', 'Service'];

  /**
   * Assert that a class and every namespace holding it are named for a role.
   *
   * @param string $class
   *   The fully qualified name of a class, interface or trait.
   */
  #[DataProvider('dataProviderClassIsNamedForItsRole')]
  public function testClassIsNamedForItsRole(string $class): void {
    $this->assertSame([], static::genericNames($class), sprintf('%s is named against the convention in CONTRIBUTING.md.', $class));
  }

  public static function dataProviderClassIsNamedForItsRole(): array {
    return static::discoverBehatClasses();
  }

  /**
   * Assert that a generic suffix is detected in a namespace or a class name.
   *
   * @param string $class
   *   A fully qualified class name.
   * @param array<int, string> $expected
   *   The names expected to end in a generic suffix, in order.
   */
  #[DataProvider('dataProviderGenericNamesAreDetected')]
  public function testGenericNamesAreDetected(string $class, array $expected): void {
    $this->assertSame($expected, static::genericNames($class));
  }

  public static function dataProviderGenericNamesAreDetected(): array {
    return [
      'role-named namespace and class' => [UserRegistry::class, []],
      'class directly under the package' => [Tag::class, []],
      'generic namespace' => ['DrevOps\\BehatSteps\\Behat\\Manager\\Authenticator', ['Manager']],
      'generic class' => ['DrevOps\\BehatSteps\\Behat\\Auth\\SessionManager', ['SessionManager']],
      'generic nested namespace' => ['DrevOps\\BehatSteps\\Behat\\Hook\\Handler\\AfterNodeCreate', ['Handler']],
      'generic namespace and class' => ['DrevOps\\BehatSteps\\Behat\\Service\\MailHelper', ['Service', 'MailHelper']],
      'generic word opening a name' => [BackendPass::class, []],
      'generic word inside a name' => ['DrevOps\\BehatSteps\\Behat\\Mink\\HandlerAwareTrait', []],
    ];
  }

  /**
   * Return every class, interface and trait under `src/Behat`, keyed by name.
   *
   * @return array<string, array{string}>
   *   Fully qualified names, as data provider rows.
   */
  protected static function discoverBehatClasses(): array {
    $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Behat';
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

    $classes = [];

    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));
      $class = 'DrevOps\\BehatSteps\\Behat\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

      $classes[$class] = [$class];
    }

    ksort($classes);

    return $classes;
  }

  /**
   * Return the namespace segments and class name that end in a generic suffix.
   *
   * @param string $class
   *   A fully qualified class name.
   *
   * @return array<int, string>
   *   The generic names, in the order the class name holds them.
   */
  protected static function genericNames(string $class): array {
    return array_values(array_filter(explode('\\', $class), static::isGeneric(...)));
  }

  /**
   * Check whether a single name ends in a generic suffix.
   *
   * @param string $name
   *   A namespace segment or a short class name.
   */
  protected static function isGeneric(string $name): bool {
    foreach (static::GENERIC_SUFFIXES as $suffix) {
      if (str_ends_with($name, $suffix)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
