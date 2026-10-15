<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Backend\Drupal\Capability\LogCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Extension\Tag;
use DrevOps\BehatSteps\Steps\Exception\AssertionException;
use DrevOps\BehatSteps\Steps\Helper\Web\LastStepTrait;

/**
 * Assert Drupal does not trigger PHP errors during scenarios using Watchdog.
 *
 * - Check for Watchdog messages after scenario completion.
 * - Optionally check for more message types than `php`.
 * - Optionally skip error checking for specific scenarios.
 *
 * The check is on by default. An opted-in scenario whose prerequisites do not
 * hold fails at its start.
 *
 * `watchdog.fail_on_errors` and `@error` determine what happens to errors
 * that were read, so they do not cover an unmet prerequisite.
 *
 * Skip processing with tag: `@behat-steps-skip:WatchdogTrait`.
 *
 * Special tags:
 * - `@watchdog:{type}` - track the named message type in addition to `php`,
 *   which is always tracked.
 * - `@error` - add to scenarios that are expected to trigger an error. The
 *   errors are still read and cleared; the scenario is not failed.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait WatchdogTrait {

  use LastStepTrait;

  /**
   * The tag that adds its value to the message types the scenario tracks.
   */
  protected const string WATCHDOG_TAG = 'watchdog';

  /**
   * The tag that keeps a scenario logging an error from failing.
   */
  protected const string WATCHDOG_ERROR_TAG = 'error';

  /**
   * The id of the newest log entry when the scenario started.
   *
   * NULL until the scenario arms the check.
   */
  protected ?int $watchdogLastId = NULL;

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
   * Store the scenario identity, tracked message types and the log position.
   */
  #[BeforeScenario]
  public function watchdogBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    $this->assertPrerequisites(__TRAIT__, $scope);

    $scenario = $scope->getScenario();

    $this->watchdogLastId = $this->backendFor(LogCapabilityInterface::class)->logGetLastId();
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
    if (!isset($this->watchdogLastId) || !$this->lastStepReached($scope)) {
      return;
    }

    $this->assertPrerequisites(__TRAIT__, $scope);

    if (!$this->watchdogGetFailOnErrors()) {
      $this->watchdogClearErrors();

      return;
    }

    $this->watchdogAssertErrorsNotExist(sprintf('during scenario "%s" (line %s)', $this->watchdogScenarioTitle, $this->watchdogScenarioLine));
  }

  /**
   * Check for errors the last-step check could not have read.
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
    if (!isset($this->watchdogLastId) || !$this->prerequisitesMet(__TRAIT__)) {
      return;
    }

    if (!$this->watchdogGetFailOnErrors()) {
      $this->watchdogClearErrors();

      return;
    }

    $context = sprintf('during scenario "%s" (line %s)', $this->watchdogScenarioTitle, $this->watchdogScenarioLine);

    if ($scope->getTestResult()->isPassed()) {
      $context = sprintf('during the teardown of scenario "%s" (line %s), which "behat --rerun" cannot record', $this->watchdogScenarioTitle, $this->watchdogScenarioLine);
    }

    $this->watchdogAssertErrorsNotExist($context);
  }

  /**
   * Assert no errors at or above the severity threshold were logged.
   *
   * @param string $context
   *   Description of when the errors were logged, for the failure message.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   If errors at or above the severity threshold were logged.
   */
  public function watchdogAssertErrorsNotExist(string $context): void {
    $errors = $this->watchdogClearErrors();

    if ($errors === []) {
      return;
    }

    throw new AssertionException(sprintf('PHP errors were logged to watchdog %s: %s', $context, PHP_EOL . implode(PHP_EOL . PHP_EOL, $errors)));
  }

  /**
   * Clear the errors logged since the scenario started, and return them.
   *
   * The returned entries are deleted, so a later call in the same scenario
   * returns only new ones.
   *
   * @return array<int, string>
   *   Rendered entries at or above the severity threshold, keyed by their
   *   watchdog id.
   */
  public function watchdogClearErrors(): array {
    $backend = $this->backendFor(LogCapabilityInterface::class);

    $errors = [];

    foreach ($backend->logRead($this->watchdogMessageTypes, $this->watchdogLastId ?? 0, LogCapabilityInterface::SEVERITY_WARNING) as $entry) {
      $errors[(int) $entry['wid']] = print_r($entry, TRUE);
    }

    $backend->logDelete(array_keys($errors));

    return $errors;
  }

  /**
   * Return TRUE if a scenario that logged an error should fail.
   */
  public function watchdogGetFailOnErrors(): bool {
    return $this->getOptionBool('watchdog', 'fail_on_errors');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
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
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function watchdogPrerequisites(): array {
    return [
      Prerequisite::capability(LogCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('dblog'), 'the core "dblog" module is enabled'),
    ];
  }

}
