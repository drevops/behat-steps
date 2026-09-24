<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Environment\Environment;
use Behat\Behat\Tester\Result\StepResult;
use DrevOps\BehatSteps\Helper\LastStepTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests for LastStepTrait.
 */
#[CoversTrait(LastStepTrait::class)]
class LastStepTraitTest extends UnitTestCase {

  /**
   * A test implementation of LastStepTrait.
   */
  protected LastStepTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new LastStepTraitTestImplementation();
  }

  public function testNoStepIsTheLastOneBeforeTheScenarioIsCaptured(): void {
    $this->assertFalse($this->testObject->callReached($this->createAfterStepScope(10)));
  }

  public function testScenarioWithoutStepsMarksNoLastStep(): void {
    $this->testObject->callCapture($this->createScenarioScopeForLines([]));

    $this->assertFalse($this->testObject->callReached($this->createAfterStepScope(10)));
  }

  public function testTheFinalStepLineIsTheLastStep(): void {
    $this->testObject->callCapture($this->createScenarioScopeForLines([10, 11, 12]));

    $this->assertTrue($this->testObject->callReached($this->createAfterStepScope(12)));
  }

  public function testAnEarlierStepLineIsNotTheLastStep(): void {
    $this->testObject->callCapture($this->createScenarioScopeForLines([10, 11, 12]));

    $this->assertFalse($this->testObject->callReached($this->createAfterStepScope(11)));
  }

  /**
   * Builds a BeforeScenario scope over a scenario with steps on the given lines.
   *
   * @param array<int, int> $lines
   *   The line each step sits on, in scenario order.
   */
  protected function createScenarioScopeForLines(array $lines): BeforeScenarioScope {
    $steps = array_map(static fn(int $line): StepNode => new StepNode('Given', 'a step', [], $line, 'Given'), $lines);

    $scenario = new ScenarioNode('Scenario', [], $steps, 'Scenario', 1);
    $feature = new FeatureNode('Feature', NULL, [], NULL, [$scenario], 'Feature', 'en', __FILE__, 1);

    return new BeforeScenarioScope($this->createStub(Environment::class), $feature, $scenario);
  }

  /**
   * Builds an AfterStep scope for a step on the given line.
   */
  protected function createAfterStepScope(int $line): AfterStepScope {
    $step = new StepNode('Given', 'a step', [], $line, 'Given');
    $feature = new FeatureNode('Feature', NULL, [], NULL, [], 'Feature', 'en', __FILE__, 1);

    return new AfterStepScope($this->createStub(Environment::class), $feature, $step, $this->createStub(StepResult::class));
  }

}

/**
 * Test implementation of LastStepTrait.
 *
 * Exposes the protected helper methods under the test.
 */
class LastStepTraitTestImplementation {

  use LastStepTrait;

  public function callCapture(BeforeScenarioScope $scope): void {
    $this->setLastStepLine($scope);
  }

  public function callReached(AfterStepScope $scope): bool {
    return $this->isLastStep($scope);
  }

}
