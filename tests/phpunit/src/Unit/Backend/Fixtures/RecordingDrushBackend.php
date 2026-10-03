<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\Drush\DrushResult;
use DrevOps\BehatSteps\Backend\DrushBackend;

/**
 * Subclass of 'DrushBackend' that records every Drush invocation.
 *
 * Both entry points are stubbed, because a backend method calls
 * 'drushResult()' when a non-zero exit is an answer rather than a failure.
 */
class RecordingDrushBackend extends DrushBackend {

  /**
   * Log of Drush invocations.
   *
   * @var array<int, array{command: string, arguments: array<int, string>, options: array<string, mixed>}>
   */
  public array $invocations = [];

  /**
   * The canned response to return from stubbed Drush calls.
   */
  public string $drushResponse = '';

  /**
   * The canned exit code to return from stubbed 'drushResult()' calls.
   */
  public int $drushExitCode = 0;

  /**
   * Remaining failure counts keyed by the command name 'drush()' throws on.
   *
   * A count lets a test fail the first call of a command and let a later
   * call succeed.
   *
   * @var array<string, int>
   */
  public array $drushFailures = [];

  /**
   * {@inheritdoc}
   */
  public function drush(string $command, array $arguments = [], array $options = []): string {
    $this->record($command, $arguments, $options);

    if (($this->drushFailures[$command] ?? 0) > 0) {
      $this->drushFailures[$command]--;

      throw new \RuntimeException(sprintf("Drush command '%s' exited with code 1.", $command));
    }

    return $this->drushResponse;
  }

  /**
   * {@inheritdoc}
   */
  public function drushResult(string $command, array $arguments = [], array $options = []): DrushResult {
    $this->record($command, $arguments, $options);

    return new DrushResult($this->drushExitCode, $this->drushResponse, '');
  }

  /**
   * Appends an invocation to the log.
   *
   * @param string $command
   *   The Drush command.
   * @param array<int, string> $arguments
   *   Positional arguments.
   * @param array<string, mixed> $options
   *   Options keyed by name.
   */
  protected function record(string $command, array $arguments, array $options): void {
    $this->invocations[] = [
      'command' => $command,
      'arguments' => $arguments,
      'options' => $options,
    ];
  }

}
