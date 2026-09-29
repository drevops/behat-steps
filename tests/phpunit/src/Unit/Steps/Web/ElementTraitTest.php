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
use PHPUnit\Framework\Attributes\DataProvider;
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
   * The driver of the session under test.
   */
  protected DriverInterface&MockObject $driver;

  /**
   * The Mink instance holding the session under test.
   */
  protected Mink $mink;

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

    $this->mink = new Mink(['default' => $session]);
    $this->mink->setDefaultSessionName('default');

    $this->testObject = new ElementTraitTestImplementation();
    $this->testObject->setMink($this->mink);
  }

  public function testAssertHeadingExistsThrowsWhenNoHeadingMatches(): void {
    $heading = $this->createMock(NodeElement::class);
    $heading->method('getText')->willReturn(' Archive ');
    $this->page->method('findAll')->with('css', 'h1, h2, h3, h4, h5, h6')->willReturn([$heading]);

    $this->expectException(ElementNotFoundException::class);
    $this->expectExceptionMessage('Heading with text "Latest news" not found.');

    $this->testObject->elementAssertHeadingExists('Latest news');
  }

  /**
   * Tests that the scroll alignment follows the configured option.
   *
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param string $expected
   *   The call the script sent to the browser has to make.
   */
  #[DataProvider('dataProviderScrollToFollowsTheAlignmentOption')]
  public function testScrollToFollowsTheAlignmentOption(array $config, string $expected): void {
    $this->driver->expects($this->once())->method('evaluateScript')->with($this->stringContains($expected));

    $context = new ElementTraitTestImplementation($config);
    $context->setMink($this->mink);

    $context->elementScrollTo('#footer');
  }

  public static function dataProviderScrollToFollowsTheAlignmentOption(): array {
    return [
      'centered by default' => [[], 'element.scrollIntoView({ behavior: "auto", block: "center", inline: "center" });'],
      'aligned to the top when switched off' => [['element' => ['scroll_into_view_center' => FALSE]], 'element.scrollIntoView(true);'],
    ];
  }

}

/**
 * Test implementation of ElementTrait.
 */
class ElementTraitTestImplementation extends WebRawContext {

  use ElementTrait;

}
