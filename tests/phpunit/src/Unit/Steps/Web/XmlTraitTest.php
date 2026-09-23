<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Steps\Web\XmlTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests for XmlTrait.
 */
#[CoversTrait(XmlTrait::class)]
class XmlTraitTest extends UnitTestCase {

  /**
   * A test implementation of XmlTrait.
   */
  protected XmlTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new XmlTraitTestImplementation();
  }

  public function testPrintLastResponseThrowsWhenSaveFails(): void {
    $this->testObject->testSetDocument(new UnserialisableDomDocument(), '<root/>');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Failed to format the XML response.');

    $this->testObject->xmlPrintLastResponse();
  }

}

/**
 * Test implementation of XmlTrait.
 */
class XmlTraitTestImplementation extends RawMinkContext {

  use XmlTrait;

  /**
   * Install a document as the one loaded for the test content.
   *
   * Content and document set together make xmlEnsureDocument() keep the
   * document instead of loading the content.
   *
   * @param \DOMDocument $document
   *   The document to install.
   * @param string $content
   *   The test content the document stands for.
   */
  public function testSetDocument(\DOMDocument $document, string $content): void {
    $this->xmlTestContent = $content;
    $this->xmlDocument = $document;
  }

}

/**
 * A document whose saveXML() always fails.
 */
class UnserialisableDomDocument extends \DOMDocument {

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
  public function saveXML(?\DOMNode $node = NULL, int $options = 0): string|false {
    return FALSE;
  }

}
