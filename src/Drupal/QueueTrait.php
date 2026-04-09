<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Manage and assert Drupal queue state.
 *
 * - Clear queues before scenarios.
 * - Process queue items during tests.
 * - Assert queue item counts.
 */
trait QueueTrait {

  /**
   * Queue names used during the scenario.
   *
   * @var array<string>
   */
  protected array $queueNames = [];

  /**
   * Empty a queue.
   *
   * @code
   * Given the "myqueue" queue is empty
   * @endcode
   */
  #[Given('the :queue queue is empty')]
  public function queueEmpty(string $queue): void {
    $this->queueTrackName($queue);
    $queue_instance = \Drupal::service('queue')->get($queue);
    $queue_instance->deleteQueue();
    $queue_instance->createQueue();
  }

  /**
   * Process a specific number of items from a queue.
   *
   * @code
   * When I process 5 items from the "myqueue" queue
   * @endcode
   *
   * @code
   * When I process 1 item from the "myqueue" queue
   * @endcode
   */
  #[When('I process :count item(s) from the :queue queue')]
  public function queueProcessItems(int $count, string $queue): void {
    $this->queueTrackName($queue);
    $queue_instance = \Drupal::service('queue')->get($queue);
    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance($queue);
    $lease_time = $this->queueGetLeaseTime();

    $processed = 0;
    while ($processed < $count) {
      /** @var \stdClass|false $item */
      $item = $queue_instance->claimItem($lease_time);
      if (!$item) {
        throw new \RuntimeException(sprintf('Queue "%s" has no more items to process. Processed %d of %d requested items.', $queue, $processed, $count));
      }
      $worker->processItem($item->data);
      $queue_instance->deleteItem($item);
      $processed++;
    }
  }

  /**
   * Process all items from a queue.
   *
   * @code
   * When I process all items from the "myqueue" queue
   * @endcode
   */
  #[When('I process all items from the :queue queue')]
  public function queueProcessAll(string $queue): void {
    $this->queueTrackName($queue);
    $queue_instance = \Drupal::service('queue')->get($queue);
    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance($queue);
    $lease_time = $this->queueGetLeaseTime();
    $limit = $this->queueGetProcessLimit();

    $processed = 0;
    while ($processed < $limit) {
      /** @var \stdClass|false $item */
      $item = $queue_instance->claimItem($lease_time);
      if (!$item) {
        break;
      }
      $worker->processItem($item->data);
      $queue_instance->deleteItem($item);
      $processed++;
    }

    if ($processed >= $limit) {
      throw new \RuntimeException(sprintf('Queue "%s" processing reached the safety limit of %d items.', $queue, $limit));
    }
  }

  /**
   * Assert that a queue has a specific number of items.
   *
   * @code
   * Then the "myqueue" queue should have 5 items
   * @endcode
   *
   * @code
   * Then the "myqueue" queue should have 1 item
   * @endcode
   */
  #[Then('the :queue queue should have :count item(s)')]
  public function queueAssertItemCount(string $queue, int $count): void {
    $this->queueTrackName($queue);
    $queue_instance = \Drupal::service('queue')->get($queue);
    $actual = $queue_instance->numberOfItems();
    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected queue "%s" to have %d items, but it has %d.', $queue, $count, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a queue is empty.
   *
   * @code
   * Then the "myqueue" queue should be empty
   * @endcode
   */
  #[Then('the :queue queue should be empty')]
  public function queueAssertEmpty(string $queue): void {
    $this->queueTrackName($queue);
    $queue_instance = \Drupal::service('queue')->get($queue);
    $actual = $queue_instance->numberOfItems();
    if ($actual !== 0) {
      throw new ExpectationException(sprintf('Expected queue "%s" to be empty, but it has %d items.', $queue, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Clean up queues after scenario.
   */
  #[AfterScenario('@queue')]
  public function queueAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach ($this->queueNames as $queue_name) {
      $queue_instance = \Drupal::service('queue')->get($queue_name);
      $queue_instance->deleteQueue();
    }

    $this->queueNames = [];
  }

  /**
   * Get the maximum number of items to process.
   */
  protected function queueGetProcessLimit(): int {
    return 1000;
  }

  /**
   * Get the lease time for claiming queue items.
   */
  protected function queueGetLeaseTime(): int {
    return 30;
  }

  /**
   * Track a queue name for cleanup.
   */
  protected function queueTrackName(string $queue_name): void {
    if (!in_array($queue_name, $this->queueNames)) {
      $this->queueNames[] = $queue_name;
    }
  }

}
