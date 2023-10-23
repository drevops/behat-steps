<?php

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Exception\DriverException;
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
   * Skip Big Pipe BeforeStep.
   *
   * @var bool
   */
  protected bool $skipBigPipeBeforeStep = FALSE;

  /**
   * Prepares Big Pipe NOJS cookie if needed.
   *
   * @BeforeScenario
   */
  public function bigPipeBeforeScenarioInit(BeforeScenarioScope $scope) {
    // Allow to skip resetting cookies on step.
    if ($scope->getScenario()->hasTag('behat-steps-skip:bigPipeBeforeStep')) {
      $this->skipBigPipeBeforeStep = TRUE;
    }
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
      $this
        ->getSession()
        ->setCookie(BigPipeStrategy::NOJS_COOKIE, 'true');
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
    if ($this->skipBigPipeBeforeStep) {
      return;
    }
    try {
      if ($this->bigPipeNoJS && !$this->getSession()->getCookie(BigPipeStrategy::NOJS_COOKIE)) {
        $this
          ->getSession()
          ->setCookie(BigPipeStrategy::NOJS_COOKIE, 'true');
      }
    }
    catch (DriverException $e) {
      // Mute not visited page exception.
    }
  }

}
