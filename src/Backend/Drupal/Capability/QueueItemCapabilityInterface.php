<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: add items to a queue.
 *
 * Adding an item hands the site arbitrary data for a worker. A backend that
 * drives queues by name may not be able to do that, so this interface is
 * separate from 'QueueCapabilityInterface'.
 */
interface QueueItemCapabilityInterface {

  /**
   * Adds an item to a queue.
   *
   * @param string $queue
   *   The queue name.
   * @param mixed $data
   *   The item data, in the shape the queue worker receives.
   */
  public function queueAddItem(string $queue, mixed $data): void;

}
