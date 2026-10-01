<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\DrushBackend;
use Symfony\Component\Process\Process;

/**
 * Subclass of 'DrushBackend' returning a stubbed process from 'runProcess()'.
 *
 * Lets the tests drive 'drushResult()' and 'drush()' deterministically without
 * spawning a real Drush binary.
 */
class ProcessStubDrushBackend extends DrushBackend {

  /**
   * The process returned in place of a real execution.
   */
  public ?Process $stubProcess = NULL;

  /**
   * {@inheritdoc}
   */
  protected function runProcess(array $argv): Process {
    if (!$this->stubProcess instanceof Process) {
      throw new \LogicException('A stub process must be set before the backend runs a command.');
    }

    return $this->stubProcess;
  }

}
