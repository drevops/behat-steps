<?php

/**
 * @file
 * Feature context for testing the Drupal half of Behat-steps.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 */

declare(strict_types=1);

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Tag;

/**
 * Defines application features from the specific context.
 */
class DrupalFeatureContext extends DrupalContext {

  use DrupalFeatureContextTrait;

  /**
   * Shorten the BigPipe wait timeout for the timeout coverage scenario.
   *
   * Scenarios tagged '@test-bigpipe-timeout' use a short timeout so they can
   * exercise the wait timing out quickly; every other scenario keeps the trait's
   * default.
   */
  #[BeforeScenario]
  public function bigPipeSetWaitTimeout(BeforeScenarioScope $scope): void {
    $this->bigPipeWaitTimeout = Tag::has($scope->getScenario(), 'test-bigpipe-timeout') ? 2000 : NULL;
  }

}
