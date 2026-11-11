<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Experimental;

/**
 * Records the experimental code paths a run takes.
 *
 * An experimental code path is a workaround or a behavior that may still
 * change. Each one has a stable ID, such as 'drush14.user-delete', and a
 * message saying what it does instead of the expected behavior.
 *
 * In strict mode, a fallback rethrows the failure it would absorb, so a run
 * shows what happens without the workaround. A notice is recorded in both
 * modes.
 */
interface ExperimentalRegistryInterface {

  /**
   * Records that an experimental code path ran.
   *
   * A notice already recorded under the ID has its count incremented, and its
   * first message is kept.
   *
   * @param string $id
   *   The stable ID of the code path.
   * @param string $message
   *   What the code path does instead of the expected behavior.
   */
  public function record(string $id, string $message): void;

  /**
   * Records a fallback taken over a failure, or rethrows it in strict mode.
   *
   * @param string $id
   *   The stable ID of the code path.
   * @param string $message
   *   What the code path does instead of the expected behavior.
   * @param \Throwable $exception
   *   The failure the fallback absorbs.
   *
   * @throws \Throwable
   *   The given exception, unchanged, in strict mode.
   */
  public function fallback(string $id, string $message, \Throwable $exception): void;

  /**
   * Whether experimental fallbacks rethrow the failure they would absorb.
   */
  public function isStrict(): bool;

  /**
   * Returns the recorded notices in the order they were first recorded.
   *
   * @return array<string, array{message: string, count: int}>
   *   The notices keyed by ID.
   */
  public function getNotices(): array;

}
