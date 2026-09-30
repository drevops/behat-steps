<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Tester;

use Behat\Behat\Tester\ScenarioTester;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\IntegerTestResult;
use Behat\Testwork\Tester\Result\TestResult;
use Behat\Testwork\Tester\Result\TestResults;
use Behat\Testwork\Tester\Setup\FailedSetup;
use Behat\Testwork\Tester\Setup\FailedTeardown;
use Behat\Testwork\Tester\Setup\Setup;
use Behat\Testwork\Tester\Setup\SuccessfulSetup;
use Behat\Testwork\Tester\Setup\SuccessfulTeardown;
use Behat\Testwork\Tester\Setup\Teardown;
use DrevOps\BehatSteps\Behat\Tester\SetupFailureScenarioTester;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that a scenario whose setup failed reports as failed.
 */
#[CoversClass(SetupFailureScenarioTester::class)]
class SetupFailureScenarioTesterTest extends UnitTestCase {

  /**
   * Tests that the wrapped tester's setup is returned as it is.
   *
   * @param \Behat\Testwork\Tester\Setup\Setup $setup
   *   The setup the wrapped tester returns.
   * @param bool $skip
   *   Whether the scenario is skipped from outside.
   */
  #[DataProvider('dataProviderSetUpReturnsTheWrappedSetup')]
  public function testSetUpReturnsTheWrappedSetup(Setup $setup, bool $skip): void {
    [$feature, $scenario] = $this->createScenarioNodes([], []);
    $environment = $this->createStub(Environment::class);

    $base_tester = $this->createMock(ScenarioTester::class);
    $base_tester->expects($this->once())->method('setUp')->with($environment, $feature, $scenario, $skip)->willReturn($setup);

    $this->assertSame($setup, (new SetupFailureScenarioTester($base_tester))->setUp($environment, $feature, $scenario, $skip));
  }

  public static function dataProviderSetUpReturnsTheWrappedSetup(): \Iterator {
    yield 'a successful setup' => [new SuccessfulSetup(), FALSE];
    yield 'a failed setup' => [new FailedSetup(), FALSE];
    yield 'a setup skipped from outside' => [new SuccessfulSetup(), TRUE];
  }

  /**
   * Tests that a scenario whose setup succeeded keeps its own result.
   *
   * @param \Behat\Testwork\Tester\Result\TestResult $result
   *   The result the wrapped tester returns.
   * @param bool $skip
   *   Whether the scenario is skipped from outside.
   */
  #[DataProvider('dataProviderSuccessfulSetupKeepsTheResult')]
  public function testSuccessfulSetupKeepsTheResult(TestResult $result, bool $skip): void {
    [$feature, $scenario] = $this->createScenarioNodes([], []);
    $environment = $this->createStub(Environment::class);

    $base_tester = $this->createMock(ScenarioTester::class);
    $base_tester->method('setUp')->willReturn(new SuccessfulSetup());
    $base_tester->expects($this->once())->method('test')->with($environment, $feature, $scenario, $skip)->willReturn($result);

    $tester = new SetupFailureScenarioTester($base_tester);
    $tester->setUp($environment, $feature, $scenario, $skip);

    $this->assertSame($result, $tester->test($environment, $feature, $scenario, $skip));
  }

  public static function dataProviderSuccessfulSetupKeepsTheResult(): \Iterator {
    yield 'passed steps' => [new IntegerTestResult(TestResult::PASSED), FALSE];
    yield 'a failed step' => [new IntegerTestResult(TestResult::FAILED), FALSE];
    yield 'steps skipped from outside' => [new IntegerTestResult(TestResult::SKIPPED), TRUE];
    yield 'no steps' => [new TestResults([]), FALSE];
  }

  /**
   * Tests that a scenario whose setup failed reports as failed.
   *
   * @param \Behat\Testwork\Tester\Result\TestResult $result
   *   The result the wrapped tester returns.
   */
  #[DataProvider('dataProviderFailedSetupFailsTheResult')]
  public function testFailedSetupFailsTheResult(TestResult $result): void {
    [$feature, $scenario] = $this->createScenarioNodes([], []);
    $environment = $this->createStub(Environment::class);

    $base_tester = $this->createMock(ScenarioTester::class);
    $base_tester->method('setUp')->willReturn(new FailedSetup());
    $base_tester->expects($this->once())->method('test')->with($environment, $feature, $scenario, TRUE)->willReturn($result);

    $tester = new SetupFailureScenarioTester($base_tester);
    $tester->setUp($environment, $feature, $scenario, FALSE);
    $failed = $tester->test($environment, $feature, $scenario, TRUE);

    $this->assertSame(TestResult::FAILED, $failed->getResultCode());
    $this->assertFalse($failed->isPassed());
  }

