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

/**
 * Base class for unit tests.
 *
 * The hook scope classes are final, so a test that invokes a hook directly
 * builds a real scope over stubbed collaborators rather than mocking it.
 */
abstract class UnitTestCase extends UpstreamUnitTestCase {

  /**
   * Indicates whether a path under `src/` holds step vocabulary.
   *
   * The conventions the discovery-driven tests hold describe traits mixed
   * into a consuming context, and those all live under `Steps/`. The driver
   * layer is library code with its own shapes.
   *
   * @param string $relative_path
   *   A path relative to `src/`.
   */
  protected static function isVocabularyPath(string $relative_path): bool {
    return str_starts_with($relative_path, 'Steps' . DIRECTORY_SEPARATOR);
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

}
