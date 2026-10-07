<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Exception\AssertionException;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Manage and assert Drupal queue state.
 *
 * - Add items to a queue and empty queues.
 * - Process queue items during tests.
 * - Assert queue item counts.
 *
 * Every queue a step names is deleted once the scenario finishes. Skip the
 * deletion with `@behat-steps-skip:QueueTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait QueueTrait {

  use StringTrait;

  /**
   * Queue names used during the scenario.
   *
   * @var array<string>
   */
  protected array $queueNames = [];

  /**
   * Delete every queue the scenario used after it finishes.
   */
  #[AfterScenario]
  public function queueAfterScenario(AfterScenarioScope $scope): void {
    // Resolving a backend fails in a suite that lists no in-process Drupal
    // backend, so a scenario that used no queue returns first.
    if ($this->skipTag(__TRAIT__, $scope) || $this->queueNames === []) {
      return;
    }

    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($this->queueNames as $queue_name) {
      $queue_instance = \Drupal::service('queue')->get($queue_name);
      $queue_instance->deleteQueue();
    }

    $this->queueNames = [];
  }

  /**
   * Add an item to a queue.
   *
   * The `data` value is JSON, decoded before it is queued so a worker
   * receives the same shape it would receive in production.
   *
   * @code
   * Given the following item is in the queue "myqueue":
   *   | data | {"nid":1} |
   * @endcode
   */
  #[Given('the following item is in the queue :queue:')]
  public function queueAddItem(string $queue, TableNode $fields): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $values = $fields->getRowsHash();
    $data = $values['data'] ?? '{}';

    if (!is_string($data)) {
      throw new \RuntimeException('The "data" value must be a single JSON string.');
    }

    $decoded = json_decode($data, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException(sprintf('The "data" value is not valid JSON: %s.', json_last_error_msg()));
    }

    $this->queueTrackName($queue);

    \Drupal::service('queue')->get($queue)->createItem($decoded);
  }

  /**
   * Empty a queue.
   *
   * @code
   * Given the queue "myqueue" is empty
   * @endcode
   */
  #[Given('the queue :queue is empty')]
  public function queueEmpty(string $queue): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->queueTrackName($queue);
    $queue_instance = \Drupal::service('queue')->get($queue);
    $queue_instance->deleteQueue();
    $queue_instance->createQueue();
  }

  /**
   * Process a specific number of items from a queue.
   *
   * @code
   * When I process 5 items from the queue "myqueue"
   * @endcode
   *
   * @code
   * When I process 1 item from the queue "myqueue"
   * @endcode
   */
  #[When('I process :count item(s) from the queue :queue')]
  public function queueProcessItems(string $count, string $queue): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $count = $this->stringParseInteger($count, 'count', 0);

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
   * When I process the queue "myqueue"
   * @endcode
   */
  #[When('I process the queue :queue')]
  public function queueProcessAll(string $queue): void {
    $this->backendFor(CoreCapabilityInterface::class);

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
   * Then the queue "myqueue" should have 5 items
   * @endcode
   *
   * @code
   * Then the queue "myqueue" should have 1 item
   * @endcode
   */
  #[Then('the queue :queue should have :count item(s)')]
  public function queueAssertItemCount(string $queue, string $count): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $count = $this->stringParseInteger($count, 'count', 0);

    $this->queueTrackName($queue);
    $queue_instance = \Drupal::service('queue')->get($queue);
    $actual = $queue_instance->numberOfItems();
    if ($actual !== $count) {
      throw new AssertionException(sprintf('Expected the queue "%s" to have %d items, but it has %d.', $queue, $count, $actual));
    }
  }

  /**
   * Assert that a queue is empty.
   *
   * @code
   * Then the queue "myqueue" should be empty
   * @endcode
   */
  #[Then('the queue :queue should be empty')]
  public function queueAssertEmpty(string $queue): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->queueTrackName($queue);
    $queue_instance = \Drupal::service('queue')->get($queue);
    $actual = $queue_instance->numberOfItems();
    if ($actual !== 0) {
      throw new AssertionException(sprintf('Expected the queue "%s" to be empty, but it has %d items.', $queue, $actual));
    }
  }

  /**
   * Get the maximum number of items to process.
   */
  public function queueGetProcessLimit(): int {
    return $this->getOptionInt('queue', 'process_limit');
  }

  /**
   * Get the lease time for claiming queue items.
   */
  public function queueGetLeaseTime(): int {
    return $this->getOptionInt('queue', 'lease_time');
  }

  /**
   * Track a queue name for cleanup.
   */
  protected function queueTrackName(string $queue): void {
    if (!in_array($queue, $this->queueNames, TRUE)) {
      $this->queueNames[] = $queue;
    }
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function queueConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Delete the queues a scenario used once it finishes.'),
      new Option('process_limit', default: 1000, description: 'Maximum number of items a single queue-processing step handles.'),
      new Option('lease_time', default: 30, description: 'Time, in seconds, a claimed queue item stays leased.'),
    ];
  }

}
