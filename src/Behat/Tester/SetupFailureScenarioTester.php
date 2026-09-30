<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Tester;

use Behat\Behat\Tester\ScenarioTester;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\TestResult;
use Behat\Testwork\Tester\Result\TestWithSetupResult;
use Behat\Testwork\Tester\Setup\Setup;
use Behat\Testwork\Tester\Setup\SuccessfulTeardown;
use Behat\Testwork\Tester\Setup\Teardown;

/**
 * Reports a scenario whose setup failed as failed.
 *
 * Behat skips every step of a scenario whose 'BeforeScenario' hook threw. It
 * dispatches 'AfterScenarioTested' with the step results alone, so the
 * summary counts that scenario as skipped.
 *
 * The result this tester returns holds the failed setup as well, so the
 * scenario counts as failed. The wrapped tester is the one that dispatches
 * the 'BeforeScenario' hooks.
 */
class SetupFailureScenarioTester implements ScenarioTester {

  /**
   * Setups that failed, keyed by the scenario they belong to.
   *
   * @var \WeakMap<\Behat\Gherkin\Node\ScenarioInterface, \Behat\Testwork\Tester\Setup\Setup>
   */
  protected \WeakMap $failedSetups;

  /**
   * Constructs a SetupFailureScenarioTester.
   *
   * @param \Behat\Behat\Tester\ScenarioTester $baseTester
   *   The tester this one wraps.
   */
  public function __construct(
    protected readonly ScenarioTester $baseTester,
  ) {
    $this->failedSetups = new \WeakMap();
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(Environment $env, FeatureNode $feature, ScenarioInterface $scenario, mixed $skip): Setup {
    $setup = $this->baseTester->setUp($env, $feature, $scenario, $skip);

    if (!$setup->isSuccessful()) {
      $this->failedSetups[$scenario] = $setup;
    }

    return $setup;
  }

  /**
   * {@inheritdoc}
   */
  public function test(Environment $env, FeatureNode $feature, ScenarioInterface $scenario, mixed $skip): TestResult {
    $result = $this->baseTester->test($env, $feature, $scenario, $skip);
    $setup = $this->failedSetups[$scenario] ?? NULL;

    if ($setup === NULL) {
      return $result;
    }

    return new TestWithSetupResult($setup, $result, new SuccessfulTeardown());
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(Environment $env, FeatureNode $feature, ScenarioInterface $scenario, mixed $skip, TestResult $result): Teardown {
    unset($this->failedSetups[$scenario]);

    return $this->baseTester->tearDown($env, $feature, $scenario, $skip, $result);
  }

}
