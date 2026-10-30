<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Drupal\Capability\DrushCapabilityInterface;
use DrevOps\BehatSteps\Steps\Exception\AssertionException;
use DrevOps\BehatSteps\Steps\Helper\Drupal\StaticCacheTrait;

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

  use StaticCacheTrait;

  /**
   * Output of the most recent Drush command, NULL until one has run.
   */
  protected ?string $drushOutput = NULL;

  /**
   * Run a Drush command.
   *
   * The command may carry its arguments, split into words as a shell splits
   * them: whitespace separates 2 words, and a quoted part stays in 1 word.
   * No shell runs the command, so a redirection such as '2>&1' reaches Drush
   * as an argument.
   *
   * The output holds stdout followed by stderr, so the output steps see what
   * the command logs as well as what it prints.
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
    $this->drushRunExpectingSuccess($command);
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
    $this->drushRunExpectingSuccess($command, $arguments);
  }

  /**
   * Run a Drush command that is expected to fail.
   *
   * A non-zero exit does not abort the step. The command output, stdout
   * followed by stderr, is kept in '$drushOutput' for later assertions.
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
   * Run a Drush command expecting a zero exit, keeping its output.
   *
   * The output kept is stdout followed by stderr.
   *
   * @param string $command
   *   The Drush command to run, which may carry arguments of its own.
   * @param string|null $arguments
   *   Arguments appended to the command, or NULL for none. Both are split into
   *   words as a shell splits them.
   *
   * @throws \RuntimeException
   *   When the command exits non-zero.
   */
  public function drushRunExpectingSuccess(string $command, ?string $arguments = NULL): void {
    $exit_code = $this->drushExecute($command, $arguments);

    if ($exit_code !== 0) {
      throw new \RuntimeException(sprintf('The drush command "%s" exited with code %d. Output:' . PHP_EOL . PHP_EOL . '%s', $command, $exit_code, $this->drushOutput));
    }
  }

  /**
   * Run a Drush command expecting a non-zero exit, keeping its output.
   *
   * The output kept is stdout followed by stderr.
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
    if ($this->drushExecute($command, $arguments) === 0) {
      throw new AssertionException(sprintf('The drush command "%s" was expected to fail, but it exited 0. Output:' . PHP_EOL . PHP_EOL . '%s', $command, $this->drushOutput));
    }
  }

  /**
   * Run a Drush command, keeping its output, and return its exit code.
   *
   * The command runs in a process of its own, so the static caches of the
   * backends this scenario resolved are cleared after it returns.
   *
   * @param string $command
   *   The Drush command to run, which may carry arguments of its own.
   * @param string|null $arguments
   *   Arguments appended to the command, or NULL for none.
   *
   * @return int
   *   The exit code of the command.
   */
  protected function drushExecute(string $command, ?string $arguments): int {
    [$name, $words] = $this->drushParseCommand($command, $arguments);
    $result = $this->drushGetBackend()->drushResult($name, $words);

    $this->staticCacheClear();

    $this->drushOutput = $this->drushCombineOutput($result->output, $result->errorOutput);

    return $result->exitCode;
  }

  /**
   * Join the stdout and the stderr of a Drush command, stdout first.
   *
   * @param string $stdout
   *   The stdout of the command.
   * @param string $stderr
   *   The stderr of the command.
   *
   * @return string
   *   Both streams, with a line break added between them when stdout holds
   *   text that does not end with one.
   */
  protected function drushCombineOutput(string $stdout, string $stderr): string {
    if ($stdout === '' || $stderr === '') {
      return $stdout . $stderr;
    }

    return $stdout . (str_ends_with($stdout, "\n") ? '' : PHP_EOL) . $stderr;
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
