<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\ElementTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for ElementTrait.
 */
#[CoversTrait(ElementTrait::class)]
class ElementTraitTest extends UnitTestCase {

  /**
   * A test implementation of ElementTrait.
   */
  protected ElementTraitTestImplementation $testObject;

  /**
   * The page served by the session under test.
   */
  protected DocumentElement&MockObject $page;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->page = $this->createMock(DocumentElement::class);

    $session = $this->createMock(Session::class);
    $session->method('getPage')->willReturn($this->page);
    $session->method('getDriver')->willReturn($this->createMock(DriverInterface::class));

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $this->testObject = new ElementTraitTestImplementation();
    $this->testObject->setMink($mink);
  }

  public function testAssertHeadingExistsThrowsWhenNoHeadingMatches(): void {
    $heading = $this->createMock(NodeElement::class);
    $heading->method('getText')->willReturn(' Archive ');
    $this->page->method('findAll')->with('css', 'h1, h2, h3, h4, h5, h6')->willReturn([$heading]);

    $this->expectException(ElementNotFoundException::class);
    $this->expectExceptionMessage('Heading with text "Latest news" not found.');

    $this->testObject->elementAssertHeadingExists('Latest news');
  }

}

/**
 * Test implementation of ElementTrait.
 */
class ElementTraitTestImplementation extends WebRawContext {

  use ElementTrait;

}
