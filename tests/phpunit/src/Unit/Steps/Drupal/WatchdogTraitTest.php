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
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\DrushDriverInterface;
use DrevOps\BehatSteps\Steps\Drupal\WatchdogTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests which drivers arm the Watchdog check.
 */
#[CoversTrait(WatchdogTrait::class)]
class WatchdogTraitTest extends UnitTestCase {

  /**
   * Tests that only a driver running Drupal in-process arms the check.
   *
   * @param array<string, class-string<\DrevOps\BehatSteps\Driver\DriverInterface>> $drivers
   *   Driver interfaces to stub, keyed by the name the scenario lists each
   *   one under, in order.
   * @param bool $expected
   *   Whether the check is armed.
   */
  #[DataProvider('dataProviderSetScenarioArmsTheCheck')]
  public function testSetScenarioArmsTheCheck(array $drivers, bool $expected): void {
    $context = $this->createContext($drivers);

    $context->watchdogSetScenario($this->createBeforeScenarioScope());

    $this->assertSame($expected, $context->isArmed());
  }

  public static function dataProviderSetScenarioArmsTheCheck(): array {
    return [
      'in-process driver' => [['drupal' => DrupalDriverInterface::class], TRUE],
      'Drush driver alone' => [['drush' => DrushDriverInterface::class], FALSE],
      'Drush driver ahead of the in-process driver' => [['drush' => DrushDriverInterface::class, 'drupal' => DrupalDriverInterface::class], TRUE],
      'driver without a Drupal capability' => [['blackbox' => DriverInterface::class], FALSE],
      'no driver' => [[], FALSE],
    ];
  }

  public function testUnarmedScenarioResolvesNoDriver(): void {
    $this->expectNotToPerformAssertions();

    $context = $this->createContext(['drush' => DrushDriverInterface::class]);

    $step = new StepNode('When', 'I visit "/"', [], 2, 'When');
    $scenario = new ScenarioNode('Scenario', [], [$step], 'Scenario', 1);
    $feature = new FeatureNode('Feature', NULL, [], NULL, [$scenario], 'Feature', 'en', __DIR__ . '/feature.feature', 1);
    $environment = $this->createStub(Environment::class);

    // No driver provides the in-process capability, so resolving one throws.
    $context->watchdogSetScenario(new BeforeScenarioScope($environment, $feature, $scenario));
    $context->watchdogAfterStep(new AfterStepScope($environment, $feature, $step, $this->createStub(StepResult::class)));
    $context->watchdogAfterScenario(new AfterScenarioScope($environment, $feature, $scenario, $this->createStub(TestResult::class)));
  }

  /**
   * Builds a context whose scenario lists stubs of the given drivers.
   *
   * @param array<string, class-string<\DrevOps\BehatSteps\Driver\DriverInterface>> $drivers
   *   Driver interfaces to stub, keyed by the name the scenario lists each
   *   one under, in order.
   */
  protected function createContext(array $drivers): WatchdogTraitTestImplementation {
    $registry = new DriverRegistry();

    foreach ($drivers as $name => $interface) {
      $registry->registerDriver($name, $this->createStub($interface));
    }

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

}
