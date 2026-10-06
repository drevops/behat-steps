<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\UninitializedContextEnvironment;
use Behat\Config\Config;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Testwork\Specification\SpecificationIterator;
use Behat\Testwork\Suite\GenericSuite;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Context\WebContext;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Tests the reference configuration in behat.dist.php.
 */
#[CoversNothing]
class BehatDistConfigTest extends UnitTestCase {

  public function testTheFileReturnsTheBehatConfiguration(): void {
    $this->assertInstanceOf(Config::class, static::loadConfig());
  }

  public function testEveryExtensionOptionIsSet(): void {
    $this->assertSame([], $this->collectUncoveredPaths(static::buildConfigTree(), static::readSetting('default', 'extensions', BehatStepsExtension::class), ''));
  }

  public function testTheSuiteRegistersDrupalContextAlone(): void {
    $environment = static::buildContextEnvironment('default');

    WebContext::assertOneContext(new BeforeSuiteScope($environment, $this->createStub(SpecificationIterator::class)));

    $this->assertContains(DrupalContext::class, $environment->getContextClasses());
  }

  /**
   * Collects the configuration paths a node declares and the settings omit.
   *
   * @param \Symfony\Component\Config\Definition\ArrayNode $node
   *   The node to read the children of.
   * @param array<string, mixed> $settings
   *   The settings covering that node.
   * @param string $prefix
   *   The path of the node, empty at the root.
   *
   * @return array<int, string>
   *   The paths that carry no value.
   */
  protected function collectUncoveredPaths(ArrayNode $node, array $settings, string $prefix): array {
    $paths = [];

    foreach ($node->getChildren() as $name => $child) {
      $path = $prefix === '' ? $name : $prefix . '.' . $name;

      if (!array_key_exists($name, $settings)) {
        $paths[] = $path;

        continue;
      }

      if ($child instanceof ArrayNode && is_array($settings[$name])) {
        $paths = array_merge($paths, $this->collectUncoveredPaths($child, $settings[$name], $path));
      }
    }

    return $paths;
  }

  /**
   * Builds the context environment Behat passes to a reference suite's hooks.
   *
   * @param string $name
   *   The name of the suite in the default profile.
   */
  protected static function buildContextEnvironment(string $name): UninitializedContextEnvironment {
    $settings = static::readSetting('default', 'suites', $name);
    $environment = new UninitializedContextEnvironment(new GenericSuite($name, $settings));

    foreach ((array) ($settings['contexts'] ?? []) as $context) {
      $class = (string) (is_array($context) ? array_key_first($context) : $context);

      if (!is_a($class, Context::class, TRUE)) {
        self::fail(sprintf('The "%s" suite in behat.dist.php registers "%s", which is not a Behat context.', $name, $class));
      }

      $environment->registerContextClass($class);
    }

    return $environment;
  }

  /**
   * Reads the settings the reference configuration holds at a path.
   *
   * @param string ...$keys
   *   The keys leading from the configuration root to the settings.
   *
   * @return array<string, mixed>
   *   The settings at the end of the path.
   */
  protected static function readSetting(string ...$keys): array {
    $path = implode(' > ', $keys);
    $settings = static::loadConfig()->toArray();

    foreach ($keys as $key) {
      if (!is_array($settings) || !isset($settings[$key])) {
        self::fail(sprintf('behat.dist.php has no "%s" key on the path %s.', $key, $path));
      }

      $settings = $settings[$key];
    }

    if (!is_array($settings)) {
      self::fail(sprintf('behat.dist.php holds no settings at %s.', $path));
    }

    return $settings;
  }

  /**
   * Loads the configuration the reference file returns.
   */
  protected static function loadConfig(): Config {
    $config = require dirname(__DIR__, 5) . '/behat.dist.php';

    if (!$config instanceof Config) {
      self::fail('behat.dist.php does not return a Behat configuration.');
    }

    return $config;
  }

  /**
   * Builds the extension's configuration tree.
   */
  protected static function buildConfigTree(): ArrayNode {
    $builder = new ArrayNodeDefinition(BehatStepsExtension::CONFIG_KEY);

    (new BehatStepsExtension())->configure($builder);

    $node = $builder->getNode(TRUE);

    if (!$node instanceof ArrayNode) {
      self::fail('The extension does not build an array configuration tree.');
    }

    return $node;
  }

}
