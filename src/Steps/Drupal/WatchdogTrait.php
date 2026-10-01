<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Helper\Web\LastStepTrait;
use Drupal\Core\Database\Database;

/**
 * Assert Drupal does not trigger PHP errors during scenarios using Watchdog.
 *
 * - Check for Watchdog messages after scenario completion.
 * - Optionally check only for specific message types.
 * - Optionally skip error checking for specific scenarios.
 *
 * The check is on by default. An opted-in scenario whose prerequisites do not
 * hold fails at its start.
 *
 * `watchdog.fail_on_errors` and `@error` decide what happens to errors that
 * were read, so they do not cover an unmet prerequisite.
 *
 * Skip processing with tag: `@behat-steps-skip:WatchdogTrait`.
 *
 * Special tags:
 * - `@watchdog:{type}` - limit watchdog messages to specific types.
 * - `@error` - add to scenarios that are expected to trigger an error. The
 *   errors are still read and cleared; the scenario is not failed.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait WatchdogTrait {

  use LastStepTrait;

  /**
   * The tag that adds its value to the message types the scenario tracks.
   */
  protected const WATCHDOG_TAG = 'watchdog';

  /**
   * The tag that keeps a scenario logging an error from failing.
   */
  protected const WATCHDOG_ERROR_TAG = 'error';

  /**
   * Start time for each scenario.
   */
  protected ?int $watchdogScenarioStartTime = NULL;

  /**
   * Array of watchdog message types.
   *
   * @var array<int, string>
   */
  protected array $watchdogMessageTypes = [];

  /**
   * Title of the current scenario.
   */
  protected string $watchdogScenarioTitle = '';

  /**
   * Line of the current scenario within its feature file.
   */
  protected int $watchdogScenarioLine = 0;

  /**
   * Store the scenario identity, tracked message types and start time.
   */
  #[BeforeScenario]
  public function watchdogSetScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    $this->assertPrerequisites(__TRAIT__);

    $scenario = $scope->getScenario();

    $this->watchdogScenarioStartTime = time();
    $this->watchdogScenarioTitle = $scenario->getTitle() ?? '';
    $this->watchdogScenarioLine = $scenario->getLine();

    $this->watchdogMessageTypes = array_values(array_unique([...Tag::values($scope, self::WATCHDOG_TAG), 'php']));

    $this->lastStepSetLine($scope);
  }

  /**
   * Check for every error logged since the scenario started, on its last step.
   *
   * Behat composes a step teardown into that step's result, so a failure
   * raised here marks the scenario as failed for the rerun cache. Checking on
   * the last step rather than on every step keeps the whole scenario in scope,
   * so every error it logged is reported together.
   */
  #[AfterStep]
  public function watchdogAfterStep(AfterStepScope $scope): void {
    if (!isset($this->watchdogScenarioStartTime) || !$this->lastStepReached($scope)) {
      return;
    }

    $this->assertPrerequisites(__TRAIT__);

    if (!$this->getOptionBool('watchdog', 'fail_on_errors')) {
      $this->watchdogReadErrors();

      return;
    }

    $this->watchdogAssertNotHasErrors(sprintf('during scenario "%s" (line %s)', $this->watchdogScenarioTitle, $this->watchdogScenarioLine));
  }

  /**
   * Check for errors that the last step could not have seen.
   *
   * A scenario whose earlier step failed never ran its last step, so nothing
   * was checked at step scope. A scenario that passed may still log an error
   * while another trait tears it down, after the last step result has been
   * composed.
   *
   * Behat cannot attribute a teardown error to a step, so it is reported here
   * and is absent from the rerun cache. Errors reported at step scope are
   * deleted, so they are not reported twice.
   */
  #[AfterScenario]
  public function watchdogAfterScenario(AfterScenarioScope $scope): void {
    if (!isset($this->watchdogScenarioStartTime) || !$this->prerequisitesMet(__TRAIT__)) {
      return;
    }

    if (!$this->getOptionBool('watchdog', 'fail_on_errors')) {
      $this->watchdogReadErrors();

      return;
    }

    $context = sprintf('during scenario "%s" (line %s)', $this->watchdogScenarioTitle, $this->watchdogScenarioLine);
    if ($scope->getTestResult()->isPassed()) {
      $context = sprintf('during the teardown of scenario "%s" (line %s), which "behat --rerun" cannot record', $this->watchdogScenarioTitle, $this->watchdogScenarioLine);
    }

    $this->watchdogAssertNotHasErrors($context);
  }

  /**
   * Assert no errors at or above the severity threshold were logged.
   *
   * @param string $context
   *   Description of when the errors were logged, for the failure message.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If errors at or above the severity threshold were logged.
   */
  public function watchdogAssertNotHasErrors(string $context): void {
    $errors = $this->watchdogReadErrors();

    if ($errors === []) {
      return;
    }

    throw new ExpectationException(sprintf('PHP errors were logged to watchdog %s: %s', $context, PHP_EOL . implode(PHP_EOL . PHP_EOL, $errors)), $this->getSession()->getDriver());
  }

  /**
   * Read the errors logged since the scenario started, and clear them.
   *
   * Read entries are deleted so a later check in the same scenario sees only
   * new ones.
   *
   * @return array<int, string>
   *   Rendered entries at or above the severity threshold, keyed by their
   *   watchdog id.
   */
  public function watchdogReadErrors(): array {
    $this->driverFor(CoreCapabilityInterface::class);

    $database = Database::getConnection();

    $entries = $database->select('watchdog', 'w')
      ->fields('w')
      ->condition('w.type', $this->watchdogMessageTypes, 'IN')
      ->condition('w.timestamp', (string) $this->watchdogScenarioStartTime, '>=')
      ->execute()
      ->fetchAll();

    if (empty($entries)) {
      return [];
    }

    $errors = [];
    if (!defined('WATCHDOG_WARNING')) {
      define('WATCHDOG_WARNING', 4);
    }

    // Remove entries less severe than a warning.
    foreach ($entries as $k => $error) {
      if ($error->severity > WATCHDOG_WARNING) {
        unset($entries[$k]);
        continue;
      }
      $error->variables = unserialize($error->variables);
      $errors[$error->wid] = print_r($error, TRUE);
    }

    if ($errors !== []) {
      $database->delete('watchdog')
        ->condition('wid', array_keys($errors), 'IN')
        ->execute();
    }

    return $errors;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function watchdogConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Read the errors a scenario logged to Watchdog. Nothing is read when this is off.'),
      new Option('fail_on_errors', default: TRUE, description: 'Fail a scenario that logged an error. The errors are still read and cleared when this is off.', tags: [self::WATCHDOG_ERROR_TAG => FALSE]),
    ];
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function watchdogPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $driver): bool => $driver->moduleIsEnabled('dblog'), 'the core "dblog" module is enabled'),
    ];
  }

}
