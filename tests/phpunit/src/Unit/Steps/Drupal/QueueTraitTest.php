<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\BlackboxBackendInterface;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\QueueTrait;
use DrevOps\BehatSteps\Steps\Exception\AssertionException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\RecordingQueueBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the queue steps against the queue capability.
 */
#[CoversTrait(QueueTrait::class)]
class QueueTraitTest extends UnitTestCase {

  public function testAddItemDecodesTheDataAndQueuesIt(): void {
    $backend = new RecordingQueueBackend();

    $this->createContext($backend)->queueAddItem('behat_test', new TableNode([['data', '{"nid":1,"tags":["a"]}']]));

    $this->assertSame([['queueAddItem', ['behat_test', ['nid' => 1, 'tags' => ['a']]]]], $backend->calls);
  }

  public function testAddItemWithoutDataQueuesAnEmptyObject(): void {
    $backend = new RecordingQueueBackend();

    $this->createContext($backend)->queueAddItem('behat_test', new TableNode([['other', 'value']]));

    $this->assertSame([['queueAddItem', ['behat_test', []]]], $backend->calls);
  }

  /**
   * Tests that a data cell the step cannot decode is rejected before queuing.
   *
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   The step's table.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderAddItemRejectsInvalidData')]
  public function testAddItemRejectsInvalidData(TableNode $fields, string $expected_message): void {
    $backend = new RecordingQueueBackend();

    try {
      $this->createContext($backend)->queueAddItem('behat_test', $fields);
      $this->fail('Invalid data was queued.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame($expected_message, $exception->getMessage());
    }

    $this->assertSame([], $backend->calls);
  }

  public static function dataProviderAddItemRejectsInvalidData(): \Iterator {
    yield 'invalid JSON' => [new TableNode([['data', '{"nid":']]), 'The "data" value is not valid JSON: Syntax error.'];
    yield 'more than 1 data value' => [new TableNode([['data', '{"nid":1}', '{"nid":2}']]), 'The "data" value must be a single JSON string.'];
  }

  public function testEmptyDeletesTheQueue(): void {
    $backend = new RecordingQueueBackend();

    $this->createContext($backend)->queueEmpty('behat_test');

    $this->assertSame([['queueDelete', ['behat_test']]], $backend->calls);
  }

  public function testProcessItemsPassesTheLeaseTime(): void {
    $backend = new RecordingQueueBackend();
    $backend->processed = 3;

    $this->createContext($backend, ['queue' => ['lease_time' => 45]])->queueProcessItems('3', 'behat_test');

    $this->assertSame([['queueProcess', ['behat_test', 3, 45]]], $backend->calls);
  }

  public function testProcessItemsFailsWhenTheQueueRunsOut(): void {
    $backend = new RecordingQueueBackend();
    $backend->processed = 1;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Queue "behat_test" has no more items to process. Processed 1 of 3 requested items.');

    $this->createContext($backend)->queueProcessItems('3', 'behat_test');
  }

  public function testProcessAllStopsBelowTheLimit(): void {
    $backend = new RecordingQueueBackend();
    $backend->processed = 2;

    $this->createContext($backend, ['queue' => ['process_limit' => 5]])->queueProcessAll('behat_test');

    $this->assertSame([['queueProcess', ['behat_test', 5, 30]]], $backend->calls);
  }

  public function testProcessAllFailsAtTheLimit(): void {
    $backend = new RecordingQueueBackend();
    $backend->processed = 5;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Queue "behat_test" processing reached the safety limit of 5 items.');

    $this->createContext($backend, ['queue' => ['process_limit' => 5]])->queueProcessAll('behat_test');
  }

  public function testAssertItemCountReadsTheCount(): void {
    $backend = new RecordingQueueBackend();
    $backend->count = 2;

    $this->createContext($backend)->queueAssertItemCount('behat_test', '2');

    $this->assertSame([['queueCountItems', ['behat_test']]], $backend->calls);
  }

  public function testAssertItemCountFailsOnAnotherCount(): void {
    $backend = new RecordingQueueBackend();
    $backend->count = 3;

    $this->expectException(AssertionException::class);
    $this->expectExceptionMessage('Expected the queue "behat_test" to have 2 items, but it has 3.');

    $this->createContext($backend)->queueAssertItemCount('behat_test', '2');
  }

  public function testAssertEmptyFailsOnItems(): void {
    $backend = new RecordingQueueBackend();
    $backend->count = 2;

    $this->expectException(AssertionException::class);
    $this->expectExceptionMessage('Expected the queue "behat_test" to be empty, but it has 2 items.');

    $this->createContext($backend)->queueAssertEmpty('behat_test');
  }

  public function testTeardownDeletesEveryQueueTheScenarioUsedOnce(): void {
    $backend = new RecordingQueueBackend();
    $context = $this->createContext($backend);

    $context->queueAssertEmpty('first');
    $context->queueAssertEmpty('first');
    $context->queueEmpty('second');
    $backend->calls = [];

    $context->queueAfterScenario($this->createAfterScenarioScope());

    $this->assertSame([['queueDelete', ['first']], ['queueDelete', ['second']]], $backend->calls);

    $backend->calls = [];
    $context->queueAfterScenario($this->createAfterScenarioScope());

    $this->assertSame([], $backend->calls, 'The queues are forgotten once deleted.');
  }

  public function testTeardownOfScenarioUsingNoQueueTouchesNoBackend(): void {
    $this->expectNotToPerformAssertions();

    // The context holds no backend registry, so resolving a backend would
    // throw.
    (new QueueTraitTestImplementation())->queueAfterScenario($this->createAfterScenarioScope());
  }

  public function testSkipTagKeepsTheQueues(): void {
    $backend = new RecordingQueueBackend();
    $context = $this->createContext($backend);

    $context->queueEmpty('first');
    $backend->calls = [];

    $context->queueAfterScenario($this->createAfterScenarioScope(['behat-steps-skip:QueueTrait']));

    $this->assertSame([], $backend->calls);
  }

  public function testStepsFailWithoutQueueBackend(): void {
    $registry = new BackendRegistry(['blackbox' => $this->createStub(BlackboxBackendInterface::class)]);
    $registry->setScenarioBackends(['blackbox' => 'blackbox']);

    $context = new QueueTraitTestImplementation();
    $context->setBackendRegistry($registry);

    $this->expectException(UnsupportedBackendActionException::class);
    $this->expectExceptionMessageMatches('/No backend provides ".*QueueCapabilityInterface"/');

    $context->queueAssertEmpty('behat_test');
  }

  /**
   * Creates a context whose scenario lists the given backend.
   *
   * @param \DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\RecordingQueueBackend $backend
   *   The backend serving the queues.
   * @param array<string, array<string, mixed>> $config
   *   The trait options, keyed by trait group.
   */
  protected function createContext(RecordingQueueBackend $backend, array $config = []): QueueTraitTestImplementation {
    $registry = new BackendRegistry(['drupal' => $backend]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new QueueTraitTestImplementation($config);
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of QueueTrait.
 */
class QueueTraitTestImplementation extends WebRawContext {

  use QueueTrait;

}
