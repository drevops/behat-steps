<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Drupal\Core\Database\Database;

/**
 * Assert Drupal does not trigger PHP errors during scenarios using Watchdog.
 *
 * - Check for Watchdog messages after scenario completion.
 * - Optionally check only for specific message types.
 * - Optionally skip error checking for specific scenarios.
 *
 * Skip processing with tags: `@behat-steps-skip:watchdogSetScenario` or
 * `@behat-steps-skip:watchdogAfterScenario`
 *
 * Special tags:
 * - `@watchdog:{type}` - limit watchdog messages to specific types.
 * - `@error` - add to scenarios that are expected to trigger an error.
 */
trait WatchdogTrait {

  /**
   * Start time for each scenario.
   *
   * @var int|null
   */
  protected $watchdogScenarioStartTime;

  /**
   * Array of watchdog message types.
   *
   * @var array<int, string>
   */
  protected $watchdogMessageTypes = [];

  /**
   * Title of the current scenario.
   */
  protected string $watchdogScenarioTitle = '';

  /**
   * Line of the current scenario within its feature file.
   */
  protected int $watchdogScenarioLine = 0;

  /**
   * Store current time.
   */
  #[BeforeScenario('@api')]
  public function watchdogSetScenario(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $scenario = $scope->getScenario();

    // Step scopes carry neither scenario tags nor scenario identity, so both
    // are resolved here for the step hook to read. An unset start time is what
    // disables the check.
    if ($scenario->hasTag('behat-steps-skip:watchdogAfterScenario') || $scenario->hasTag('error')) {
      return;
    }

    $this->watchdogScenarioStartTime = time();
    $this->watchdogScenarioTitle = $scenario->getTitle() ?? '';
    $this->watchdogScenarioLine = $scenario->getLine();

    $this->watchdogMessageTypes = $this->watchdogParseMessageTypes($scenario->getTags());
  }

  /**
   * Parse scenario tags into message types.
   *
   * @code
   * @watchdog:my_module_type @watchdog:my_other_module_type
   * @endcode
   *
   * @param array<int,string> $tags
   *   Array of scenario tags.
   * @param string $prefix
   *   Optional tag prefix to filter by.
   *
   * @return array<int,string>
   *   Array of message types. 'php' is always added to the list.
   */
  protected function watchdogParseMessageTypes(array $tags = [], string $prefix = 'watchdog:'): array {
    $types = [];
    foreach ($tags as $tag) {
      if (str_starts_with((string) $tag, $prefix) && strlen((string) $tag) > strlen($prefix)) {
        $types[] = substr((string) $tag, strlen($prefix));
      }
    }

    return array_unique(array_merge($types, ['php']));
  }

  /**
   * Check after every step for errors logged since the scenario started.
   *
   * Add @error to any scenario that is expected to trigger an error - the
   * error tracking will be ignored. Skip the check for a scenario with
   * `@behat-steps-skip:watchdogAfterScenario`.
   *
   * Behat composes a step teardown into that step's result, so a failure
   * raised here marks the scenario as failed for the rerun cache.
   */
  #[AfterStep]
  public function watchdogAfterStep(): void {
    // The start time is set only for '@api' scenarios that opted into the
    // check.
    if (!isset($this->watchdogScenarioStartTime)) {
      return;
    }

    $database = Database::getConnection();

    if (!$database->schema()->tableExists('watchdog')) {
      throw new \RuntimeException('Watchdog table does not exist. Ensure the dblog module is enabled.');
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

      throw new ExpectationException(sprintf('PHP errors were logged to watchdog during scenario "%s" (line %s): %s', $this->watchdogScenarioTitle, $this->watchdogScenarioLine, PHP_EOL . implode(PHP_EOL . PHP_EOL, $errors)), $this->getSession()->getDriver());
    }
  }

}
