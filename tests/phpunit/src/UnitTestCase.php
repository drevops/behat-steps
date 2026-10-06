<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use AlexSkrypnyk\PhpunitHelpers\UnitTestCase as UpstreamUnitTestCase;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Testwork\Call\CallCenter;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\EnvironmentManager;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\HookRepository;
use Behat\Testwork\Hook\Scope\AfterSuiteScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Testwork\Specification\SpecificationIterator;
use Behat\Testwork\Tester\Result\TestResult;
use PHPUnit\Framework\TestCase;

/**
 * Base class for unit tests.
 *
 * The hook scope classes are final, so a test that invokes a hook directly
 * builds a real scope over stubbed collaborators rather than mocking it.
 */
abstract class UnitTestCase extends UpstreamUnitTestCase {

  /**
   * Directories under `src/` holding the traits a context composes.
   */
  protected const COMPOSED_TRAIT_ROOTS = ['Helper', 'Steps'];

  /**
   * Indicates whether a path under `src/` holds a trait a context composes.
   *
   * The discovery-driven tests hold conventions for the traits this library
   * names itself and flattens into a consuming context. Those are the step
   * vocabulary under `Steps/` and the helpers under `Helper/`.
   *
   * The traits under `Behat/` carry the names the framework interfaces
   * dictate, and the backend layer is library code with its own shapes.
   *
   * @param string $relative_path
   *   A path relative to `src/`.
   */
  protected static function isComposedTraitPath(string $relative_path): bool {
    foreach (static::COMPOSED_TRAIT_ROOTS as $root) {
      if (str_starts_with($relative_path, $root . DIRECTORY_SEPARATOR)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Return every trait a context composes, keyed by name.
   *
   * @return array<string, array{string}>
   *   Fully qualified trait names, as data provider rows.
   */
  protected static function discoverTraits(): array {
    $root = dirname(__DIR__, 3) . '/src';
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

    $traits = [];
    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));

      if (!static::isComposedTraitPath($relative)) {
        continue;
      }

      $trait = 'DrevOps\\BehatSteps\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

      if (!trait_exists($trait)) {
        continue;
      }

      $traits[$trait] = [$trait];
    }

    ksort($traits);

    return $traits;
  }

  /**
   * Return every class, interface and trait declared under `src/`.
   *
   * @return array<string, array{string}>
   *   Fully qualified type names, as data provider rows keyed by the path
   *   relative to `src/`.
   */
  protected static function discoverSourceTypes(): array {
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
   * Reflect a class, interface or trait held in a variable.
   *
   * A name read from a file or a data provider is a plain string, so it is
   * narrowed to a class string here.
   *
   * @param object|string $subject
   *   An object, or the fully qualified name of a class, interface or trait.
   *
   * @return \ReflectionClass<object>
   *   Reflection of the subject.
   */
  protected static function reflect(object|string $subject): \ReflectionClass {
    /** @var class-string|object $subject */
    return new \ReflectionClass($subject);
  }

  /**
   * Tokenize a file, dropping whitespace and comments.
   *
   * @param string $file
   *   The absolute path to the file.
   *
   * @return array<int, array{int, string, int}|string>
   *   The remaining tokens, reindexed.
   */
  protected static function readSignificantTokens(string $file): array {
    return static::tokenizeSignificant((string) file_get_contents($file));
  }

  /**
   * Tokenize PHP code, dropping whitespace and comments.
   *
   * @param string $code
   *   The PHP code, opening tag included.
   *
   * @return array<int, array{int, string, int}|string>
   *   The remaining tokens, reindexed.
   */
  protected static function tokenizeSignificant(string $code): array {
    return array_values(array_filter(token_get_all($code), static fn(array|string $token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)));
  }

  /**
   * Build the pattern matching the source line that declares a constant.
   *
   * A native type may sit between 'const' and the name.
   *
   * @param string $name
   *   The constant name.
   *
   * @return string
   *   A regular expression for 'preg_match()'.
   */
  protected static function buildConstantDeclarationPattern(string $name): string {
    return '/(^|\s)const\s+(?:[^=]+\s)?' . preg_quote($name, '/') . '\s*=/';
  }

  /**
   * Build a hook dispatcher that finds no hooks.
   *
   * The dispatcher and everything it composes are final, so a test that needs
   * one builds the real chain over an empty environment manager.
   */
  protected function createHookDispatcher(): HookDispatcher {
    return new HookDispatcher(new HookRepository(new EnvironmentManager()), new CallCenter());
  }

  /**
   * Build a scope for a BeforeSuite hook.
   */
  protected function createBeforeSuiteScope(): BeforeSuiteScope {
    return new BeforeSuiteScope($this->createStub(Environment::class), $this->createStub(SpecificationIterator::class));
  }

  /**
   * Build a scope for an AfterSuite hook.
   */
  protected function createAfterSuiteScope(): AfterSuiteScope {
    return new AfterSuiteScope($this->createStub(Environment::class), $this->createStub(SpecificationIterator::class), $this->createStub(TestResult::class));
  }

  /**
   * Build a scope for a BeforeScenario hook.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   */
  protected function createBeforeScenarioScope(array $scenario_tags = [], array $feature_tags = []): BeforeScenarioScope {
    [$feature, $scenario] = $this->createScenarioNodes($scenario_tags, $feature_tags);

    return new BeforeScenarioScope($this->createStub(Environment::class), $feature, $scenario);
  }

  /**
   * Build a scope for an AfterScenario hook.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   */
  protected function createAfterScenarioScope(array $scenario_tags = [], array $feature_tags = []): AfterScenarioScope {
    [$feature, $scenario] = $this->createScenarioNodes($scenario_tags, $feature_tags);

    return new AfterScenarioScope($this->createStub(Environment::class), $feature, $scenario, $this->createStub(TestResult::class));
  }

  /**
   * Build the feature and scenario nodes a scenario scope wraps.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   *
   * @return array{\Behat\Gherkin\Node\FeatureNode, \Behat\Gherkin\Node\ScenarioNode}
   *   The feature node and the scenario node it contains.
   */
  protected function createScenarioNodes(array $scenario_tags, array $feature_tags): array {
    $scenario = new ScenarioNode('Scenario', $scenario_tags, [], 'Scenario', 1);
    $feature = new FeatureNode('Feature', NULL, $feature_tags, NULL, [$scenario], 'Feature', 'en', __DIR__ . '/feature.feature', 1);

    return [$feature, $scenario];
  }

  /**
   * Write a file below the per-test temporary directory.
   *
   * @param string $path
   *   Path relative to the temporary directory. Missing parent directories
   *   are created.
   * @param string $contents
   *   The file contents.
   *
   * @return string
   *   The absolute path written.
   */
  protected function writeFixture(string $path, string $contents): string {
    $full_path = static::$tmp . '/' . $path;
    $directory = dirname($full_path);

    if (!is_dir($directory)) {
      mkdir($directory, 0777, TRUE);
    }

    file_put_contents($full_path, $contents);

    return $full_path;
  }

}
