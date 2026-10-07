<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\TestmodeTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests switching Testmode on and off around a scenario.
 */
#[CoversTrait(TestmodeTrait::class)]
class TestmodeTraitTest extends UnitTestCase {

  public function testTeardownOfScenarioThatEnabledNothingTouchesNoBackend(): void {
    $this->expectNotToPerformAssertions();

    // The context holds no backend registry, so resolving a backend would
    // throw.
    (new TestmodeTraitTestImplementation())->testmodeAfterScenario($this->createAfterScenarioScope(['testmode']));
  }

  public function testUnmetPrerequisiteFailsTheSetupAndLeavesNothingToUndo(): void {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    $registry = new BackendRegistry(['drupal' => $drupal]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new TestmodeTraitTestImplementation();
    $context->setBackendRegistry($registry);

    try {
      $context->testmodeBeforeScenario($this->createBeforeScenarioScope(['testmode']));
      $this->fail('A missing "testmode" module did not fail the setup.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('TestmodeTrait requires that the "testmode" module from the "drupal/testmode" package is enabled, which does not hold. Meet the prerequisite, or switch TestmodeTrait off with the "testmode.enabled" option or the "@behat-steps-skip:TestmodeTrait" tag.', $exception->getMessage());
    }

    $this->assertFalse($context->testIsActive());
  }

}

/**
 * Test implementation of TestmodeTrait.
 *
 * Exposes whether the scenario enabled test mode.
 */
class TestmodeTraitTestImplementation extends WebRawContext {

  use TestmodeTrait;

  public function testIsActive(): bool {
    return $this->testmodeActive;
  }

}
