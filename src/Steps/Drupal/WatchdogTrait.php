<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use DrevOps\BehatSteps\Attribute\Steps;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\WatchdogCapabilityInterface;
use DrevOps\BehatSteps\Helper\LastStepTrait;
use Drupal\Core\Database\Database;

/**
 * Assert Drupal does not trigger PHP errors during scenarios using Watchdog.
 *
 * - Check for Watchdog messages after scenario completion.
 * - Optionally check only for specific message types.
 * - Optionally skip error checking for specific scenarios.
 *
 * Skip processing with tags: `@behat-steps-skip:watchdogSetScenario` or
 * `@behat-steps-skip:watchdogAfterStep`
 *
 * Special tags:
 * - `@watchdog:{type}` - limit watchdog messages to specific types.
 * - `@error` - add to scenarios that are expected to trigger an error. The
 *   errors are still read and cleared; the scenario is not failed.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 * @phpstan-require-implements \DrevOps\BehatSteps\Behat\Context\DrupalApiInterface
 */
#[Steps]
trait WatchdogTrait {

  use LastStepTrait;

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
   * Whether a logged error fails the current scenario.
   */
  protected bool $watchdogFailOnErrors = TRUE;

  /**
   * Store the scenario identity, tracked message types and start time.
   */
  #[BeforeScenario]
  public function watchdogSetScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__FUNCTION__, $scope) || !$this->getDriverManager()->hasCapability(WatchdogCapabilityInterface::class)) {
      return;
    }

    $scenario = $scope->getScenario();

    // An unset start time disables the check.
    if (Tag::has($scenario, 'behat-steps-skip:watchdogAfterStep')) {
      return;
    }

    $this->watchdogFailOnErrors = $this->getOption('watchdog', 'fail_on_errors', $scope) !== FALSE;
    $this->watchdogScenarioStartTime = time();
    $this->watchdogScenarioTitle = $scenario->getTitle() ?? '';
    $this->watchdogScenarioLine = $scenario->getLine();

    $this->watchdogMessageTypes = $this->watchdogParseMessageTypes(Tag::on($scenario));

    $this->setLastStepLine($scope);
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
    if (!isset($this->watchdogScenarioStartTime) || !$this->isLastStep($scope)) {
      return;
    }

    $this->driverFor(CoreCapabilityInterface::class);

    if (!Database::getConnection()->schema()->tableExists('watchdog')) {
      throw new \RuntimeException('Watchdog table does not exist. Ensure the dblog module is enabled.');
    }

    if (!$this->watchdogFailOnErrors) {
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
    if (!isset($this->watchdogScenarioStartTime) || !$this->getDriverManager()->hasCapability(WatchdogCapabilityInterface::class)) {
      return;
    }

    $this->driverFor(CoreCapabilityInterface::class);

    // The step hook throws for a missing table because the step result is
    // still open. This hook runs after the result is set, where throwing
    // would replace a real scenario failure with a configuration error.
    if (!Database::getConnection()->schema()->tableExists('watchdog')) {
      return;
    }

    if (!$this->watchdogFailOnErrors) {
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
   * Parse scenario tags into message types.
   *
   * @code
   * @watchdog:my_module_type @watchdog:my_other_module_type
   * @endcode
   *
   * @param array<int, string> $tags
   *   Array of scenario tags.
   * @param string $prefix
   *   Optional tag prefix to filter by.
   *
   * @return array<int, string>
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

    // Remove entries below severity threshold.
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
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function watchdogConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Read the errors a scenario logged to Watchdog. Nothing is read when this is off.',
      ],
      'fail_on_errors' => [
        'default' => TRUE,
        'description' => 'Fail a scenario that logged an error. The errors are still read and cleared when this is off.',
        'tags' => ['error' => FALSE],
      ],
    ];
  }

}
