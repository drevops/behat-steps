<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Drupal\Capability\DrushCapabilityInterface;
use DrevOps\BehatSteps\Steps\Exception\AssertionException;

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
 * @title Drush
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait DrushTrait {

  /**
   * Output of the most recent Drush command, NULL until one has run.
   */
  protected ?string $drushOutput = NULL;

  /**
   * Run a Drush command.
   *
   * The command may carry its arguments, split into words as a shell splits
   * them: whitespace separates 2 words, and a quoted part stays in 1 word.
   *
   * @code
   * When I run the drush command "status"
   * When I run the drush command "config:get system.site name"
   * @endcode
   *
   * @actor:person
   */
  #[When('I run the drush command :command')]
  public function drushRun(string $command): void {
    [$name, $words] = $this->drushParseCommand($command);
    $this->drushOutput = $this->drushGetBackend()->drush($name, $words);
  }

  /**
   * Run a Drush command with arguments.
   *
   * The arguments are split into words as a shell splits them, so they may
   * carry options as well as positional arguments, and a quoted part stays in
   * 1 word.
   *
   * @code
   * When I run the drush command "pm:list" with the arguments "--status=enabled"
   * When I run the drush command "config:get" with the arguments "system.site uuid"
   * @endcode
   *
   * @actor:person
   */
  #[When('I run the drush command :command with the arguments :arguments')]
  public function drushRunWithArguments(string $command, string $arguments): void {
    [$name, $words] = $this->drushParseCommand($command, $arguments);
    $this->drushOutput = $this->drushGetBackend()->drush($name, $words);
  }

  /**
   * Run a Drush command that is expected to fail.
   *
   * A non-zero exit does not abort the step. The command output is kept in
   * '$drushOutput' for later assertions, falling back to stderr when stdout is
   * empty.
   *
   * @code
   * When I run the failing drush command "pm:uninstall no_such_module"
   * @endcode
   *
   * @actor:person
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
   *
   * @actor:person
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
   *
   * @actor:person
   */
  #[When('I print the last drush output')]
  public function drushPrintOutput(): void {
    print $this->drushGetOutput();
  }

  /**
   * Assert that the last Drush output contains the text.
   *
   * @code
   * Then the drush output should contain "Drupal version"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the drush output should contain :value')]
  public function drushAssertOutputContains(string $value): void {
    if (!str_contains($this->drushGetOutput(), $this->drushFixArgument($value))) {
      throw new AssertionException(sprintf('The last drush command output does not contain "%s". It was:' . PHP_EOL . PHP_EOL . '%s', $value, $this->drushOutput));
    }
  }

  /**
   * Assert that the last Drush output does not contain the text.
   *
   * @code
   * Then the drush output should not contain "error"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the drush output should not contain :value')]
  public function drushAssertOutputNotContains(string $value): void {
    if (str_contains($this->drushGetOutput(), $this->drushFixArgument($value))) {
      throw new AssertionException(sprintf('The last drush command output contains "%s". It was:' . PHP_EOL . PHP_EOL . '%s', $value, $this->drushOutput));
    }
  }

  /**
   * Assert that the last Drush output matches the pattern.
   *
   * @code
   * Then the drush output should match "/Drupal [0-9]+/"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the drush output should match :pattern')]
  public function drushAssertOutputMatches(string $pattern): void {
    $output = $this->drushGetOutput();
    $result = @preg_match($pattern, $output);

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
  public function drushGetOutput(): string {
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
   *   The Drush command to run, which may carry arguments of its own.
   * @param string|null $arguments
   *   Arguments appended to the command, or NULL for none. Both are split into
   *   words as a shell splits them.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the command exits zero.
   */
  public function drushRunExpectingFailure(string $command, ?string $arguments = NULL): void {
    [$name, $words] = $this->drushParseCommand($command, $arguments);
    $result = $this->drushGetBackend()->drushResult($name, $words);

    // The success path returns the command's output, so the failure path keeps
    // stdout and falls back to stderr when it is empty.
    $output = $result->output === '' ? $result->errorOutput : $result->output;
    $this->drushOutput = $output;

    if ($result->exitCode === 0) {
      throw new AssertionException(sprintf('The drush command "%s" was expected to fail, but it exited 0. Output:' . PHP_EOL . PHP_EOL . '%s', $command, $output));
    }
  }

  /**
   * Parse a command and its arguments into the words Drush receives.
   *
   * @param string $command
   *   The command, which may carry arguments of its own.
   * @param string|null $arguments
   *   Arguments appended to the command, or NULL for none.
   *
   * @return array{0: string, 1: array<int, string>}
   *   The Drush command and its arguments.
   *
   * @throws \RuntimeException
   *   When the command is empty or a quote is not closed.
   */
  protected function drushParseCommand(string $command, ?string $arguments = NULL): array {
    $words = $this->drushParseWords($command);

    if ($words === []) {
      throw new \RuntimeException('The drush command is empty.');
    }

    $name = array_shift($words);

    return [$name, [...$words, ...$this->drushParseWords($arguments ?? '')]];
  }

  /**
   * Split a command line into words as a POSIX shell splits them.
   *
   * Whitespace separates 2 words, and a single- or double-quoted part stays
   * in 1 word without its quotes. A backslash outside quotes keeps the next
   * character as it is, and inside double quotes it does so for a double
   * quote or a backslash. Nothing is expanded.
   *
   * @param string $command_line
   *   The command line.
   *
   * @return array<int, string>
   *   The words, in order.
   *
   * @throws \RuntimeException
   *   When a quote is not closed.
   */
  protected function drushParseWords(string $command_line): array {
    $length = strlen($command_line);
    $words = [];
    $word = NULL;
    $quote = NULL;

    for ($i = 0; $i < $length; $i++) {
      $char = $command_line[$i];
      $next = $command_line[$i + 1] ?? '';
      $is_escape = $char === '\\' && $next !== '' && ($quote === NULL || ($quote === '"' && ($next === '"' || $next === '\\')));

      if ($is_escape) {
        $word = ($word ?? '') . $next;
        $i++;

        continue;
      }

      if ($quote !== NULL) {
        if ($char === $quote) {
          $quote = NULL;
        }
        else {
          $word = ($word ?? '') . $char;
        }

        continue;
      }

      if ($char === '"' || $char === "'") {
        // An empty quoted part still makes a word, as '""' does in a shell.
        $word ??= '';
        $quote = $char;

        continue;
      }

      if (ctype_space($char)) {
        if ($word !== NULL) {
          $words[] = $word;
        }

        $word = NULL;

        continue;
      }

      $word = ($word ?? '') . $char;
    }

    if ($quote !== NULL) {
      throw new \RuntimeException(sprintf('The quote in "%s" is not closed.', $command_line));
    }

    if ($word !== NULL) {
      $words[] = $word;
    }

    return $words;
  }

  /**
   * Restore quotes escaped by the Gherkin parser.
   */
  protected function drushFixArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

}
