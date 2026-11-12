<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Experimental;

use DrevOps\BehatSteps\Backend\Experimental\ExperimentalAwareTrait;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistry;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\ExperimentalAwareObject;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the experimental-aware trait.
 */
#[CoversTrait(ExperimentalAwareTrait::class)]
#[Group('experimental')]
class ExperimentalAwareTraitTest extends UnitTestCase {

  public function testRecordsToTheInjectedRegistry(): void {
    $registry = new ExperimentalRegistry();
    $subject = $this->createSubject();

    $subject->setExperimentalRegistry($registry);
    $subject->experimentalRecord('an.id', 'Message.');
    $subject->experimentalFallback('an.id', 'Message.', new \RuntimeException('Failure.'));

    $this->assertSame($registry, $subject->getExperimentalRegistry());
    $this->assertSame(['an.id' => ['message' => 'Message.', 'count' => 2]], $registry->getNotices());
  }

  public function testRecordsToLenientRegistryOfItsOwnWithoutInjection(): void {
    $subject = $this->createSubject();

    $subject->experimentalFallback('an.id', 'Message.', new \RuntimeException('Failure.'));

    $this->assertFalse($subject->getExperimentalRegistry()->isStrict());
    $this->assertSame(['an.id' => ['message' => 'Message.', 'count' => 1]], $subject->getExperimentalRegistry()->getNotices());
  }

  public function testFallbackRethrowsThroughStrictRegistry(): void {
    $subject = $this->createSubject();
    $subject->setExperimentalRegistry(new ExperimentalRegistry(TRUE));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Failure.');

    $subject->experimentalFallback('an.id', 'Message.', new \RuntimeException('Failure.'));
  }

  /**
   * Creates an object composing the trait.
   */
  protected function createSubject(): ExperimentalAwareObject {
    return new ExperimentalAwareObject();
  }

}
