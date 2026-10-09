<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Behat\Auth\Authenticator;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Context\Initializer\BackendAwareInitializer;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactory;
use DrevOps\BehatSteps\Behat\Registry\UserRegistry;
use DrevOps\BehatSteps\Behat\ServiceContainer\BackendPass;
use DrevOps\BehatSteps\Behat\Tag;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;

/**
 * Asserts that every class and namespace under `src/Behat` is named for a role.
 *
 * Every class the container builds ends in a role suffix, and every class
 * sits in a sub-namespace, apart from the ones listed in ROOT_TYPES.
 * CONTRIBUTING.md states the rules.
 */
#[CoversNothing]
class ClassNamingTest extends UnitTestCase {

  /**
   * The namespace `src/Behat` maps to.
   */
  protected const BEHAT_NAMESPACE = 'DrevOps\\BehatSteps\\Behat\\';

  /**
   * Suffixes that would describe every class in the package equally well.
   */
  protected const GENERIC_SUFFIXES = ['Manager', 'Handler', 'Helper', 'Service'];

  /**
   * Suffixes naming the role of a class the container builds.
   *
   * A registry holds things and looks them up, and every other suffix is an
   * agent noun for what the service does.
   */
  protected const ROLE_SUFFIXES = ['Authenticator', 'Factory', 'Initializer', 'Listener', 'Reader', 'Registry', 'Resolver', 'Selector'];

  /**
   * Types that sit directly under `src/Behat`.
   *
   * `Tag` is read by the step traits, the contexts, the listeners and the
   * registries alike, so no sub-namespace owns it.
   */
  protected const ROOT_TYPES = [Tag::class];

  /**
   * Assert that a class and every namespace holding it are named for a role.
   *
   * @param string $class
   *   The fully qualified name of a class, interface or trait.
   */
  #[DataProvider('dataProviderClassIsNamedForItsRole')]
  public function testClassIsNamedForItsRole(string $class): void {
    $this->assertSame([], static::collectGenericNames($class), sprintf('%s is named against the convention in CONTRIBUTING.md.', $class));
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
    $this->assertSame($expected, static::collectGenericNames($class));
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
   * Assert that a class the container builds ends in a role suffix.
   *
   * @param string $class
   *   The fully qualified name of a class the service definitions name.
   */
  #[DataProvider('dataProviderServiceIsNamedForItsRole')]
  public function testServiceIsNamedForItsRole(string $class): void {
    $this->assertTrue(static::hasRoleSuffix($class), sprintf('%s is a container service named neither as a registry nor as an agent noun, against the convention in CONTRIBUTING.md. Name it for its role, adding a new suffix to ROLE_SUFFIXES.', $class));
  }

  public static function dataProviderServiceIsNamedForItsRole(): array {
    return static::discoverServiceClasses();
  }

  /**
   * Assert that a role suffix is told apart from a name that states no role.
   *
   * @param string $class
   *   A fully qualified class name.
   * @param bool $expected
   *   Whether the name ends in a role suffix.
   */
  #[DataProvider('dataProviderRoleSuffixesAreDetected')]
  public function testRoleSuffixesAreDetected(string $class, bool $expected): void {
    $this->assertSame($expected, static::hasRoleSuffix($class));
  }

  public static function dataProviderRoleSuffixesAreDetected(): array {
    return [
      'registry' => [UserRegistry::class, TRUE],
      'agent noun' => [Authenticator::class, TRUE],
      'factory' => [HttpClientFactory::class, TRUE],
      'value' => [Option::class, FALSE],
      'plural noun' => ['DrevOps\\BehatSteps\\Behat\\Config\\TagOverrides', FALSE],
      'suffix inside a name' => ['DrevOps\\BehatSteps\\Behat\\Config\\ReaderSettings', FALSE],
    ];
  }

  /**
   * Assert that a class sits in a sub-namespace named for its role or concern.
   *
   * @param string $class
   *   The fully qualified name of a class, interface or trait.
   */
  #[DataProvider('dataProviderClassSitsInSubNamespace')]
  public function testClassSitsInSubNamespace(string $class): void {
    $this->assertTrue(!static::isAtRoot($class) || in_array($class, static::ROOT_TYPES, TRUE), sprintf('%s sits directly under src/Behat. Move it to the sub-namespace named for its role or concern, as CONTRIBUTING.md describes.', $class));
  }

  public static function dataProviderClassSitsInSubNamespace(): array {
    return static::discoverBehatClasses();
  }

  /**
   * Assert that a class directly under `src/Behat` is told apart.
   *
   * @param string $class
   *   A fully qualified class name.
   * @param bool $expected
   *   Whether the class sits directly under `src/Behat`.
   */
  #[DataProvider('dataProviderRootClassesAreDetected')]
  public function testRootClassesAreDetected(string $class, bool $expected): void {
    $this->assertSame($expected, static::isAtRoot($class));
  }

  public static function dataProviderRootClassesAreDetected(): array {
    return [
      'class directly under the package' => [Tag::class, TRUE],
      'class in a sub-namespace' => [UserRegistry::class, FALSE],
      'class in a nested sub-namespace' => [BackendAwareInitializer::class, FALSE],
    ];
  }

  /**
   * Return every class, interface and trait under `src/Behat`, keyed by name.
   *
   * @return array<string, array{string}>
   *   Fully qualified names, as data provider rows.
   */
  protected static function discoverBehatClasses(): array {
    $root = dirname(__DIR__, 3) . '/src/Behat';
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

    $classes = [];

    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));
      $class = static::BEHAT_NAMESPACE . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

      $classes[$class] = [$class];
    }

    ksort($classes);

    return $classes;
  }

  /**
   * Return every class the extension's service definitions name, keyed by name.
   *
   * @return array<string, array{string}>
   *   Fully qualified class names, as data provider rows.
   */
  protected static function discoverServiceClasses(): array {
    $definitions = Yaml::parseFile(dirname(__DIR__, 3) . '/src/Behat/ServiceContainer/config/services.yml');
    $parameters = is_array($definitions) && is_array($definitions['parameters'] ?? NULL) ? $definitions['parameters'] : [];

    $classes = [];

    foreach ($parameters as $name => $value) {
      if (str_ends_with((string) $name, '.class') && is_string($value)) {
        $classes[$value] = [$value];
      }
    }

    ksort($classes);

    return $classes;
  }

  /**
   * Check whether a class name ends in a role suffix.
   *
   * @param string $class
   *   A fully qualified class name.
   */
  protected static function hasRoleSuffix(string $class): bool {
    $short_name = substr($class, (int) strrpos($class, '\\') + 1);

    foreach (static::ROLE_SUFFIXES as $suffix) {
      if (str_ends_with($short_name, $suffix)) {
        return TRUE;
      }
    }

    return FALSE;
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
  protected static function collectGenericNames(string $class): array {
    return array_values(array_filter(explode('\\', $class), static::isGeneric(...)));
  }

  /**
   * Check whether a class sits directly under `src/Behat`.
   *
   * @param string $class
   *   A fully qualified class name under `src/Behat`.
   */
  protected static function isAtRoot(string $class): bool {
    return !str_contains(substr($class, strlen(static::BEHAT_NAMESPACE)), '\\');
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
