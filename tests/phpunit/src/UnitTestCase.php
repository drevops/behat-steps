<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use AlexSkrypnyk\PhpunitHelpers\UnitTestCase as UpstreamUnitTestCase;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Testwork\Environment\Environment;
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
   */
  protected function createBeforeScenarioScope(): BeforeScenarioScope {
    return new BeforeScenarioScope($this->createStub(Environment::class), $this->createStub(FeatureNode::class), $this->createStub(ScenarioInterface::class));
  }

  /**
   * Build a scope for an AfterScenario hook.
   */
  protected function createAfterScenarioScope(): AfterScenarioScope {
    return new AfterScenarioScope($this->createStub(Environment::class), $this->createStub(FeatureNode::class), $this->createStub(ScenarioInterface::class), $this->createStub(TestResult::class));
  }

}
