<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Exception\AssertionException;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Run local shell commands and assert on their result.
 *
 * - Run a shell command and capture its output, error output, and exit code.
 * - Assert that the command succeeded or failed.
 * - Assert the exit code, standard output, and error output.
 * - Assert how long the command took to complete.
 *
 * Commands run through the system shell with the privileges of the process
 * that runs the tests. The command string is passed to the shell verbatim and
 * is subject to shell expansion, so untrusted input must never be
 * interpolated into it.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait CommandTrait {

  use StringTrait;

  /**
   * Standard output (stdout) captured from the last command.
   */
  protected string $commandStdout = '';

  /**
   * Error output (stderr) captured from the last command.
   */
  protected string $commandStderr = '';

  /**
   * Exit code of the last command, or NULL when no command has run yet.
   */
  protected ?int $commandExitCode = NULL;

  /**
   * Wall-clock duration of the last command, in seconds.
   */
  protected float $commandDuration = 0.0;

  /**
   * Clear captured command state before each scenario.
   */
  #[BeforeScenario]
  public function commandBeforeScenario(BeforeScenarioScope $scope): void {
    $this->commandResetState();
  }

  /**
   * Clear captured command state after each scenario.
   */
  #[AfterScenario]
  public function commandAfterScenario(AfterScenarioScope $scope): void {
    $this->commandResetState();
  }

  /**
   * Run a shell command.
   *
   * The command runs through the system shell; its standard output, error
   * output, and exit code are captured for subsequent assertions.
   *
   * @code
   * When I run the command "php -v"
   * When I run the command "./vendor/bin/phpunit --version"
   * @endcode
   */
  #[When('I run the command :command')]
  public function commandRun(string $command): void {
    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $this->commandResetState();

    $started = microtime(TRUE);
    $process = proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('Unable to start the command "%s".', $command));
      // @codeCoverageIgnoreEnd
    }

    fclose($pipes[0]);

    // Drain both pipes concurrently. A sequential read deadlocks once a
    // command fills the unread pipe's buffer (~64KB) and blocks before it
    // finishes writing the pipe being read.
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);

    $stdout = '';
    $stderr = '';
    $timeout = $this->commandGetTimeout();

    while (!feof($pipes[1]) || !feof($pipes[2])) {
      if (microtime(TRUE) - $started > $timeout) {
        proc_terminate($process, 9);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        throw new \RuntimeException(sprintf('The command "%s" timed out after %d seconds.', $command, $timeout));
      }

      $read = [];
      if (!feof($pipes[1])) {
        $read[] = $pipes[1];
      }
      if (!feof($pipes[2])) {
        $read[] = $pipes[2];
      }

      $write = NULL;
      $except = NULL;
      if (stream_select($read, $write, $except, 1) === FALSE) {
        // @codeCoverageIgnoreStart
        break;
        // @codeCoverageIgnoreEnd
      }

      foreach ($read as $stream) {
        $chunk = fread($stream, 8192);
        if ($chunk === FALSE) {
          // @codeCoverageIgnoreStart
          continue;
          // @codeCoverageIgnoreEnd
        }

        if ($stream === $pipes[1]) {
          $stdout .= $chunk;
        }
        else {
          $stderr .= $chunk;
        }
      }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    $this->commandExitCode = proc_close($process);
    $this->commandDuration = microtime(TRUE) - $started;
    $this->commandStdout = $stdout;
    $this->commandStderr = $stderr;
  }

  /**
   * Assert that the last command succeeded.
   *
   * @code
   * When I run the command "php -v"
   * Then the command should succeed
   * @endcode
   */
  #[Then('the command should succeed')]
  public function commandAssertSuccess(): void {
    $this->commandRequireRun();

    $exit_code = (int) $this->commandExitCode;

    if ($exit_code !== 0) {
      throw new AssertionException(sprintf('Expected the command to succeed, but it exited with code %d. Error output: %s.', $exit_code, $this->commandStderr));
    }
  }

  /**
   * Assert that the last command failed.
   *
   * @code
   * When I run the command "phpcs --standard=NonExisting"
   * Then the command should fail
   * @endcode
   */
  #[Then('the command should fail')]
  public function commandAssertFailure(): void {
    $this->commandRequireRun();

    if ((int) $this->commandExitCode === 0) {
      throw new AssertionException('Expected the command to fail, but it exited with code 0.');
    }
  }

  /**
   * Assert that the last command exited with a specific code.
   *
   * @code
   * When I run the command "php -r 'exit(3);'"
   * Then the command exit code should be 3
   * @endcode
   */
  #[Then('the command exit code should be :code')]
  public function commandAssertExitCode(string $code): void {
    $this->commandRequireRun();

    $expected = $this->stringParseInteger($code, 'exit code');
    $exit_code = (int) $this->commandExitCode;

    if ($exit_code !== $expected) {
      throw new AssertionException(sprintf('Expected the command to exit with code %d, but it exited with code %d.', $expected, $exit_code));
    }
  }

  /**
   * Assert that the command output contains a value.
   *
   * The output is the command's standard output (stdout).
   *
   * @code
   * When I run the command "echo hello"
   * Then the command output should contain the value "hello"
   * @endcode
   */
  #[Then('the command output should contain the value :value')]
  public function commandAssertOutputContains(string $value): void {
    $this->commandRequireRun();

    if (!str_contains($this->commandStdout, $value)) {
      throw new AssertionException(sprintf('Expected the command output to contain "%s", but it did not. Actual output: %s.', $value, $this->commandStdout));
    }
  }

  /**
   * Assert that the command output does not contain a value.
   *
   * The output is the command's standard output (stdout).
   *
   * @code
   * When I run the command "echo hello"
   * Then the command output should not contain the value "goodbye"
   * @endcode
   */
  #[Then('the command output should not contain the value :value')]
  public function commandAssertOutputNotContains(string $value): void {
    $this->commandRequireRun();

    if (str_contains($this->commandStdout, $value)) {
      throw new AssertionException(sprintf('Expected the command output to not contain "%s", but it did. Actual output: %s.', $value, $this->commandStdout));
    }
  }

  /**
   * Assert that the command output equals a value.
   *
   * The output is the command's standard output (stdout). Leading and trailing
   * whitespace is ignored on both sides so a trailing newline emitted by the
   * command does not cause a mismatch.
   *
   * @code
   * When I run the command "echo hello"
   * Then the command output should be equal to the value "hello"
   * @endcode
   */
  #[Then('the command output should be equal to the value :value')]
  public function commandAssertOutputEquals(string $value): void {
    $this->commandRequireRun();

    if (trim($this->commandStdout) !== trim($value)) {
      throw new AssertionException(sprintf('Expected the command output to be "%s", but got "%s".', trim($value), trim($this->commandStdout)));
    }
  }

  /**
   * Assert that the command error output contains a value.
   *
   * The error output is the command's standard error (stderr).
   *
   * @code
   * When I run the command "ls /nonexistent"
   * Then the command error output should contain the value "No such file"
   * @endcode
   */
  #[Then('the command error output should contain the value :value')]
  public function commandAssertErrorOutputContains(string $value): void {
    $this->commandRequireRun();

    if (!str_contains($this->commandStderr, $value)) {
      throw new AssertionException(sprintf('Expected the command error output to contain "%s", but it did not. Actual error output: %s.', $value, $this->commandStderr));
    }
  }

  /**
   * Assert that the command completed in less than a number of seconds.
   *
   * @code
   * When I run the command "echo hello"
   * Then the command should complete in less than 5 seconds
   * @endcode
   */
  #[Then('the command should complete in less than :seconds second(s)')]
  public function commandAssertDurationLessThan(string $seconds): void {
    $this->commandRequireRun();

    $limit = $this->stringParseNumber($seconds, 'duration', 0);

    if ($this->commandDuration >= $limit) {
      throw new AssertionException(sprintf('Expected the command to complete in less than %s seconds, but it took %.3f seconds.', $seconds, $this->commandDuration));
    }
  }

  /**
   * Assert that the command completed in more than a number of seconds.
   *
   * @code
   * When I run the command "sleep 2"
   * Then the command should complete in more than 1 second
   * @endcode
   */
  #[Then('the command should complete in more than :seconds second(s)')]
  public function commandAssertDurationMoreThan(string $seconds): void {
    $this->commandRequireRun();

    $limit = $this->stringParseNumber($seconds, 'duration', 0);

    if ($this->commandDuration <= $limit) {
      throw new AssertionException(sprintf('Expected the command to complete in more than %s seconds, but it took %.3f seconds.', $seconds, $this->commandDuration));
    }
  }

  /**
   * Reset the captured command state.
   */
  protected function commandResetState(): void {
    $this->commandStdout = '';
    $this->commandStderr = '';
    $this->commandExitCode = NULL;
    $this->commandDuration = 0.0;
  }

  /**
   * Require a command to have run in the current scenario.
   *
   * @throws \RuntimeException
   *   When no command has been run yet.
   */
  protected function commandRequireRun(): void {
    if ($this->commandExitCode === NULL) {
      throw new \RuntimeException('No command has been run. Run a command before asserting on its result.');
    }
  }

  /**
   * The maximum time, in seconds, a command may run before it is terminated.
   */
  public function commandGetTimeout(): int {
    return $this->getOptionInt('command', 'timeout');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function commandConfigSchema(): array {
    return [
      new Option('timeout', default: 300, description: 'Maximum time, in seconds, a command may run before it is terminated.'),
    ];
  }

}
