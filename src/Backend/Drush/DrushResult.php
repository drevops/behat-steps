<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drush;

/**
 * Immutable result of a Drush command execution.
 */
final readonly class DrushResult {

  /**
   * Constructs a DrushResult.
   *
   * @param int $exitCode
   *   The command exit code, 0 for success.
   * @param string $output
   *   The command's captured standard output.
   * @param string $errorOutput
   *   The command's captured standard error output.
   */
  public function __construct(
    public int $exitCode,
    public string $output,
    public string $errorOutput,
  ) {}

}
