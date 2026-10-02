<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Exception;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the UnsupportedBackendActionException.
 */
#[CoversClass(UnsupportedBackendActionException::class)]
#[Group('exception')]
class UnsupportedBackendActionExceptionTest extends TestCase {

  /**
   * Tests that the message template is populated with the backend class name.
   */
  public function testMessageFormatting(): void {
    $backend = $this->createMock(BackendInterface::class);
    $backend_class = $backend::class;

    $exception = new UnsupportedBackendActionException('Action %s is not supported.', $backend);

    $this->assertSame(sprintf('Action %s is not supported.', $backend_class), $exception->getMessage());
  }

  /**
   * Tests that the backend is accessible via getBackend().
   */
  public function testGetBackendReturnsConstructorArgument(): void {
    $backend = $this->createMock(BackendInterface::class);

    $exception = new UnsupportedBackendActionException('%s', $backend);

    $this->assertSame($backend, $exception->getBackend());
  }

  /**
   * Tests that code and previous exception are propagated to the parent.
   */
  public function testCodeAndPreviousArePropagated(): void {
    $backend = $this->createMock(BackendInterface::class);
    $previous = new \RuntimeException('root cause');

    $exception = new UnsupportedBackendActionException('%s', $backend, 42, $previous);

    $this->assertSame(42, $exception->getCode());
    $this->assertSame($previous, $exception->getPrevious());
  }

}
