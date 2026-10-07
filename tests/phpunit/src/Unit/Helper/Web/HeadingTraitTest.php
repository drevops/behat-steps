<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper\Web;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\TraversableElement;
use DrevOps\BehatSteps\Helper\Web\HeadingTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for HeadingTrait.
 */
#[CoversTrait(HeadingTrait::class)]
class HeadingTraitTest extends UnitTestCase {

  /**
   * A test implementation of HeadingTrait.
   */
  protected HeadingTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new HeadingTraitTestImplementation();
  }

  #[DataProvider('dataProviderFind')]
  public function testFind(array $texts, string $heading, ?int $expected_index): void {
    $headings = array_map(function (string $text): NodeElement {
      $element = $this->createStub(NodeElement::class);
      $element->method('getText')->willReturn($text);

      return $element;
    }, $texts);

    $container = $this->createMock(TraversableElement::class);
    $container->expects($this->once())->method('findAll')->with('css', 'h1, h2, h3, h4, h5, h6')->willReturn($headings);

    $found = $this->testObject->headingFind($container, $heading);

    $this->assertSame($expected_index === NULL ? NULL : $headings[$expected_index], $found);
  }

  public static function dataProviderFind(): array {
    return [
      'exact match' => [['Latest news', 'About'], 'About', 1],
      'whitespace around the text is ignored' => [['  Latest news '], 'Latest news', 0],
      'first of 2 matches' => [['About', 'About'], 'About', 0],
      'partial text does not match' => [['Latest news'], 'Latest', NULL],
      'case differs' => [['About'], 'about', NULL],
      'no headings' => [[], 'About', NULL],
    ];
  }

}

/**
 * Test implementation of HeadingTrait.
 */
class HeadingTraitTestImplementation {

  use HeadingTrait;

}
