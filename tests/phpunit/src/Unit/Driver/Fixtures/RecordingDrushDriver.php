<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Fixtures;

use DrevOps\BehatSteps\Driver\Drush\DrushResult;
use DrevOps\BehatSteps\Driver\DrushDriver;

/**
 * Subclass of 'DrushDriver' that records every Drush invocation.
 *
 * Both entry points are stubbed, because a driver method reaches for
 * 'drushResult()' when a non-zero exit is an answer rather than a failure.
 */
class RecordingDrushDriver extends DrushDriver {

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
   * {@inheritdoc}
   */
  public function drush(string $command, array $arguments = [], array $options = []): string {
    $this->record($command, $arguments, $options);

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
