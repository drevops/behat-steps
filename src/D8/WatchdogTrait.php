<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\Core\Database\Database;

/**
 * Trait WatchdogTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait WatchdogTrait {

  /**
   * Start time for each scenario.
   *
   * @var int
   */
  protected $watchdogScenarioStartTime;

  /**
   * Store current time.
   *
   * @BeforeScenario
   */
  public function watchdogSetScenarioStartTime(BeforeScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $this->watchdogScenarioStartTime = time();
  }

  /**
   * Check for errors since the scenario started.
   *
   * Add @error to any scenario that is expected to trigger an error - the
   * error tracking will be ignored.
   *
   * @AfterScenario
   */
  public function watchdogAssertErrors(AfterScenarioScope $scope) {
    $database = Database::getConnection();
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    // Bypass the error checking if the scenario is expected to trigger an
    // error. Such scenarios should be tagged with "@error".
    if (in_array('error', $scope->getScenario()->getTags())) {
      return;
    }

    if (!$database->schema()->tableExists('watchdog')) {
      return;
    }

    // Select all logged entries for PHP channel that appeared from the start
    // of the scenario.
    $entries = $database->select('watchdog', 'w')
      ->fields('w')
      ->condition('w.type', 'php', '=')
      ->condition('w.timestamp', $this->watchdogScenarioStartTime, '>=')
      ->execute()
      ->fetchAll();

    if (empty($entries)) {
      return;
    }

    $errors = [];
    if (!defined('WATCHDOG_WARNING')) {
      define('WATCHDOG_WARNING', 4);
    }

    // Remove entries below severity threshold.
    foreach ($entries as $k => $error) {
      if ($error->severity > WATCHDOG_WARNING) {
        unset($entries[$k]);
        continue;
      }
      $error->variables = unserialize($error->variables);
      $errors[$error->wid] = print_r($error, TRUE);
    }

    if (!empty($errors)) {
      $database->delete('watchdog')
        ->condition('wid', array_keys($errors), 'IN')
        ->execute();

      throw new \Exception(sprintf('PHP errors were logged to watchdog during this scenario: %s', PHP_EOL . implode(PHP_EOL . PHP_EOL, $errors)));
    }
  }

}
