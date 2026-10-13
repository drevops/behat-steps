<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: add to, count, process and delete queues.
 */
interface QueueCapabilityInterface {

  /**
   * Adds an item to a queue.
   *
   * @param string $queue
   *   The queue name.
   * @param mixed $data
   *   The item data, in the shape the queue worker receives.
   */
  public function queueAddItem(string $queue, mixed $data): void;

  /**
   * Returns the number of items in a queue.
   *
   * @param string $queue
   *   The queue name.
   *
   * @return int
   *   The number of items, 0 for a queue that does not exist.
   */
  public function queueCountItems(string $queue): int;

  /**
   * Deletes a queue and every item in it.
   *
   * Does nothing when the queue does not exist.
   *
   * @param string $queue
   *   The queue name.
   */
  public function queueDelete(string $queue): void;

  /**
   * Processes items from a queue with the queue's worker.
   *
   * @param string $queue
   *   The queue name, which is also the id of the queue worker plugin.
   * @param int $limit
   *   The most items to process. 0 processes nothing.
   * @param int $lease_time
   *   The time, in seconds, a claimed item stays leased.
   *
   * @return int
   *   The number of items processed, below the limit when the queue ran out.
   *
   * @throws \RuntimeException
   *   When no queue worker has the queue's name.
   */
  public function queueProcess(string $queue, int $limit, int $lease_time): int;

}
