<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueItemCapabilityInterface;
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;

/**
 * Recording test double for the queue and queue item capabilities.
 *
 * Records every queue call in order, and answers the counts a test sets.
 */
class RecordingQueueBackend implements BackendInterface, QueueCapabilityInterface, QueueItemCapabilityInterface {

  /**
   * The calls made, each as the method name and its arguments.
   *
   * @var array<int, array{string, array<int, mixed>}>
   */
  public array $calls = [];

  /**
   * The number of items 'queueCountItems()' reports.
   */
  public int $count = 0;

  /**
   * The number of items 'queueProcess()' reports as processed.
   */
  public int $processed = 0;

  /**
   * {@inheritdoc}
   */
  public function getRandom(): RandomGeneratorInterface {
    return new RandomGenerator();
  }

  /**
   * {@inheritdoc}
   */
  public function bootstrap(): void {}

  /**
   * {@inheritdoc}
   */
  public function isBootstrapped(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function queueAddItem(string $queue, mixed $data): void {
    $this->calls[] = ['queueAddItem', [$queue, $data]];
  }

  /**
   * {@inheritdoc}
   */
  public function queueCountItems(string $queue): int {
    $this->calls[] = ['queueCountItems', [$queue]];

    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function queueDelete(string $queue): void {
    $this->calls[] = ['queueDelete', [$queue]];
  }

  /**
   * {@inheritdoc}
   */
  public function queueProcess(string $queue, int $limit, int $lease_time): int {
    $this->calls[] = ['queueProcess', [$queue, $limit, $lease_time]];

    return $this->processed;
  }

}
