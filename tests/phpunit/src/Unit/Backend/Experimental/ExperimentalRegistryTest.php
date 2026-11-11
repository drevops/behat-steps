<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Experimental;

use DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistry;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the experimental registry.
 */
#[CoversClass(ExperimentalRegistry::class)]
#[Group('experimental')]
class ExperimentalRegistryTest extends UnitTestCase {

  public function testStartsEmptyAndLenient(): void {
    $registry = new ExperimentalRegistry();

    $this->assertSame([], $registry->getNotices());
    $this->assertFalse($registry->isStrict());
    $this->assertTrue((new ExperimentalRegistry(TRUE))->isStrict());
  }

  public function testRecordCountsEachIdAndKeepsTheFirstMessage(): void {
    $registry = new ExperimentalRegistry();

    $registry->record('b.second', 'Second.');
    $registry->record('a.first', 'First.');
    $registry->record('b.second', 'A later message.');

    $this->assertSame([
      'b.second' => ['message' => 'Second.', 'count' => 2],
      'a.first' => ['message' => 'First.', 'count' => 1],
    ], $registry->getNotices());
  }

  /**
   * Tests that a notice without an ID or a message is rejected.
   *
   * @param string $id
   *   The ID of the notice.
   * @param string $message
   *   The message of the notice.
   * @param string $expected_message
   *   The message the record is expected to fail with.
   */
  #[DataProvider('dataProviderRecordRejectsEmptyValue')]
  public function testRecordRejectsEmptyValue(string $id, string $message, string $expected_message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_message);

    (new ExperimentalRegistry())->record($id, $message);
  }

  public static function dataProviderRecordRejectsEmptyValue(): \Iterator {
    yield 'an empty ID' => ['', 'Message.', 'An experimental notice needs a non-empty ID.'];
    yield 'a blank ID' => [' ', 'Message.', 'An experimental notice needs a non-empty ID.'];
    yield 'an empty message' => ['an.id', '', 'The experimental notice "an.id" needs a non-empty message.'];
    yield 'a blank message' => ['an.id', ' ', 'The experimental notice "an.id" needs a non-empty message.'];
  }

  public function testFallbackRecordsTheNoticeWhenLenient(): void {
    $registry = new ExperimentalRegistry();

    $registry->fallback('an.id', 'Message.', new \RuntimeException('Failure.'));

    $this->assertSame(['an.id' => ['message' => 'Message.', 'count' => 1]], $registry->getNotices());
  }

  public function testFallbackRethrowsTheFailureWhenStrict(): void {
    $registry = new ExperimentalRegistry(TRUE);
    $exception = new \LogicException('Failure.');

    try {
      $registry->fallback('an.id', 'Message.', $exception);
      $this->fail('The failure was absorbed in strict mode.');
    }
    catch (\LogicException $caught) {
      $this->assertSame($exception, $caught);
    }

    $this->assertSame([], $registry->getNotices());
  }

}
