<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Capability;

use DrevOps\BehatSteps\Driver\Drush\DrushResult;

/**
 * Capability: run a Drush command and return what it wrote.
 */
interface DrushCapabilityInterface {

  /**
   * Executes a Drush command.
   *
   * @param string $command
   *   The Drush command to execute.
   * @param array<int, string> $arguments
   *   Positional arguments to pass to Drush.
   * @param array<string, string|bool|null> $options
   *   Options to pass to Drush.
   *
   * @return string
   *   The command's stdout, or its stderr when stdout is empty.
   *
   * @throws \RuntimeException
   *   When the command exits with a non-zero status.
   */
  public function drush(string $command, array $arguments = [], array $options = []): string;

  /**
   * Executes a Drush command without failing on a non-zero exit.
   *
   * @param string $command
   *   The Drush command to execute.
   * @param array<int, string> $arguments
   *   Positional arguments to pass to Drush.
   * @param array<string, string|bool|null> $options
   *   Options to pass to Drush.
   *
   * @return \DrevOps\BehatSteps\Driver\Drush\DrushResult
   *   The exit code together with the captured stdout and stderr.
   */
  public function drushResult(string $command, array $arguments = [], array $options = []): DrushResult;

}
