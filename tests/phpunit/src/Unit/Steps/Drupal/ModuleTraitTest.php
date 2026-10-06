<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
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
   * Tests that the tags of the scenario and its feature change each module once.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param bool $is_enabled
   *   Whether the module starts enabled.
   * @param string|null $expected_call
   *   The backend method expected to run once, or NULL when neither runs.
   */
  #[DataProvider('dataProviderBeforeScenarioAppliesTags')]
  public function testBeforeScenarioAppliesTags(array $scenario_tags, array $feature_tags, bool $is_enabled, ?string $expected_call): void {
    $backend = $this->createModuleBackend();
    $backend->method('moduleIsEnabled')->willReturn($is_enabled);
    $backend->method('moduleIsPresent')->willReturn(TRUE);
    $backend->expects($expected_call === 'moduleInstall' ? $this->once() : $this->never())->method('moduleInstall')->with('help');
    $backend->expects($expected_call === 'moduleUninstall' ? $this->once() : $this->never())->method('moduleUninstall')->with('help');

    $this->createContext($backend)->moduleBeforeScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));
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
   * Builds a context resolving the module capability to the given backend.
   */
  protected function createContext(BackendInterface $backend): ModuleTraitTestImplementation {
    $backend_registry = new BackendRegistry(['test' => $backend]);
    $backend_registry->setScenarioBackends(['test' => 'test']);

    $context = new ModuleTraitTestImplementation();
    $context->setBackendRegistry($backend_registry);

    return $context;
  }

  /**
   * Builds a backend double that installs modules and clears caches.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend double.
   */
  protected function createModuleBackend(): BackendInterface&ModuleCapabilityInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject $backend */
    $backend = $this->createMockForIntersectionOfInterfaces([BackendInterface::class, ModuleCapabilityInterface::class, CacheCapabilityInterface::class]);

    return $backend;
  }

}

/**
 * Test implementation of ModuleTrait.
 */
class ModuleTraitTestImplementation extends WebRawContext {

  use ModuleTrait;

}
