<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Steps\Web\TableTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for TableTrait.
 */
#[CoversTrait(TableTrait::class)]
class TableTraitTest extends UnitTestCase {

  /**
   * A test implementation of TableTrait.
   */
  protected TableTraitTestImplementation $testObject;

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

    $this->testObject = new TableTraitTestImplementation();
    $this->testObject->setMink($mink);
  }

  #[DataProvider('dataProviderRowElementNotFound')]
  public function testRowElementNotFound(string $method, string $finder, string $locator, string $expected_message): void {
    $row = $this->createMock(NodeElement::class);
    $row->method('getText')->willReturn('Article title Published');
    $row->expects($this->once())->method($finder)->with($locator)->willReturn(NULL);
    $this->page->method('findAll')->with('css', 'table tr')->willReturn([$row]);

    $this->expectException(ElementNotFoundException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->{$method}($locator, 'Article title');
  }

  public static function dataProviderRowElementNotFound(): array {
    return [
      'click a missing link' => ['tableClickLinkInRow', 'findLink', 'Edit', 'Link in the row containing "Article title" with id|title|alt|text "Edit" not found.'],
      'press a missing button' => ['tablePressButtonInRow', 'findButton', 'Remove', 'Button in the row containing "Article title" with id|name|title|alt|value "Remove" not found.'],
      'assert a missing link' => ['tableAssertLinkInRow', 'findLink', 'Edit', 'Link in the row containing "Article title" with id|title|alt|text "Edit" not found.'],
    ];
  }

}

/**
 * Test implementation of TableTrait.
 */
class TableTraitTestImplementation extends RawContext {

  use TableTrait;

}
