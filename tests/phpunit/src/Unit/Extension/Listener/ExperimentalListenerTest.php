<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Listener;

use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistry;
use DrevOps\BehatSteps\Extension\Listener\ExperimentalListener;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests the list of experimental code paths printed after a run.
 */
#[CoversClass(ExperimentalListener::class)]
class ExperimentalListenerTest extends UnitTestCase {

  public function testSubscribesToTheEndOfTheRun(): void {
    $this->assertSame([ExerciseCompleted::AFTER => ['printNotices', 0]], ExperimentalListener::getSubscribedEvents());
  }

  public function testPrintsNothingWhenNoNoticeWasRecorded(): void {
    $output = new BufferedOutput();

    (new ExperimentalListener(new ExperimentalRegistry(), $output))->printNotices();

    $this->assertSame('', $output->fetch());
  }

  public function testPrintsEachNoticeOnceWithItsCount(): void {
    $registry = new ExperimentalRegistry();
    $registry->record('drush14.user-delete', 'The account was deleted with "entity:delete".');
    $registry->record('drush14.user-delete', 'The account was deleted with "entity:delete".');
    $registry->record('acme.step', 'The step is experimental.');
    $output = new BufferedOutput();

    (new ExperimentalListener($registry, $output))->printNotices();

    $expected = implode(PHP_EOL, [
      '',
      '--- Experimental behavior used in this run:',
      '',
      '    [drush14.user-delete] The account was deleted with "entity:delete". (2 times)',
      '    [acme.step] The step is experimental. (1 time)',
      '',
      '',
    ]);
    $this->assertSame($expected, $output->fetch());
  }

}
