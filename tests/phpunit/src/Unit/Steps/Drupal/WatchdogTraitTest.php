<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\TestResult;
use DrevOps\BehatSteps\Backend\BlackboxBackendInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackendInterface;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\WatchdogTrait;
use DrevOps\BehatSteps\Steps\Exception\AssertionException;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests when the Watchdog check runs, for each opt-in and module state.
 */
#[CoversTrait(WatchdogTrait::class)]
class WatchdogTraitTest extends UnitTestCase {

  /**
   * The sentence naming the switches that turn the check off.
   */
  protected const SWITCH_OFF = ' Meet the prerequisite, or switch WatchdogTrait off with the "watchdog.enabled" option or the "@behat-steps-skip:WatchdogTrait" tag.';

  public function testOptedInWithDblogArmsTheCheck(): void {
    $context = $this->createContext(['drupal' => $this->createDrupalBackend(TRUE)]);

    $context->watchdogBeforeScenario($this->createBeforeScenarioScope());

    $this->assertTrue($context->testIsArmed());
  }

  public function testOptedInWithoutDblogFailsAtTheStart(): void {
    $context = $this->createContext(['drupal' => $this->createDrupalBackend(FALSE)]);

    try {
      $context->watchdogBeforeScenario($this->createBeforeScenarioScope());
      $this->fail('A site without dblog did not fail the scenario at its start.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('WatchdogTrait requires that the core "dblog" module is enabled, which does not hold.' . static::SWITCH_OFF, $exception->getMessage());
    }

    $this->assertFalse($context->testIsArmed());
  }

  /**
   * Tests that an opted-out scenario calls nothing on any backend.
   *
   * Whether dblog is enabled is never queried, so an opted-out scenario runs
   * the same with or without it.
   *
   * @param array<string, mixed> $steps
   *   The profile's 'steps' section.
   * @param list<string> $tags
   *   The scenario's tags.
   */
  #[DataProvider('dataProviderOptedOutTouchesNoBackend')]
  public function testOptedOutTouchesNoBackend(array $steps, array $tags): void {
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->expects($this->never())->method($this->anything());

    $context = $this->createContext(['drupal' => $drupal]);
    $context->setParameters(['steps' => $steps]);

    $context->watchdogBeforeScenario($this->createBeforeScenarioScope($tags));
    $context->watchdogAfterScenario($this->createAfterScenarioScope($tags));

    $this->assertFalse($context->testIsArmed());
  }

  public static function dataProviderOptedOutTouchesNoBackend(): \Iterator {
    yield 'opted out in the profile' => [['watchdog' => ['enabled' => FALSE]], []];
    yield 'opted out with the tag' => [[], ['behat-steps-skip:WatchdogTrait']];
  }

  /**
   * Tests that an opted-in scenario with no in-process backend fails at start.
   *
   * @param array<string, class-string<\DrevOps\BehatSteps\Backend\BackendInterface>> $backends
   *   Backend interfaces to stub, keyed by the name the scenario lists each
   *   one under, in order.
   * @param string $listed
   *   How the message lists them.
   */
  #[DataProvider('dataProviderOptedInWithoutLogBackendFailsAtTheStart')]
  public function testOptedInWithoutLogBackendFailsAtTheStart(array $backends, string $listed): void {
    $context = $this->createContext(array_map($this->createStub(...), $backends));

    $this->expectException(UnsupportedBackendActionException::class);
    $this->expectExceptionMessage(sprintf('WatchdogTrait requires that a backend in the scenario\'s list provides "LogCapabilityInterface", which does not hold. Backends available to this scenario, in order: %s.', $listed) . static::SWITCH_OFF);

    $context->watchdogBeforeScenario($this->createBeforeScenarioScope());
  }

  public static function dataProviderOptedInWithoutLogBackendFailsAtTheStart(): \Iterator {
    yield 'blackbox alone' => [['blackbox' => BlackboxBackendInterface::class], 'blackbox'];
    yield 'no backend' => [[], 'none'];
  }

  public function testOptedInWithDrushAloneArmsTheCheck(): void {
    $drush = $this->createStub(DrushBackendInterface::class);
    $drush->method('moduleIsEnabled')->willReturn(TRUE);
    $drush->method('logGetLastId')->willReturn(3);

    $context = $this->createContext(['drush' => $drush, 'blackbox' => $this->createStub(BlackboxBackendInterface::class)]);

    $context->watchdogBeforeScenario($this->createBeforeScenarioScope());

    $this->assertTrue($context->testIsArmed());
  }

  public function testScenarioThatUninstallsDblogFailsAtItsLastStep(): void {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturnOnConsecutiveCalls(TRUE, FALSE, FALSE);

    $context = $this->createContext(['drupal' => $drupal]);

    $step = new StepNode('When', 'I visit "/"', [], 2, 'When');
    $scenario = new ScenarioNode('Scenario', [], [$step], 'Scenario', 1);
    $feature = new FeatureNode('Feature', NULL, [], NULL, [$scenario], 'Feature', 'en', __DIR__ . '/feature.feature', 1);
    $environment = $this->createStub(Environment::class);

    $context->watchdogBeforeScenario(new BeforeScenarioScope($environment, $feature, $scenario));

    try {
      $context->watchdogAfterStep(new AfterStepScope($environment, $feature, $step, $this->createStub(StepResult::class)));
      $this->fail('A scenario that uninstalled dblog did not fail at its last step.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('WatchdogTrait requires that the core "dblog" module is enabled, which does not hold.' . static::SWITCH_OFF, $exception->getMessage());
    }

    // With dblog gone, the AfterScenario hook returns before reading the log.
    $context->watchdogAfterScenario(new AfterScenarioScope($environment, $feature, $scenario, $this->createStub(TestResult::class)));
  }

  /**
   * Tests the message types the scenario and feature tags add to 'php'.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param list<string> $expected
   *   The message types expected to be tracked.
   */
  #[DataProvider('dataProviderBeforeScenarioTracksMessageTypes')]
  public function testBeforeScenarioTracksMessageTypes(array $scenario_tags, array $feature_tags, array $expected): void {
    $context = $this->createContext(['drupal' => $this->createDrupalBackend(TRUE)]);

    $context->watchdogBeforeScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));

    $this->assertSame($expected, array_values($context->testGetMessageTypes()));
  }

