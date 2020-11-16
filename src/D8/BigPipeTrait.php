<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Driver\Selenium2Driver;
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
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __METHOD__)) {
      return;
    }

    if (!\Drupal::hasService('big_pipe')) {
      return;
    }

    $driver = $this->getSession()->getDriver();
    if ($driver instanceof Selenium2Driver) {
      // Start driver's session manually if it is not already started.
      if (!$driver->isStarted()) {
        $driver->start();
      }
    }

    try {
      // Check if JavaScript can be executed by Driver.
      $this->getSession()->getDriver()->executeScript('true');
    }
    catch (UnsupportedDriverActionException $e) {
      $this
        ->getSession()
        ->setCookie(BigPipeStrategy::NOJS_COOKIE, TRUE);
    }
    catch (\Exception $e) {
      // Mute exceptions.
    }
  }

}
