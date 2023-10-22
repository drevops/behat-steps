<?php

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Drupal\big_pipe\Render\Placeholder\BigPipeStrategy;

/**
 * Big Pipe trait.
 *
 * Behat trait for handling BigPipe functionality.
 */
trait BigPipeTrait {

  /**
   * Flag for JS not supported by driver.
   *
   * @var bool
   */
  protected $bigPipeNoJS;

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
      $this->bigPipeNoJS = FALSE;
    }
    catch (UnsupportedDriverActionException $e) {
      $this->bigPipeNoJS = TRUE;
    }
    catch (\Exception $e) {
      // Mute exceptions.
    }
  }

  /**
   * Prepares Big Pipe NO JS cookie if needed.
   *
   * @BeforeStep
   */
  public function bigPipeBeforeStep(BeforeStepScope $scope) {
    if ($this->bigPipeNoJS) {
      $this
        ->getSession()
        ->setCookie(BigPipeStrategy::NOJS_COOKIE, '1');
    }
  }

}
