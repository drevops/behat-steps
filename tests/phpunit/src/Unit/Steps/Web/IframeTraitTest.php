<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Steps\Web\IframeTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests naming an iframe so a browser driver can switch to it.
 */
#[CoversTrait(IframeTrait::class)]
class IframeTraitTest extends UnitTestCase {

  /**
   * A test implementation of IframeTrait.
   */
  protected IframeTraitTestImplementation $testObject;

  /**
   * The page served by the session under test.
   */
  protected DocumentElement&MockObject $page;

  /**
   * The session under test.
   */
  protected Session&MockObject $session;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->page = $this->createMock(DocumentElement::class);

    $this->session = $this->createMock(Session::class);
    $this->session->method('getPage')->willReturn($this->page);
    $this->session->method('getDriver')->willReturn($this->createStub(DriverInterface::class));

    $mink = new Mink(['default' => $this->session]);
    $mink->setDefaultSessionName('default');

    $this->testObject = new IframeTraitTestImplementation();
    $this->testObject->setMink($mink);
  }

  public function testUnnamedIframeGetsTheNameTheScriptAssigned(): void {
    $unnamed = $this->createStub(NodeElement::class);
    $unnamed->method('getAttribute')->willReturn(NULL);

    $named = $this->createStub(NodeElement::class);
    $named->method('getAttribute')->willReturn('behat_iframe_2');

    $this->page->method('find')->willReturnOnConsecutiveCalls($unnamed, $named);
    $this->session->expects($this->once())->method('executeScript');

    $this->assertSame('behat_iframe_2', $this->testObject->iframeGetName('.unnamed-iframe'));
  }

  public function testIframeRemovedWhileBeingNamedIsNotFound(): void {
    $unnamed = $this->createStub(NodeElement::class);
    $unnamed->method('getAttribute')->willReturn('');

    $this->page->method('find')->willReturnOnConsecutiveCalls($unnamed, NULL);

    $this->expectException(ElementNotFoundException::class);
    $this->expectExceptionMessage('Iframe matching css ".unnamed-iframe" not found.');

    $this->testObject->iframeGetName('.unnamed-iframe');
  }

}

/**
 * Test implementation of IframeTrait.
 */
class IframeTraitTestImplementation extends WebRawContext {

  use IframeTrait;

}
