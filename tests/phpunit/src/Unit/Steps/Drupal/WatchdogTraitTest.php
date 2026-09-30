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
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistry;
use DrevOps\BehatSteps\Driver\BlackboxDriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\DrushDriverInterface;
use DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException;
use DrevOps\BehatSteps\Steps\Drupal\WatchdogTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
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
    $context = $this->createContext(['drupal' => $this->createDrupalDriver(TRUE)]);

    $context->watchdogSetScenario($this->createBeforeScenarioScope());

    $this->assertTrue($context->isArmed());
  }

  public function testOptedInWithoutDblogFailsAtTheStart(): void {
    $context = $this->createContext(['drupal' => $this->createDrupalDriver(FALSE)]);

    try {
      $context->watchdogSetScenario($this->createBeforeScenarioScope());
      $this->fail('A site without dblog did not fail the scenario at its start.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('WatchdogTrait requires that the core "dblog" module is enabled, which does not hold.' . static::SWITCH_OFF, $exception->getMessage());
    }

    $this->assertFalse($context->isArmed());
  }

  /**
   * Tests that an opted-out scenario calls nothing on any driver.
   *
   * Whether dblog is enabled is never asked, so an opted-out scenario runs
   * the same with or without it.
   *
   * @param array<string, mixed> $steps
   *   The profile's 'steps' section.
   * @param list<string> $tags
   *   The scenario's tags.
   */
  #[DataProvider('dataProviderOptedOutTouchesNoDriver')]
  public function testOptedOutTouchesNoDriver(array $steps, array $tags): void {
    $drupal = $this->createMock(DrupalDriverInterface::class);
    $drupal->expects($this->never())->method($this->anything());

    $context = $this->createContext(['drupal' => $drupal]);
    $context->setParameters(['steps' => $steps]);

    $context->watchdogSetScenario($this->createBeforeScenarioScope($tags));
    $context->watchdogAfterScenario($this->createAfterScenarioScope($tags));

    $this->assertFalse($context->isArmed());
  }

  public static function dataProviderOptedOutTouchesNoDriver(): \Iterator {
    yield 'opted out in the profile' => [['watchdog' => ['enabled' => FALSE]], []];
    yield 'opted out with the tag' => [[], ['behat-steps-skip:WatchdogTrait']];
  }

  /**
   * Tests that an opted-in scenario with no in-process driver fails at start.
   *
   * @param array<string, class-string<\DrevOps\BehatSteps\Driver\DriverInterface>> $drivers
   *   Driver interfaces to stub, keyed by the name the scenario lists each
   *   one under, in order.
   * @param string $listed
   *   How the message lists them.
   */
  #[DataProvider('dataProviderOptedInWithoutInProcessDriverFailsAtTheStart')]
  public function testOptedInWithoutInProcessDriverFailsAtTheStart(array $drivers, string $listed): void {
    $context = $this->createContext(array_map($this->createStub(...), $drivers));

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage(sprintf('WatchdogTrait requires that a driver in the scenario\'s list provides "CoreCapabilityInterface", which does not hold. Drivers available to this scenario, in order: %s.', $listed) . static::SWITCH_OFF);

    $context->watchdogSetScenario($this->createBeforeScenarioScope());
  }

  public static function dataProviderOptedInWithoutInProcessDriverFailsAtTheStart(): \Iterator {
    yield 'Drush alone' => [['drush' => DrushDriverInterface::class], 'drush'];
    yield 'Drush and blackbox' => [['drush' => DrushDriverInterface::class, 'blackbox' => BlackboxDriverInterface::class], 'drush, blackbox'];
    yield 'no driver' => [[], 'none'];
  }

  public function testScenarioThatUninstallsDblogFailsAtItsLastStep(): void {
    $drupal = $this->createStub(DrupalDriverInterface::class);
    $drupal->method('moduleIsEnabled')->willReturnOnConsecutiveCalls(TRUE, FALSE, FALSE);

    $context = $this->createContext(['drupal' => $drupal]);

    $step = new StepNode('When', 'I visit "/"', [], 2, 'When');
    $scenario = new ScenarioNode('Scenario', [], [$step], 'Scenario', 1);
    $feature = new FeatureNode('Feature', NULL, [], NULL, [$scenario], 'Feature', 'en', __DIR__ . '/feature.feature', 1);
    $environment = $this->createStub(Environment::class);

    $context->watchdogSetScenario(new BeforeScenarioScope($environment, $feature, $scenario));

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
  #[DataProvider('dataProviderSetScenarioTracksMessageTypes')]
  public function testSetScenarioTracksMessageTypes(array $scenario_tags, array $feature_tags, array $expected): void {
    $context = $this->createContext(['drupal' => $this->createDrupalDriver(TRUE)]);

    $context->watchdogSetScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));

    $this->assertSame($expected, array_values($context->getMessageTypes()));
  }

  public static function dataProviderSetScenarioTracksMessageTypes(): \Iterator {
    yield 'no tags' => [[], [], ['php']];
    yield 'on the scenario' => [['watchdog:custom_type'], [], ['custom_type', 'php']];
    yield 'on the feature' => [[], ['watchdog:custom_type'], ['custom_type', 'php']];
    yield 'on both' => [['watchdog:scenario_type'], ['watchdog:feature_type'], ['feature_type', 'scenario_type', 'php']];
    yield 'the same type on both' => [['watchdog:custom_type'], ['watchdog:custom_type'], ['custom_type', 'php']];
  }

  /**
   * Builds a Drupal driver double reporting whether dblog is enabled.
   */
  protected function createDrupalDriver(bool $dblog): DrupalDriverInterface {
    $driver = $this->createStub(DrupalDriverInterface::class);
    $driver->method('moduleIsEnabled')->willReturn($dblog);

    return $driver;
  }

  /**
   * Builds a context whose scenario lists the given drivers, in order.
   *
   * @param array<string, \DrevOps\BehatSteps\Driver\DriverInterface> $drivers
   *   The drivers, keyed by the name the scenario lists each one under.
   */
  protected function createContext(array $drivers): WatchdogTraitTestImplementation {
    $registry = new DriverRegistry($drivers);
    $names = array_keys($drivers);
    $registry->setScenarioDrivers(array_combine($names, $names));

    $context = new WatchdogTraitTestImplementation();
    $context->setDriverRegistry($registry);

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

  /**
   * Whether the scenario's follow-up hooks read the log.
   */
  public function isArmed(): bool {
    return $this->watchdogScenarioStartTime !== NULL;
  }

  /**
   * Returns the message types the scenario is checked for.
   *
   * @return array<int, string>
   *   The message types.
   */
  public function getMessageTypes(): array {
    return $this->watchdogMessageTypes;
  }

}
