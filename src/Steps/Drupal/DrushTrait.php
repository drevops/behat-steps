<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Driver\DrushDriver;

/**
 * Run Drush commands and assert their output.
 *
 * - Run a command with or without arguments, through the Drush driver.
 * - Run a command that is expected to fail and keep its error output.
 * - Assert the last command's output by substring or regular expression.
 *
 * Steps route through the `drush` driver rather than the scenario's default
 * driver, so they work in a scenario running on any other driver as long as
 * `drush:` is configured.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
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
    $this->drushOutput = $this->drushDriver()->drush($command);
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
    $this->drushOutput = $this->drushDriver()->drush($command, [$this->drushFixArgument($arguments)]);
  }

  /**
   * Run a Drush command that is expected to fail.
   *
   * The command runs without aborting the step on a non-zero exit, capturing
   * its error output for the assertion steps below.
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
      throw new ExpectationException(sprintf("The last drush command output does not contain \"%s\". It was:\n\n%s", $value, $this->drushOutput), $this->getSession()->getDriver());
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
      throw new ExpectationException(sprintf("The last drush command output contains \"%s\". It was:\n\n%s", $value, $this->drushOutput), $this->getSession()->getDriver());
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

    // A malformed pattern also returns FALSE, which would otherwise read as a
    // command whose output simply did not match.
    if ($result === FALSE) {
      throw new \RuntimeException(sprintf('"%s" is not a valid regular expression: %s.', $pattern, preg_last_error_msg()));
    }

    if ($result !== 1) {
      throw new ExpectationException(sprintf("The last drush command output does not match \"%s\". It was:\n\n%s", $pattern, $this->drushOutput), $this->getSession()->getDriver());
    }
  }

  /**
   * Return the output of the most recent Drush command.
   *
   * @throws \RuntimeException
   *   When no Drush command has run in this scenario.
   */
  protected function drushReadOutput(): string {
    if ($this->drushOutput === NULL) {
      throw new \RuntimeException('No drush command has run in this scenario, so there is no output to read.');
    }

    return $this->drushOutput;
  }

  /**
   * Return the Drush driver.
   *
   * @throws \RuntimeException
   *   When the 'drush' driver is not configured.
   */
  protected function drushDriver(): DrushDriver {
    $driver = $this->getDriver('drush');

    if (!$driver instanceof DrushDriver) {
      throw new \RuntimeException(sprintf('The "drush" driver resolved to "%s", which cannot run drush commands. Configure "drush:" under "behat_steps:".', $driver::class));
    }

    return $driver;
  }

  /**
   * Run a Drush command expecting a non-zero exit, keeping its output.
   *
   * @param string $command
   *   The Drush command to run.
   * @param string|null $arguments
   *   Arguments appended to the command verbatim, or NULL for none.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the command exits zero.
   */
  protected function drushRunExpectingFailure(string $command, ?string $arguments = NULL): void {
    $args = $arguments === NULL ? [] : [$this->drushFixArgument($arguments)];
    $result = $this->drushDriver()->drushResult($command, $args);

    // Prefer stdout and fall back to stderr, matching the success path, which
    // returns whatever the command wrote.
    $output = $result->output === '' ? $result->errorOutput : $result->output;
    $this->drushOutput = $output;

    if ($result->exitCode === 0) {
      throw new ExpectationException(sprintf("The drush command \"%s\" was expected to fail, but it exited 0. Output:\n\n%s", $command, $output), $this->getSession()->getDriver());
    }
  }

  /**
   * Restore quotes escaped by the Gherkin parser.
   */
  protected function drushFixArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

}
