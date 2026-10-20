<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueItemCapabilityInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Exception\AssertionException;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;

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
 * @title Queues
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
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
    // Resolving a backend fails in a suite that lists no backend serving
    // queues, so a scenario that used no queue returns first.
    if ($this->skipTag(__TRAIT__, $scope) || $this->queueNames === []) {
      return;
    }

    $backend = $this->backendFor(QueueCapabilityInterface::class);

    foreach ($this->queueNames as $queue_name) {
      $backend->queueDelete($queue_name);
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
   *
   * @actor:system
   */
  #[Given('the following item is in the queue :queue:')]
  public function queueAddItem(string $queue, TableNode $fields): void {
    $data = $fields->getRowsHash()['data'] ?? '{}';

    if (!is_string($data)) {
      throw new \RuntimeException('The "data" value must be a single JSON string.');
    }

    $this->queueResolveBackend($queue);

    $this->backendFor(QueueItemCapabilityInterface::class)->queueAddItem($queue, $this->queueDecodeData($data));
  }

  /**
   * Empty a queue.
   *
   * @code
   * Given the queue "myqueue" is empty
   * @endcode
   *
   * @actor:system
   */
  #[Given('the queue :queue is empty')]
  public function queueEmpty(string $queue): void {
    $this->queueClear($queue);
  }

  /**
   * Process a specific number of items from a queue.
   *
   * @code
   * When the queue "myqueue" processes 5 items
   * @endcode
   *
   * @code
   * When the queue "myqueue" processes 1 item
   * @endcode
   *
   * @actor:system
   */
  #[When('the queue :queue processes :count item(s)')]
  public function queueProcessItems(string $count, string $queue): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $processed = $this->queueProcess($queue, $count);

    if ($processed < $count) {
      throw new \RuntimeException(sprintf('Queue "%s" has no more items to process. Processed %d of %d requested items.', $queue, $processed, $count));
    }
  }

  /**
   * Process all items from a queue.
   *
   * @code
   * When the queue "myqueue" is processed
   * @endcode
   *
   * @actor:system
   */
  #[When('the queue :queue is processed')]
  public function queueProcessAll(string $queue): void {
    $limit = $this->queueGetProcessLimit();
    $processed = $this->queueProcess($queue, $limit);

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
   *
   * @actor:system
   */
  #[Then('the queue :queue should have :count item(s)')]
  public function queueAssertItemCount(string $queue, string $count): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $actual = $this->queueCountItems($queue);

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
   *
   * @actor:system
   */
  #[Then('the queue :queue should be empty')]
  public function queueAssertEmpty(string $queue): void {
    $actual = $this->queueCountItems($queue);

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
   * Count the items in a queue.
   *
   * The queue is deleted after the scenario.
   *
   * @param string $queue
   *   The queue name.
   *
   * @return int
   *   The number of items.
   */
  public function queueCountItems(string $queue): int {
    return $this->queueResolveBackend($queue)->queueCountItems($queue);
  }

  /**
   * Delete every item of a queue.
   *
   * The queue is deleted after the scenario.
   *
   * @param string $queue
   *   The queue name.
   */
  public function queueClear(string $queue): void {
    $this->queueResolveBackend($queue)->queueDelete($queue);
  }

  /**
   * Process items from a queue with the queue's worker.
   *
   * The queue is deleted after the scenario.
   *
   * @param string $queue
   *   The queue name.
   * @param int $limit
   *   The most items to process.
   *
   * @return int
   *   The number of items processed, below the limit when the queue ran out.
   */
  public function queueProcess(string $queue, int $limit): int {
    return $this->queueResolveBackend($queue)->queueProcess($queue, $limit, $this->queueGetLeaseTime());
  }

  /**
   * Resolve the backend serving a queue, which is deleted after the scenario.
   *
   * @param string $queue
   *   The queue name.
   */
  protected function queueResolveBackend(string $queue): QueueCapabilityInterface {
    $backend = $this->backendFor(QueueCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->queueTrackName($queue);

    return $backend;
  }

  /**
   * Decode the JSON data of a queue item.
   *
   * @param string $data
   *   The JSON data.
   *
   * @return mixed
   *   The decoded data, with objects as associative arrays.
   *
   * @throws \RuntimeException
   *   When the data is not valid JSON.
   */
  protected function queueDecodeData(string $data): mixed {
    $decoded = json_decode($data, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException(sprintf('The "data" value is not valid JSON: %s.', json_last_error_msg()));
    }

    return $decoded;
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
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function queueConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'If `TRUE`, deletes every queue the queue steps used once the scenario finishes, items from before the scenario included. If `FALSE`, the queues stay.'),
      new Option('process_limit', default: 1000, description: 'The number of items at which `the queue :queue is processed` stops and fails, as a guard against a queue that keeps refilling, so a queue holding exactly this many items fails the step too.'),
      new Option('lease_time', default: 30, description: 'The time, in seconds, a queue item stays claimed while a processing step works on it.'),
    ];
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function queuePrerequisites(): array {
    return [
      Prerequisite::capability(QueueCapabilityInterface::class),
    ];
  }

}
