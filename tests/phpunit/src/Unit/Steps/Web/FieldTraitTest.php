<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\FieldTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for FieldTrait.
 */
#[CoversTrait(FieldTrait::class)]
class FieldTraitTest extends UnitTestCase {

  /**
   * A test implementation of FieldTrait.
   */
  protected FieldTraitTestImplementation $testObject;

  /**
   * The page served by the session under test.
   */
  protected DocumentElement&MockObject $page;

  /**
   * The driver behind the session under test.
   */
  protected DriverInterface&MockObject $driver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->page = $this->createMock(DocumentElement::class);
    $this->driver = $this->createMock(DriverInterface::class);

    $session = $this->createMock(Session::class);
    $session->method('getPage')->willReturn($this->page);
    $session->method('getDriver')->willReturn($this->driver);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $this->testObject = new FieldTraitTestImplementation();
    $this->testObject->setMink($mink);
  }

  public function testFillMultiValueRequiresJavascriptDriver(): void {
    // The trait probes JavaScript support by evaluating a script, so a driver
    // that throws on evaluateScript() counts as non-JavaScript.
    $this->driver->method('isStarted')->willReturn(TRUE);
    $this->driver->method('evaluateScript')->willThrowException(new UnsupportedDriverActionException('JavaScript is not supported by %s', $this->driver));

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage('The "fill in the multi-value field" step requires a JavaScript-capable driver.');

    $this->testObject->fieldFillMultiValue('Tags', new TableNode([['value'], ['Drupal']]));
  }

  public function testFillMultiValueThrowsWhenInputRowIsMissing(): void {
    $this->driver->method('isStarted')->willReturn(TRUE);
    $this->driver->method('evaluateScript')->willReturn(TRUE);

    // Zero existing inputs count as one row, so no "Add another item" click
    // is attempted and the first value has no input to fill.
    $wrapper = $this->createMock(NodeElement::class);
    $wrapper->method('findAll')->willReturn([]);
    $this->page->method('find')->willReturn($wrapper);

    $this->expectException(ElementNotFoundException::class);
    $this->expectExceptionMessage('Input row of the multi-value field "Tags" with index "0" not found.');

    $this->testObject->fieldFillMultiValue('Tags', new TableNode([['value'], ['Drupal']]));
  }

}

/**
 * Test implementation of FieldTrait.
 */
class FieldTraitTestImplementation extends WebRawContext {

  use FieldTrait;

}
