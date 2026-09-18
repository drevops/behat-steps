<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat;

use Behat\Config\Config;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Tests the reference configuration in behat.dist.php.
 */
#[CoversNothing]
class BehatDistConfigTest extends TestCase {

  public function testTheFileReturnsTheBehatConfiguration(): void {
    $this->assertInstanceOf(Config::class, static::loadConfig());
  }

  public function testEveryExtensionOptionIsSet(): void {
    $this->assertSame([], $this->uncoveredPaths(static::buildConfigTree(), static::extensionSettings(), ''));
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
  protected function uncoveredPaths(ArrayNode $node, array $settings, string $prefix): array {
    $paths = [];

    foreach ($node->getChildren() as $name => $child) {
      $path = $prefix === '' ? $name : $prefix . '.' . $name;

      if (!array_key_exists($name, $settings)) {
        $paths[] = $path;

        continue;
      }

      if ($child instanceof ArrayNode && is_array($settings[$name])) {
        $paths = array_merge($paths, $this->uncoveredPaths($child, $settings[$name], $path));
      }
    }

    return $paths;
  }

  /**
   * Reads the settings the reference configuration gives this package.
   *
   * @return array<string, mixed>
   *   The settings under the extension's key.
   */
  protected static function extensionSettings(): array {
    $settings = static::loadConfig()->toArray();

    foreach (['default', 'extensions', BehatStepsExtension::class] as $key) {
      if (!is_array($settings) || !isset($settings[$key])) {
        self::fail(sprintf('behat.dist.php has no "%s" key on the path to the extension settings.', $key));
      }

      $settings = $settings[$key];
    }

    if (!is_array($settings)) {
      self::fail('behat.dist.php does not configure ' . BehatStepsExtension::class . '.');
    }

    return $settings;
  }

  /**
   * Loads the configuration the reference file returns.
   */
  protected static function loadConfig(): Config {
    $config = require dirname(__DIR__, 3) . '/behat.dist.php';

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
