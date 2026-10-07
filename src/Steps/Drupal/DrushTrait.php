<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\DrushCapabilityInterface;
use DrevOps\BehatSteps\Exception\AssertionException;

/**
 * Run Drush commands and assert their output.
 *
 * - Run a command with or without arguments, through the Drush backend.
 * - Run a command that is expected to fail and keep its output.
 * - Assert the last command's output by substring or regular expression.
 *
 * Steps resolve the backend that can run Drush commands, not the first one in
 * the scenario's order. They work in a scenario driven by any other backend
 * as long as the suite lists a Drush-capable one.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait DrushTrait {

  /**
   * Output of the most recent Drush command, NULL until one has run.
   */
  protected ?string $drushOutput = NULL;

  /**
   * Run a Drush command.
   *
   * @code
   * When I run the drush command "status"
   * @endcode
   */
  #[When('I run the drush command :command')]
  public function drushRun(string $command): void {
    $this->drushOutput = $this->drushGetBackend()->drush($command);
  }

  /**
   * Run a Drush command with arguments.
   *
   * The arguments string is appended verbatim, so it may carry options as
   * well as positional arguments.
   *
   * @code
   * When I run the drush command "pm:list" with the arguments "--status=enabled"
   * When I run the drush command "config:get" with the arguments "system.site uuid"
   * @endcode
   */
  #[When('I run the drush command :command with the arguments :arguments')]
  public function drushRunWithArguments(string $command, string $arguments): void {
    $this->drushOutput = $this->drushGetBackend()->drush($command, [$this->drushFixArgument($arguments)]);
  }

  /**
   * Run a Drush command that is expected to fail.
   *
   * A non-zero exit does not abort the step; the command output is kept in
   * '$drushOutput' for later assertions, falling back to stderr when stdout is
   * empty.
   *
   * @code
   * When I run the failing drush command "pm:uninstall no_such_module"
   * @endcode
   */
  #[When('I run the failing drush command :command')]
  public function drushRunFailing(string $command): void {
    $this->drushRunExpectingFailure($command);
  }

  /**
   * Run a Drush command with arguments that is expected to fail.
   *
   * @code
   * When I run the failing drush command "pm:uninstall" with the arguments "no_such_module"
   * @endcode
   */
  #[When('I run the failing drush command :command with the arguments :arguments')]
  public function drushRunFailingWithArguments(string $command, string $arguments): void {
    $this->drushRunExpectingFailure($command, $arguments);
  }

  /**
   * Print the output of the most recent Drush command.
   *
   * @code
   * When I print the last drush output
   * @endcode
   */
  #[When('I print the last drush output')]
  public function drushPrintOutput(): void {
    print $this->drushReadOutput();
  }

  /**
   * Assert that the last Drush output contains the value.
   *
   * @code
   * Then the drush output should contain the value "Drupal version"
   * @endcode
   */
  #[Then('the drush output should contain the value :value')]
  public function drushAssertOutputContains(string $value): void {
    if (!str_contains($this->drushReadOutput(), $this->drushFixArgument($value))) {
      throw new AssertionException(sprintf('The last drush command output does not contain "%s". It was:' . PHP_EOL . PHP_EOL . '%s', $value, $this->drushOutput));
    }
  }

  /**
   * Assert that the last Drush output does not contain the value.
   *
   * @code
   * Then the drush output should not contain the value "error"
   * @endcode
   */
  #[Then('the drush output should not contain the value :value')]
  public function drushAssertOutputNotContains(string $value): void {
    if (str_contains($this->drushReadOutput(), $this->drushFixArgument($value))) {
      throw new AssertionException(sprintf('The last drush command output contains "%s". It was:' . PHP_EOL . PHP_EOL . '%s', $value, $this->drushOutput));
    }
  }

  /**
   * Assert that the last Drush output matches the pattern.
   *
   * @code
   * Then the drush output should match the pattern "/Drupal [0-9]+/"
   * @endcode
   */
  #[Then('the drush output should match the pattern :pattern')]
  public function drushAssertOutputMatches(string $pattern): void {
    $output = $this->drushReadOutput();
    $result = @preg_match($pattern, $output);

    // 'preg_match()' returns FALSE for a malformed pattern, so that case is
    // reported apart from an output that did not match.
    if ($result === FALSE) {
      throw new \RuntimeException(sprintf('"%s" is not a valid regular expression: %s.', $pattern, preg_last_error_msg()));
    }

    if ($result !== 1) {
      throw new AssertionException(sprintf('The last drush command output does not match "%s". It was:' . PHP_EOL . PHP_EOL . '%s', $pattern, $this->drushOutput));
    }
  }

  /**
   * Return the output of the most recent Drush command.
   *
   * @throws \RuntimeException
   *   When no Drush command has run in this scenario.
   */
  public function drushReadOutput(): string {
    if ($this->drushOutput === NULL) {
      throw new \RuntimeException('No drush command has run in this scenario, so there is no output to read.');
    }

    return $this->drushOutput;
  }

  /**
   * Return the backend that runs Drush commands.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's order can run Drush commands.
   */
  public function drushGetBackend(): DrushCapabilityInterface {
    return $this->backendFor(DrushCapabilityInterface::class);
  }

  /**
   * Run a Drush command expecting a non-zero exit, keeping its output.
   *
   * @param string $command
   *   The Drush command to run.
   * @param string|null $arguments
   *   Arguments appended to the command verbatim, or NULL for none.
   *
   * @throws \DrevOps\BehatSteps\Exception\AssertionException
   *   When the command exits zero.
   */
  public function drushRunExpectingFailure(string $command, ?string $arguments = NULL): void {
    $args = $arguments === NULL ? [] : [$this->drushFixArgument($arguments)];
    $result = $this->drushGetBackend()->drushResult($command, $args);

    // The success path returns the command's output, so the failure path keeps
    // stdout and falls back to stderr when it is empty.
    $output = $result->output === '' ? $result->errorOutput : $result->output;
    $this->drushOutput = $output;

    if ($result->exitCode === 0) {
      throw new AssertionException(sprintf('The drush command "%s" was expected to fail, but it exited 0. Output:' . PHP_EOL . PHP_EOL . '%s', $command, $output));
    }
  }

  /**
   * Restore quotes escaped by the Gherkin parser.
   */
  protected function drushFixArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

}
