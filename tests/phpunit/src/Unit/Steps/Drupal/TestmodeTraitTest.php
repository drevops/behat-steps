<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistry;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Steps\Drupal\TestmodeTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests switching Testmode on and off around a scenario.
 */
#[CoversTrait(TestmodeTrait::class)]
class TestmodeTraitTest extends UnitTestCase {

  public function testTeardownOfScenarioThatEnabledNothingTouchesNoDriver(): void {
    $this->expectNotToPerformAssertions();

    // The context holds no driver registry, so resolving a driver would throw.
    (new TestmodeTraitTestImplementation())->testmodeAfterScenario($this->createAfterScenarioScope(['testmode']));
  }

  public function testUnmetPrerequisiteFailsTheSetupAndLeavesNothingToUndo(): void {
    $drupal = $this->createStub(DrupalDriverInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    $registry = new DriverRegistry(['drupal' => $drupal]);
    $registry->setScenarioDrivers(['drupal' => 'drupal']);

    $context = new TestmodeTraitTestImplementation();
    $context->setDriverRegistry($registry);

    try {
      $context->testmodeBeforeScenario($this->createBeforeScenarioScope(['testmode']));
      $this->fail('A missing "testmode" module did not fail the setup.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('TestmodeTrait requires that the "testmode" module from the "drupal/testmode" package is enabled, which does not hold. Meet the prerequisite, or switch TestmodeTrait off with the "testmode.enabled" option or the "@behat-steps-skip:TestmodeTrait" tag.', $exception->getMessage());
    }

    $this->assertFalse($context->isActive());
  }

}

/**
 * Test implementation of TestmodeTrait.
 *
 * Exposes whether the scenario enabled test mode.
 */
class TestmodeTraitTestImplementation extends WebRawContext {

  use TestmodeTrait;

  /**
   * Whether the scenario enabled test mode.
   */
  public function isActive(): bool {
    return $this->testmodeActive;
  }

}
