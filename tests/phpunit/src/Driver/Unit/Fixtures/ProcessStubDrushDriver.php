<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Driver\Unit\Fixtures;

use DrevOps\BehatSteps\Driver\DrushDriver;
use Symfony\Component\Process\Process;

/**
 * Subclass of 'DrushDriver' that returns a stubbed process from 'runProcess()'.
 *
 * Lets the tests drive 'drushResult()' and 'drush()' deterministically without
 * spawning a real Drush binary.
 */
class ProcessStubDrushDriver extends DrushDriver {

  /**
   * The process returned in place of a real execution.
   */
  public ?Process $stubProcess = NULL;

  /**
   * {@inheritdoc}
   */
  protected function runProcess(string $cmd): Process {
    if (!$this->stubProcess instanceof Process) {
      throw new \LogicException('A stub process must be set before the driver runs a command.');
    }

    return $this->stubProcess;
  }

}