  public static function dataProviderBeforeScenarioTracksMessageTypes(): \Iterator {
    yield 'no tags' => [[], [], ['php']];
    yield 'on the scenario' => [['watchdog:custom_type'], [], ['custom_type', 'php']];
    yield 'on the feature' => [[], ['watchdog:custom_type'], ['custom_type', 'php']];
    yield 'on both' => [['watchdog:scenario_type'], ['watchdog:feature_type'], ['feature_type', 'scenario_type', 'php']];
    yield 'the same type on both' => [['watchdog:custom_type'], ['watchdog:custom_type'], ['custom_type', 'php']];
  }

  public function testClearErrorsReadsTheEntriesAfterTheWatermarkAndDeletesThem(): void {
    $entry = ['wid' => 6, 'type' => 'php', 'severity' => 4, 'message' => 'Warning %name', 'variables' => ['%name' => 'first']];

    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(TRUE);
    $drupal->method('logGetLastId')->willReturn(5);
    $drupal->expects($this->once())->method('logRead')->with(['custom_type', 'php'], 5, 4)->willReturn([$entry]);
    $drupal->expects($this->once())->method('logDelete')->with([6]);

    $context = $this->createContext(['drupal' => $drupal]);
    $context->watchdogBeforeScenario($this->createBeforeScenarioScope(['watchdog:custom_type']));

    $errors = $context->watchdogClearErrors();

    $this->assertSame([6], array_keys($errors));
    $this->assertStringContainsString('Warning %name', $errors[6]);
  }

