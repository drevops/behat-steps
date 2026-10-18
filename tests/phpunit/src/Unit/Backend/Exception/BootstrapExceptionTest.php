<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Exception;

use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Backend\Exception\Exception;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the BootstrapException.
 */
#[CoversClass(BootstrapException::class)]
#[Group('exception')]
class BootstrapExceptionTest extends UnitTestCase {

  public function testMessageCodeAndPreviousArePropagated(): void {
    $previous = new \RuntimeException('root cause');

    $exception = new BootstrapException('No Drupal installation found at /app.', 3, $previous);

    $this->assertInstanceOf(Exception::class, $exception);
    $this->assertSame('No Drupal installation found at /app.', $exception->getMessage());
    $this->assertSame(3, $exception->getCode());
    $this->assertSame($previous, $exception->getPrevious());
    $this->assertNull($exception->getBackend());
  }

  public function testCodeAndPreviousDefaultToNone(): void {
    $exception = new BootstrapException('Unsupported Drupal core version 9.5.');

    $this->assertSame(0, $exception->getCode());
    $this->assertNull($exception->getPrevious());
  }

}
