<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Drupal\big_pipe\Render\Placeholder\BigPipeStrategy;

/**
 * Bypass Drupal BigPipe when rendering pages.
 *
 * Activated by adding `@big_pipe` tag to the scenario.
 *
 * Skip processing with tags: `@behat-steps-skip:bigPipeBeforeScenario` or
 * `@behat-steps-skip:bigPipeBeforeStep`.
 */
trait BigPipeTrait {

  /**
   * Flag indicating that the driver supports JavaScript.
   */
  protected bool $bigPipeJsIsSupported = FALSE;

  /**
   * Skip Big Pipe BeforeStep.
   */
  protected bool $bigPipeSkipBeforeStep = TRUE;

  /**
   * Initialize BigPipe settings before scenario.
   *
   * @BeforeScenario
   */
  public function bigPipeBeforeScenario(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $this->bigPipeSkipBeforeStep = FALSE;

    // Allow to skip resetting cookies on step.
    // BeforeStep scope does not have access to scenario where tagging is
    // made.
    if ($scope->getScenario()->hasTag('behat-steps-skip:bigPipeBeforeStep')) {
      $this->bigPipeSkipBeforeStep = TRUE;
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
      $this->bigPipeJsIsSupported = TRUE;
    }
    catch (UnsupportedDriverActionException) {
      $this->bigPipeJsIsSupported = FALSE;
      $this->getSession()->setCookie(BigPipeStrategy::NOJS_COOKIE, 'true');
    }
    catch (\Exception) {
      // Mute exceptions.
    }
  }

  /**
   * Prepare Big Pipe NOJS cookie if needed.
   *
   * @BeforeStep
   */
  public function bigPipeBeforeStep(BeforeStepScope $scope): void {
    if ($this->bigPipeSkipBeforeStep) {
      return;
    }

    try {
      if (!$this->bigPipeJsIsSupported && !$this->getSession()->getCookie(BigPipeStrategy::NOJS_COOKIE)) {
        $this->getSession()->setCookie(BigPipeStrategy::NOJS_COOKIE, 'true');
      }
    }
    catch (DriverException) {
      // Mute not visited page exception.
      return;
    }
  }

}
