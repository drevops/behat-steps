<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Browser\Element;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Extension\Browser\Element\DocumentElement;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the document element that replaces Mink's own.
 */
#[CoversClass(DocumentElement::class)]
class DocumentElementTest extends UnitTestCase {

  /**
   * Tests that the content lookup reports whether the document holds the text.
   *
   * @param bool $is_found
   *   Whether the browser driver finds an element holding the text.
   */
  #[DataProvider('dataProviderHasContent')]
  public function testHasContent(bool $is_found): void {
    $found = $is_found ? [$this->createStub(NodeElement::class)] : [];

    $driver = $this->createMock(DriverInterface::class);
    $driver->expects($this->atLeastOnce())->method('find')->with($this->stringContains("'Welcome to the site'"))->willReturn($found);

    $document = new DocumentElement(new Session($driver));

    $this->assertSame($is_found, $document->hasContent('Welcome to the site'));
  }

  public static function dataProviderHasContent(): \Iterator {
    yield 'found' => [TRUE];
    yield 'not found' => [FALSE];
  }

}