  public function testLastStepFailsWithTheErrorsLoggedSinceTheStart(): void {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(TRUE);
    $drupal->method('logGetLastId')->willReturn(5);
    $drupal->method('logRead')->willReturn([['wid' => 6, 'type' => 'php', 'severity' => 3, 'message' => 'Boom', 'variables' => []]]);

    $context = $this->createContext(['drupal' => $drupal]);
    [$environment, $feature, $scenario, $step] = $this->createSingleStepScenario();
    $context->watchdogBeforeScenario(new BeforeScenarioScope($environment, $feature, $scenario));

    $this->expectException(AssertionException::class);
    $this->expectExceptionMessageMatches('/^PHP errors were logged to watchdog during scenario "Scenario" \(line 1\):/');

    $context->watchdogAfterStep(new AfterStepScope($environment, $feature, $step, $this->createStub(StepResult::class)));
  }

  public function testLastStepClearsWithoutFailingWhenFailingIsOff(): void {
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(TRUE);
    $drupal->method('logGetLastId')->willReturn(5);
    $drupal->method('logRead')->willReturn([['wid' => 6, 'type' => 'php', 'severity' => 3, 'message' => 'Boom', 'variables' => []]]);
    $drupal->expects($this->once())->method('logDelete')->with([6]);

    $context = $this->createContext(['drupal' => $drupal]);
    $context->setParameters(['steps' => ['watchdog' => ['fail_on_errors' => FALSE]]]);
    [$environment, $feature, $scenario, $step] = $this->createSingleStepScenario();
    $context->watchdogBeforeScenario(new BeforeScenarioScope($environment, $feature, $scenario));

    $context->watchdogAfterStep(new AfterStepScope($environment, $feature, $step, $this->createStub(StepResult::class)));
  }

  /**
   * Builds the nodes of a feature holding 1 scenario with 1 step.
   *
   * @return array{\Behat\Testwork\Environment\Environment, \Behat\Gherkin\Node\FeatureNode, \Behat\Gherkin\Node\ScenarioNode, \Behat\Gherkin\Node\StepNode}
   *   The environment, the feature, the scenario and its step.
   */
  protected function createSingleStepScenario(): array {
    $step = new StepNode('When', 'I visit "/"', [], 2, 'When');
    $scenario = new ScenarioNode('Scenario', [], [$step], 'Scenario', 1);
    $feature = new FeatureNode('Feature', NULL, [], NULL, [$scenario], 'Feature', 'en', __DIR__ . '/feature.feature', 1);

    return [$this->createStub(Environment::class), $feature, $scenario, $step];
  }

  /**
   * Builds a Drupal backend double reporting whether dblog is enabled.
   */
  protected function createDrupalBackend(bool $is_dblog_enabled): DrupalBackendInterface {
    $backend = $this->createStub(DrupalBackendInterface::class);
    $backend->method('moduleIsEnabled')->willReturn($is_dblog_enabled);

    return $backend;
  }

  /**
   * Builds a context whose scenario lists the given backends, in order.
   *
   * @param array<string, \DrevOps\BehatSteps\Backend\BackendInterface> $backends
   *   The backends, keyed by the name the scenario lists each one under.
   */
  protected function createContext(array $backends): WatchdogTraitTestImplementation {
    $registry = new BackendRegistry($backends);
    $names = array_keys($backends);
    $registry->setScenarioBackends(array_combine($names, $names));

    $context = new WatchdogTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of WatchdogTrait.
 *
 * Exposes whether the scenario's follow-up hooks read the log.
 */
class WatchdogTraitTestImplementation extends WebRawContext {

  use WatchdogTrait;

  public function testIsArmed(): bool {
    return $this->watchdogLastId !== NULL;
  }

  /**
   * Returns the message types the scenario is checked for.
   *
   * @return array<int, string>
   *   The message types.
   */
  public function testGetMessageTypes(): array {
    return $this->watchdogMessageTypes;
  }

}