  public static function dataProviderFailedSetupFailsTheResult(): \Iterator {
    yield 'skipped steps' => [new IntegerTestResult(TestResult::SKIPPED)];
    yield 'skipped steps collected as results' => [new TestResults([new IntegerTestResult(TestResult::SKIPPED), new IntegerTestResult(TestResult::SKIPPED)])];
    yield 'no steps' => [new TestResults([])];
    yield 'passed steps' => [new IntegerTestResult(TestResult::PASSED)];
    yield 'pending steps' => [new IntegerTestResult(TestResult::PENDING)];
    yield 'undefined steps' => [new IntegerTestResult(TestResult::UNDEFINED)];
  }

  /**
   * Tests that the wrapped tester's teardown is returned as it is.
   *
   * @param \Behat\Testwork\Tester\Setup\Teardown $teardown
   *   The teardown the wrapped tester returns.
   * @param bool $skip
   *   Whether the scenario is skipped.
   */
  #[DataProvider('dataProviderTearDownReturnsTheWrappedTeardown')]
  public function testTearDownReturnsTheWrappedTeardown(Teardown $teardown, bool $skip): void {
    [$feature, $scenario] = $this->createScenarioNodes([], []);
    $environment = $this->createStub(Environment::class);
    $result = new IntegerTestResult(TestResult::PASSED);

    $base_tester = $this->createMock(ScenarioTester::class);
    $base_tester->expects($this->once())->method('tearDown')->with($environment, $feature, $scenario, $skip, $result)->willReturn($teardown);

    $this->assertSame($teardown, (new SetupFailureScenarioTester($base_tester))->tearDown($environment, $feature, $scenario, $skip, $result));
  }

  public static function dataProviderTearDownReturnsTheWrappedTeardown(): \Iterator {
    yield 'a successful teardown' => [new SuccessfulTeardown(), FALSE];
    yield 'a failed teardown' => [new FailedTeardown(), FALSE];
    yield 'a skipped teardown' => [new SuccessfulTeardown(), TRUE];
  }

  public function testTearDownForgetsTheFailedSetup(): void {
    [$feature, $scenario] = $this->createScenarioNodes([], []);
    $environment = $this->createStub(Environment::class);
    $result = new IntegerTestResult(TestResult::SKIPPED);

    $tester = new SetupFailureScenarioTester($this->createBaseTester(new FailedSetup(), $result));
    $tester->setUp($environment, $feature, $scenario, FALSE);
    $failed = $tester->test($environment, $feature, $scenario, TRUE);
    $tester->tearDown($environment, $feature, $scenario, TRUE, $failed);

    $this->assertSame(TestResult::FAILED, $failed->getResultCode());
    $this->assertSame($result, $tester->test($environment, $feature, $scenario, TRUE));
  }

  public function testFailedSetupStaysWithItsScenario(): void {
    [$feature, $scenario] = $this->createScenarioNodes([], []);
    $other_scenario = new ScenarioNode('Other scenario', [], [], 'Scenario', 2);
    $environment = $this->createStub(Environment::class);
    $result = new IntegerTestResult(TestResult::SKIPPED);

    $tester = new SetupFailureScenarioTester($this->createBaseTester(new FailedSetup(), $result));
    $tester->setUp($environment, $feature, $scenario, FALSE);

    $this->assertSame($result, $tester->test($environment, $feature, $other_scenario, TRUE));
    $this->assertSame(TestResult::FAILED, $tester->test($environment, $feature, $scenario, TRUE)->getResultCode());
  }

  /**
   * Builds a wrapped tester returning one setup and one result.
   *
   * @param \Behat\Testwork\Tester\Setup\Setup $setup
   *   The setup every call to 'setUp()' returns.
   * @param \Behat\Testwork\Tester\Result\TestResult $result
   *   The result every call to 'test()' returns.
   */
  protected function createBaseTester(Setup $setup, TestResult $result): ScenarioTester {
    $base_tester = $this->createStub(ScenarioTester::class);
    $base_tester->method('setUp')->willReturn($setup);
    $base_tester->method('test')->willReturn($result);
    $base_tester->method('tearDown')->willReturn(new SuccessfulTeardown());

    return $base_tester;
  }

}
