<?php

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Drupal\big_pipe\Render\Placeholder\BigPipeStrategy;

/**
 * Big Pipe trait.
 *
 * Behat trait for handling BigPipe functionality.
 */
trait BigPipeTrait {

  /**
   * Prepares Big Pipe NOJS cookie if needed.
   *
   * @BeforeScenario
   */
  public function bigPipeBeforeScenarioInit(BeforeScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if (!\Drupal::hasService('big_pipe')) {
      return;
    }

    try {
      // Check if JavaScript can be executed by the driver and add a cookie
      // if it cannot.
      $driver = $this->getSession()->getDriver();
      if (!$driver->isStarted()) {
        $driver->start();
      }
      $driver->executeScript('true');
    }
    catch (UnsupportedDriverActionException $e) {
      $this
        ->getSession()
        ->setCookie(BigPipeStrategy::NOJS_COOKIE, 'true');
    }
    catch (\Exception $e) {
      // Mute exceptions.
    }
  }

}
