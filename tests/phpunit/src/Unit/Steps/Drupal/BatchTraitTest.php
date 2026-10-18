<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Steps\Drupal\BatchTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests waiting for a Batch API job to finish.
 */
#[CoversTrait(BatchTrait::class)]
class BatchTraitTest extends UnitTestCase {

  public function testWaitReturnsOnceTheProgressElementIsGone(): void {
    $context = $this->createContextWaitingFor(TRUE);

    $context->batchWaitForCompletion();
  }

  public function testWaitFailsWhenTheJobOutlastsTheTimeout(): void {
    $context = $this->createContextWaitingFor(FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The batch job did not finish within 180 seconds.');

    $context->batchWaitForCompletion();
  }

  /**
   * Create a context whose session waits for the progress element once.
   *
   * @param bool $is_finished
   *   What the session's wait reports.
   */
  protected function createContextWaitingFor(bool $is_finished): BatchTraitTestImplementation {
    $session = $this->createMock(Session::class);
    $session->expects($this->once())->method('wait')->with(180000, 'document.getElementById("updateprogress") === null')->willReturn($is_finished);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $context = new BatchTraitTestImplementation();
    $context->setMink($mink);

    return $context;
  }

}

/**
 * Test implementation of BatchTrait.
 */
class BatchTraitTestImplementation extends WebRawContext {

  use BatchTrait;

}
