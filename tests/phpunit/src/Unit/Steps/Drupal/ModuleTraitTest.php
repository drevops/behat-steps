<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistry;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Steps\Drupal\ModuleTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for ModuleTrait.
 */
#[CoversTrait(ModuleTrait::class)]
class ModuleTraitTest extends UnitTestCase {

  /**
   * Tests the state each module tag resolves to.
   *
   * @param list<string> $tags
   *   Tags of the scenario and its feature, feature tags first.
   * @param array<string, bool> $expected
   *   The expected state of each module.
   */
  #[DataProvider('dataProviderParseTags')]
  public function testParseTags(array $tags, array $expected): void {
    $this->assertSame($expected, (new ModuleTraitTestImplementation())->callModuleParseTags($tags));
  }

  public static function dataProviderParseTags(): array {
    return [
      'no tags' => [[], []],
      'unrelated tags' => [['api', 'javascript', 'modules:help'], []],
      'an enabled module' => [['module:help'], ['help' => TRUE]],
      'a disabled module' => [['module:!help'], ['help' => FALSE]],
      'several modules' => [['module:help', 'module:!contextual'], ['help' => TRUE, 'contextual' => FALSE]],
      'a repeated tag' => [['module:help', 'module:help'], ['help' => TRUE]],
      'a later disable replaces an enable' => [['module:help', 'module:!help'], ['help' => FALSE]],
      'a later enable replaces a disable' => [['module:!help', 'module:help'], ['help' => TRUE]],
      'the first mention keeps its position' => [['module:help', 'module:syslog', 'module:!help'], ['help' => FALSE, 'syslog' => TRUE]],
    ];
  }

  /**
   * Tests that the tags of the scenario and its feature change each module once.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param bool $enabled
   *   Whether the module starts enabled.
   * @param string|null $expected_call
   *   The driver method expected to run once, or NULL when neither runs.
   */
  #[DataProvider('dataProviderBeforeScenarioAppliesTags')]
  public function testBeforeScenarioAppliesTags(array $scenario_tags, array $feature_tags, bool $enabled, ?string $expected_call): void {
    $driver = $this->createModuleDriver();
    $driver->method('moduleIsEnabled')->willReturn($enabled);
    $driver->method('moduleIsPresent')->willReturn(TRUE);
    $driver->expects($expected_call === 'moduleInstall' ? $this->once() : $this->never())->method('moduleInstall')->with('help');
    $driver->expects($expected_call === 'moduleUninstall' ? $this->once() : $this->never())->method('moduleUninstall')->with('help');

    $this->createContext($driver)->moduleBeforeScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));
  }

  public static function dataProviderBeforeScenarioAppliesTags(): array {
    return [
      'enabled on the scenario' => [['module:help'], [], FALSE, 'moduleInstall'],
      'enabled on the feature' => [[], ['module:help'], FALSE, 'moduleInstall'],
      'disabled on the feature' => [[], ['module:!help'], TRUE, 'moduleUninstall'],
      'already in the state the feature asks for' => [[], ['module:help'], TRUE, NULL],
      'the scenario disables what the feature enables' => [['module:!help'], ['module:help'], FALSE, NULL],
      'the scenario enables what the feature disables' => [['module:help'], ['module:!help'], TRUE, NULL],
      'skipped on the feature' => [['module:help'], ['behat-steps-skip:ModuleTrait'], FALSE, NULL],
    ];
  }

  /**
   * Builds a context resolving the module capability to the given driver.
   */
  protected function createContext(DriverInterface $driver): ModuleTraitTestImplementation {
    $driver_registry = new DriverRegistry(['test' => $driver]);
    $driver_registry->setScenarioDrivers(['test' => 'test']);

    $context = new ModuleTraitTestImplementation();
    $context->setDriverRegistry($driver_registry);

    return $context;
  }

  /**
   * Builds a driver double that installs modules and clears caches.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface&\DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The driver double.
   */
  protected function createModuleDriver(): DriverInterface&ModuleCapabilityInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Driver\DriverInterface&\DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject $driver */
    $driver = $this->createMockForIntersectionOfInterfaces([DriverInterface::class, ModuleCapabilityInterface::class, CacheCapabilityInterface::class]);

    return $driver;
  }

}

/**
 * Test implementation of ModuleTrait.
 */
class ModuleTraitTestImplementation extends WebRawContext {

  use ModuleTrait;

  /**
   * Exposes the protected tag parser.
   *
   * @param array<int, string> $tags
   *   Tags of the scenario and its feature, feature tags first.
   *
   * @return array<string, bool>
   *   The state of each named module.
   */
  public function callModuleParseTags(array $tags): array {
    return $this->moduleParseTags($tags);
  }

}
