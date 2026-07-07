<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Then;
use Behat\Step\When;

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
 * is subject to shell expansion, so never interpolate untrusted input into it.
 */
trait CommandTrait {

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
  public function commandBeforeScenario(): void {
    $this->commandResetState();
  }

  /**
   * Clear captured command state after each scenario.
   */
  #[AfterScenario]
  public function commandAfterScenario(): void {
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

    // Drain both pipes concurrently. Reading one to completion before the other
    // would deadlock when a command fills the buffer of the unread pipe (~64KB)
    // and blocks before it can finish writing the pipe being read.
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);

    $stdout = '';
    $stderr = '';

    while (!feof($pipes[1]) || !feof($pipes[2])) {
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
    $this->commandAssertHasRun();

    $exit_code = (int) $this->commandExitCode;

    if ($exit_code !== 0) {
      throw new \Exception(sprintf('Expected the command to succeed, but it exited with code %d. Error output: %s', $exit_code, $this->commandStderr));
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
    $this->commandAssertHasRun();

    if ((int) $this->commandExitCode === 0) {
      throw new \Exception('Expected the command to fail, but it exited with code 0.');
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
    $this->commandAssertHasRun();

    $expected = (int) $code;
    $exit_code = (int) $this->commandExitCode;

    if ($exit_code !== $expected) {
      throw new \Exception(sprintf('Expected the command to exit with code %d, but it exited with code %d.', $expected, $exit_code));
    }
  }

  /**
   * Assert that the command output contains a string.
   *
   * The output is the command's standard output (stdout).
   *
   * @code
   * When I run the command "echo hello"
   * Then the command output should contain "hello"
   * @endcode
   */
  #[Then('the command output should contain :text')]
  public function commandAssertOutputContains(string $text): void {
    $this->commandAssertHasRun();

    if (!str_contains($this->commandStdout, $text)) {
      throw new \Exception(sprintf('Expected the command output to contain "%s", but it did not. Actual output: %s', $text, $this->commandStdout));
    }
  }

  /**
   * Assert that the command output does not contain a string.
   *
   * The output is the command's standard output (stdout).
   *
   * @code
   * When I run the command "echo hello"
   * Then the command output should not contain "goodbye"
   * @endcode
   */
  #[Then('the command output should not contain :text')]
  public function commandAssertOutputNotContains(string $text): void {
    $this->commandAssertHasRun();

    if (str_contains($this->commandStdout, $text)) {
      throw new \Exception(sprintf('Expected the command output to not contain "%s", but it did. Actual output: %s', $text, $this->commandStdout));
    }
  }

  /**
   * Assert that the command output equals a string.
   *
   * The output is the command's standard output (stdout). Leading and trailing
   * whitespace is ignored on both sides so a trailing newline emitted by the
   * command does not cause a mismatch.
   *
   * @code
   * When I run the command "echo hello"
   * Then the command output should be "hello"
   * @endcode
   */
  #[Then('the command output should be :text')]
  public function commandAssertOutputEquals(string $text): void {
    $this->commandAssertHasRun();

    if (trim($this->commandStdout) !== trim($text)) {
      throw new \Exception(sprintf('Expected the command output to be "%s", but got "%s".', trim($text), trim($this->commandStdout)));
    }
  }

  /**
   * Assert that the command error output contains a string.
   *
   * The error output is the command's standard error (stderr).
   *
   * @code
   * When I run the command "ls /nonexistent"
   * Then the command error output should contain "No such file"
   * @endcode
   */
  #[Then('the command error output should contain :text')]
  public function commandAssertErrorOutputContains(string $text): void {
    $this->commandAssertHasRun();

    if (!str_contains($this->commandStderr, $text)) {
      throw new \Exception(sprintf('Expected the command error output to contain "%s", but it did not. Actual error output: %s', $text, $this->commandStderr));
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
    $this->commandAssertHasRun();

    $limit = (float) $seconds;

    if ($this->commandDuration >= $limit) {
      throw new \Exception(sprintf('Expected the command to complete in less than %s seconds, but it took %.3f seconds.', $seconds, $this->commandDuration));
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
    $this->commandAssertHasRun();

    $limit = (float) $seconds;

    if ($this->commandDuration <= $limit) {
      throw new \Exception(sprintf('Expected the command to complete in more than %s seconds, but it took %.3f seconds.', $seconds, $this->commandDuration));
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
   * Assert that a command has been run in the current scenario.
   *
   * @throws \RuntimeException
   *   When no command has been run yet.
   */
  protected function commandAssertHasRun(): void {
    if ($this->commandExitCode === NULL) {
      throw new \RuntimeException('No command has been run. Run a command before asserting on its result.');
    }
  }

}
