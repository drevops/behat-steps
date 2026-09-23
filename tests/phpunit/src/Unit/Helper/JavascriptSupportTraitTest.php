<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Helper\JavascriptSupportTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for JavascriptSupportTrait.
 */
#[CoversTrait(JavascriptSupportTrait::class)]
class JavascriptSupportTraitTest extends UnitTestCase {

  public function testStartedDriverThatEvaluatesScriptIsSupported(): void {
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('isStarted')->willReturn(TRUE);
    $driver->expects($this->never())->method('start');
    $driver->expects($this->once())->method('evaluateScript')->with('true');

    $this->assertTrue($this->createContext($driver)->javascriptSupportAvailable());
  }

  public function testStoppedDriverIsStartedBeforeTheCheck(): void {
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('isStarted')->willReturn(FALSE);
    $driver->expects($this->once())->method('start');
    $driver->expects($this->once())->method('evaluateScript')->with('true');

    $this->assertTrue($this->createContext($driver)->javascriptSupportAvailable());
  }

  public function testDriverRefusingToEvaluateIsNotSupported(): void {
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('isStarted')->willReturn(TRUE);
    $driver->method('evaluateScript')->willThrowException(new UnsupportedDriverActionException('no scripting', $driver));

    $this->assertFalse($this->createContext($driver)->javascriptSupportAvailable());
  }

  public function testDriverThatFailsToStartIsNotSupported(): void {
    $driver = $this->createMock(DriverInterface::class);
    $driver->method('isStarted')->willReturn(FALSE);
    $driver->method('start')->willThrowException(new \RuntimeException('no browser'));

    $this->assertFalse($this->createContext($driver)->javascriptSupportAvailable());
  }

  /**
   * Builds a context whose default session runs on the given driver.
   */
  protected function createContext(DriverInterface&MockObject $driver): JavascriptSupportTraitTestImplementation {
    $session = $this->createMock(Session::class);
    $session->method('getDriver')->willReturn($driver);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $context = new JavascriptSupportTraitTestImplementation();
    $context->setMink($mink);

    return $context;
  }

}

/**
 * Test implementation of JavascriptSupportTrait.
 */
class JavascriptSupportTraitTestImplementation extends RawMinkContext {

  use JavascriptSupportTrait;

}
