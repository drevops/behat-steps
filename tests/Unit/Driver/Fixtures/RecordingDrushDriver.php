<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Fixtures;

use DrevOps\BehatSteps\Driver\DrushDriver;

/**
 * Subclass of 'DrushDriver' that records every 'drush()' invocation.
 */
class RecordingDrushDriver extends DrushDriver {

  /**
   * Log of 'drush()' invocations.
   *
   * @var array<int, array{command: string, arguments: array<int, string>, options: array<string, mixed>}>
   */
  public array $invocations = [];

  /**
   * The canned response to return from stubbed 'drush()' calls.
   */
  public string $drushResponse = '';

  /**
   * {@inheritdoc}
   */
  public function drush(string $command, array $arguments = [], array $options = []): string {
    $this->invocations[] = [
      'command' => $command,
      'arguments' => $arguments,
      'options' => $options,
    ];

    return $this->drushResponse;
  }

}
