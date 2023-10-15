<?php

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\Core\Database\Database;

/**
 * Trait WatchdogTrait.
 *
 * Watchdog-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait WatchdogTrait {

  /**
   * Start time for each scenario.
   *
   * @var int
   */
  protected $watchdogScenarioStartTime;

  /**
   * Array of watchdog message types.
   *
   * @var array
   */
  protected $watchdogMessageTypes = [];

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

    $this->watchdogMessageTypes = $this->watchdogParseMessageTypes($scope->getScenario()->getTags());
  }

  /**
   * Parse scenario tags into message types.
   *
   * @code
   * @watchdog:my_module_type @watchdog:my_other_module_type
   * @endcode
   *
   * @param array $tags
   *   Array of scenario tags.
   * @param string $prefix
   *   Optional tag prefix to filter by.
   *
   * @return array
   *   Array of message types. 'php' is always added to the list.
   */
  protected function watchdogParseMessageTypes(array $tags = [], $prefix = 'watchdog:') {
    $types = [];
    foreach ($tags as $tag) {
      if (strpos($tag, $prefix) === 0 && strlen($tag) > strlen($prefix)) {
        $types[] = substr($tag, strlen($prefix));
      }
    }

    return array_unique(array_merge($types, ['php']));
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
      ->condition('w.type', $this->watchdogMessageTypes, 'IN')
      ->condition('w.timestamp', (string) $this->watchdogScenarioStartTime, '>=')
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

      throw new \Exception(sprintf(
          'PHP errors were logged to watchdog during scenario "%s" (line %s): %s',
          $scope->getScenario()->getTitle(),
          $scope->getScenario()->getLine(),
          PHP_EOL . implode(PHP_EOL . PHP_EOL, $errors))
      );
    }
  }

}
