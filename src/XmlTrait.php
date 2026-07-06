<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Assert XML responses with element and attribute checks.
 *
 * - Assert response is valid XML format.
 * - Assert XML element existence and content.
 * - Assert XML attribute values.
 * - Assert XML structure and namespace usage.
 */
trait XmlTrait {

  /**
   * The current XML document.
   */
  protected ?\DOMDocument $xmlDocument = NULL;

  /**
   * The current XPath instance.
   */
  protected ?\DOMXPath $xmlXpath = NULL;

  /**
   * Hash of the currently loaded XML content.
   *
   * Used to detect when page content changes and document needs reloading.
   */
  protected ?string $xmlContentHash = NULL;

  /**
   * XML content set directly for testing without an HTTP request.
   */
  protected ?string $xmlTestContent = NULL;

  /**
   * Enable internal XML error handling before each scenario.
   */
  #[BeforeScenario]
  public function xmlBeforeScenario(): void {
    libxml_use_internal_errors(TRUE);
    libxml_clear_errors();

    // Clear cached document state to ensure fresh start.
    $this->xmlDocument = NULL;
    $this->xmlXpath = NULL;
    $this->xmlContentHash = NULL;
    $this->xmlTestContent = NULL;
  }

  /**
   * Clear cached XML document state after each scenario.
   *
   * Ensures fresh document parsing for each scenario.
   */
  #[AfterScenario]
  public function xmlAfterScenario(): void {
    $this->xmlDocument = NULL;
    $this->xmlXpath = NULL;
    $this->xmlContentHash = NULL;
    $this->xmlTestContent = NULL;
  }

  /**
   * Set the response XML content from a fixture file.
   *
   * @code
   * Given the response content from the file "xml_valid.xml"
   * @endcode
   */
  #[Given('the response content from the file :filename')]
  public function xmlSetResponseContentFromFile(string $filename): void {
    $this->xmlTestContent = $this->xmlReadFile($filename);
    $this->xmlDocument = NULL;
    $this->xmlXpath = NULL;
    $this->xmlContentHash = NULL;
  }

  /**
   * Set the response XML content directly from a PyString.
   *
   * @code
   * Given the response content is the following:
   *   """
   *   <?xml version="1.0"?><root><item>value</item></root>
   *   """
   * @endcode
   */
  #[Given('the response content is the following:')]
  public function xmlSetResponseContentDirect(PyStringNode $content): void {
    $this->xmlTestContent = $content->getRaw();
    $this->xmlDocument = NULL;
    $this->xmlXpath = NULL;
    $this->xmlContentHash = NULL;
  }

  /**
   * Assert that a response is valid XML.
   *
   * @code
   * Then the response should be in XML format
   *
   * # Content set by a fixture step is validated instead of the page content.
   * Given the response content from the file "xml_valid.xml"
   * Then the response should be in XML format
   * @endcode
   */
  #[Then('the response should be in XML format')]
  public function xmlAssertResponseIsXml(): void {
    $this->xmlEnsureDocument();
  }

  /**
   * Assert that a response is not valid XML.
   *
   * @code
   * Then the response should not be in XML format
   *
   * # Content set by a fixture step is validated instead of the page content.
   * Given the response content from the file "xml_invalid.xml"
   * Then the response should not be in XML format
   * @endcode
   */
  #[Then('the response should not be in XML format')]
  public function xmlAssertResponseIsNotXml(): void {
    // Resolve content the same way as xmlEnsureDocument(): prefer content set
    // by the fixture steps, falling back to the live page content.
    $content = $this->xmlTestContent ?? $this->getSession()->getPage()->getContent();

    $doc = new \DOMDocument();
    libxml_clear_errors();
    $loaded = @$doc->loadXML($content);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if ($loaded && empty($errors)) {
      throw new ExpectationException('The response is valid XML, but it should not be.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element exists.
   *
   * @code
   * Then the XML element "//book" should exist
   * Then the XML element "/library/book[@id='123']" should exist
   * @endcode
   */
  #[Then('the XML element :element should exist')]
  public function xmlAssertElementExists(string $element): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element does not exist.
   *
   * @code
   * Then the XML element "//nonexistent" should not exist
   * Then the XML element "/library/book[@id='999']" should not exist
   * @endcode
   */
  #[Then('the XML element :element should not exist')]
  public function xmlAssertElementNotExists(string $element): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes !== FALSE && $nodes->length > 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was found, but it should not exist.', $element), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element content equals specified text.
   *
   * @code
   * Then the XML element "//title" should be equal to "The Great Adventure"
   * Then the XML element "/library/book[1]/author" should be equal to "John Doe"
   * @endcode
   */
  #[Then('the XML element :element should be equal to :text')]
  public function xmlAssertElementEquals(string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new ExpectationException(sprintf('The XML element "%s" is not a valid node.', $element), $this->getSession()->getDriver());
      // @codeCoverageIgnoreEnd
    }

    $actual_text = trim($node->textContent);
    if ($actual_text !== $text) {
      throw new ExpectationException(sprintf('The XML element "%s" content is "%s", but expected "%s".', $element, $actual_text, $text), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element content does not equal specified text.
   *
   * @code
   * Then the XML element "//title" should not be equal to "Wrong Title"
   * Then the XML element "/library/book[1]/author" should not be equal to "Wrong Author"
   * @endcode
   */
  #[Then('the XML element :element should not be equal to :text')]
  public function xmlAssertElementNotEquals(string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new ExpectationException(sprintf('The XML element "%s" is not a valid node.', $element), $this->getSession()->getDriver());
      // @codeCoverageIgnoreEnd
    }

    $actual_text = trim($node->textContent);
    if ($actual_text === $text) {
      throw new ExpectationException(sprintf('The XML element "%s" content is "%s", but it should not be.', $element, $actual_text), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element contains specified text.
   *
   * @code
   * Then the XML element "//description" should contain "sample book"
   * Then the XML element "/library/book[1]/description" should contain "detailed"
   * @endcode
   */
  #[Then('the XML element :element should contain :text')]
  public function xmlAssertElementContains(string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new ExpectationException(sprintf('The XML element "%s" is not a valid node.', $element), $this->getSession()->getDriver());
      // @codeCoverageIgnoreEnd
    }

    $actual_text = $node->textContent;
    if (!str_contains($actual_text, $text)) {
      throw new ExpectationException(sprintf('The XML element "%s" does not contain "%s". Actual content: "%s".', $element, $text, trim($actual_text)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element does not contain specified text.
   *
   * @code
   * Then the XML element "//description" should not contain "nonexistent"
   * Then the XML element "/library/book[1]/title" should not contain "wrong"
   * @endcode
   */
  #[Then('the XML element :element should not contain :text')]
  public function xmlAssertElementNotContains(string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new ExpectationException(sprintf('The XML element "%s" is not a valid node.', $element), $this->getSession()->getDriver());
      // @codeCoverageIgnoreEnd
    }

    $actual_text = $node->textContent;
    if (str_contains($actual_text, $text)) {
      throw new ExpectationException(sprintf('The XML element "%s" contains "%s", but it should not.', $element, $text), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute exists on an element.
   *
   * @code
   * Then the XML attribute "id" on element "//book" should exist
   * Then the XML attribute "category" on element "/library/book[1]" should exist
   * @endcode
   */
  #[Then('the XML attribute :attribute on element :element should exist')]
  public function xmlAssertAttributeExists(string $attribute, string $element): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute does not exist on an element.
   *
   * @code
   * Then the XML attribute "nonexistent" on element "//book" should not exist
   * Then the XML attribute "missing" on element "/library/book[1]" should not exist
   * @endcode
   */
  #[Then('the XML attribute :attribute on element :element should not exist')]
  public function xmlAssertAttributeNotExists(string $attribute, string $element): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if ($node instanceof \DOMElement && $node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was found, but it should not exist.', $attribute, $element), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute value equals specified text.
   *
   * @code
   * Then the XML attribute "id" on element "//book" should be equal to "123"
   * Then the XML attribute "category" on element "/library/book[1]" should be equal to "fiction"
   * @endcode
   */
  #[Then('the XML attribute :attribute on element :element should be equal to :text')]
  public function xmlAssertAttributeEquals(string $attribute, string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element), $this->getSession()->getDriver());
    }

    $actual_value = $node->getAttribute($attribute);
    if ($actual_value !== $text) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" is "%s", but expected "%s".', $attribute, $element, $actual_value, $text), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute value does not equal specified text.
   *
   * @code
   * Then the XML attribute "id" on element "//book" should not be equal to "999"
   * Then the XML attribute "category" on element "/library/book[1]" should not be equal to "science"
   * @endcode
   */
  #[Then('the XML attribute :attribute on element :element should not be equal to :text')]
  public function xmlAssertAttributeNotEquals(string $attribute, string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element), $this->getSession()->getDriver());
    }

    $actual_value = $node->getAttribute($attribute);
    if ($actual_value === $text) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" is "%s", but it should not be.', $attribute, $element, $actual_value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute value contains specified text.
   *
   * @code
   * Then the XML attribute "category" on element "//book" should contain "fic"
   * Then the XML attribute "id" on element "/library/book[1]" should contain "12"
   * @endcode
   */
  #[Then('the XML attribute :attribute_name on element :element should contain :text')]
  public function xmlAssertAttributeContains(string $attribute_name, string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute_name)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute_name, $element), $this->getSession()->getDriver());
    }

    $actual_value = $node->getAttribute($attribute_name);
    if (!str_contains($actual_value, $text)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" does not contain "%s". Actual value: "%s".', $attribute_name, $element, $text, $actual_value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute value does not contain specified text.
   *
   * @code
   * Then the XML attribute "category" on element "//book" should not contain "science"
   * Then the XML attribute "id" on element "/library/book[1]" should not contain "999"
   * @endcode
   */
  #[Then('the XML attribute :attribute_name on element :element should not contain :text')]
  public function xmlAssertAttributeNotContains(string $attribute_name, string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute_name)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute_name, $element), $this->getSession()->getDriver());
    }

    $actual_value = $node->getAttribute($attribute_name);
    if (str_contains($actual_value, $text)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" contains "%s", but it should not.', $attribute_name, $element, $text), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element has a specific number of child elements.
   *
   * @code
   * Then the XML element "//library" should have "3" elements
   * Then the XML element "/library" should have "3" elements
   * @endcode
   */
  #[Then('the XML element :element should have :count element(s)')]
  public function xmlAssertElementCount(string $element, string $count): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ExpectationException(sprintf('The XML element "%s" was not found.', $element), $this->getSession()->getDriver());
    }

    $parent_node = $nodes->item(0);
    if (!$parent_node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new ExpectationException(sprintf('The XML element "%s" is not a valid node.', $element), $this->getSession()->getDriver());
      // @codeCoverageIgnoreEnd
    }

    $child_elements = 0;

    foreach ($parent_node->childNodes as $child) {
      if ($child->nodeType === XML_ELEMENT_NODE) {
        $child_elements++;
      }
    }

    $expected_count = (int) $count;
    if ($child_elements !== $expected_count) {
      throw new ExpectationException(sprintf('The XML element "%s" has %d child element(s), but expected %d.', $element, $child_elements, $expected_count), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the XML uses a specific namespace.
   *
   * @code
   * Then the XML should use the namespace "http://example.com/custom"
   * @endcode
   */
  #[Then('the XML should use the namespace :namespace')]
  public function xmlAssertNamespaceExists(string $namespace): void {
    $this->xmlEnsureDocument();

    $namespaces = $this->xmlExtractNamespaces();

    if (!in_array($namespace, $namespaces, TRUE)) {
      throw new ExpectationException(sprintf('The XML does not use the namespace "%s". Available namespaces: %s', $namespace, implode(', ', $namespaces)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the XML does not use a specific namespace.
   *
   * @code
   * Then the XML should not use the namespace "http://example.com/nonexistent"
   * @endcode
   */
  #[Then('the XML should not use the namespace :namespace')]
  public function xmlAssertNamespaceNotExists(string $namespace): void {
    $this->xmlEnsureDocument();

    $namespaces = $this->xmlExtractNamespaces();

    if (in_array($namespace, $namespaces, TRUE)) {
      throw new ExpectationException(sprintf('The XML uses the namespace "%s", but it should not.', $namespace), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the response validates against an inline XSD schema.
   *
   * @code
   * Then the response should match the following XSD schema:
   *   """
   *   <?xml version="1.0"?>
   *   <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
   *     <xs:element name="note" type="xs:string"/>
   *   </xs:schema>
   *   """
   * @endcode
   */
  #[Then('the response should match the following XSD schema:')]
  public function xmlAssertMatchesXsd(PyStringNode $schema): void {
    $this->xmlValidateXsd($schema->getRaw());
  }

  /**
   * Assert that the response validates against an XSD schema from a file.
   *
   * @code
   * Then the response should match the XSD schema in the file "xml_schema.xsd"
   * @endcode
   */
  #[Then('the response should match the XSD schema in the file :filename')]
  public function xmlAssertMatchesXsdFromFile(string $filename): void {
    $this->xmlValidateXsd($this->xmlReadFile($filename));
  }

  /**
   * Assert that the response validates against an inline DTD.
   *
   * DTDs are namespace-unaware: to validate a namespaced document, the DTD must
   * declare the prefixed element names and the `xmlns` attributes.
   *
   * @code
   * Then the response should match the following DTD:
   *   """
   *   <!ELEMENT note (#PCDATA)>
   *   """
   * @endcode
   */
  #[Then('the response should match the following DTD:')]
  public function xmlAssertMatchesDtd(PyStringNode $dtd): void {
    $this->xmlValidateDtd($dtd->getRaw());
  }

  /**
   * Assert that the response validates against a DTD from a file.
   *
   * DTDs are namespace-unaware: to validate a namespaced document, the DTD must
   * declare the prefixed element names and the `xmlns` attributes.
   *
   * @code
   * Then the response should match the DTD in the file "xml_schema.dtd"
   * @endcode
   */
  #[Then('the response should match the DTD in the file :filename')]
  public function xmlAssertMatchesDtdFromFile(string $filename): void {
    $this->xmlValidateDtd($this->xmlReadFile($filename));
  }

  /**
   * Assert that the response validates against an inline RelaxNG schema.
   *
   * @code
   * Then the response should match the following RelaxNG schema:
   *   """
   *   <element name="note" xmlns="http://relaxng.org/ns/structure/1.0">
   *     <text/>
   *   </element>
   *   """
   * @endcode
   */
  #[Then('the response should match the following RelaxNG schema:')]
  public function xmlAssertMatchesRelaxNg(PyStringNode $schema): void {
    $this->xmlValidateRelaxNg($schema->getRaw());
  }

  /**
   * Assert that the response validates against a RelaxNG schema from a file.
   *
   * @code
   * Then the response should match the RelaxNG schema in the file "xml_schema.rng"
   * @endcode
   */
  #[Then('the response should match the RelaxNG schema in the file :filename')]
  public function xmlAssertMatchesRelaxNgFromFile(string $filename): void {
    $this->xmlValidateRelaxNg($this->xmlReadFile($filename));
  }

  /**
   * Assert that the response is a valid RSS 2.0 feed.
   *
   * Checks the required RSS 2.0 structure: an `rss` root with a `version` of
   * `2.0`, a single `channel` with `title`, `link` and `description`, and an
   * `item` with at least a `title` or a `description`.
   *
   * @code
   * Then the response should be a valid RSS feed
   * @endcode
   */
  #[Then('the response should be a valid RSS feed')]
  public function xmlAssertValidRssFeed(): void {
    $this->xmlValidateRssFeed();
  }

  /**
   * Assert that the response is a valid Atom feed.
   *
   * Checks the required Atom structure: a `feed` root in the Atom namespace
   * with `id`, `title` and `updated`, and each `entry` with `id`, `title`
   * and `updated`.
   *
   * @code
   * Then the response should be a valid Atom feed
   * @endcode
   */
  #[Then('the response should be a valid Atom feed')]
  public function xmlAssertValidAtomFeed(): void {
    $this->xmlValidateAtomFeed();
  }

  /**
   * Print the last XML response.
   *
   * @code
   * When I print last XML response
   * @endcode
   */
  #[When('I print last XML response')]
  public function xmlPrintLastResponse(): void {
    $this->xmlEnsureDocument();

    $this->xmlDocument->formatOutput = TRUE;
    $output = $this->xmlDocument->saveXML();

    if ($output === FALSE) {
      throw new ExpectationException('Failed to format the XML response.', $this->getSession()->getDriver());
    }

    print $output;
  }

  /**
   * Load XML content into the document and XPath.
   *
   * @param string $content
   *   The XML content to load.
   *
   * @throws \Exception
   *   If the XML cannot be loaded.
   */
  protected function xmlLoadDocument(string $content): void {
    $this->xmlDocument = new \DOMDocument();

    libxml_clear_errors();
    $loaded = @$this->xmlDocument->loadXML($content);

    if (!$loaded) {
      $errors = libxml_get_errors();
      throw new \RuntimeException(sprintf('Failed to load XML. Errors: %s', $this->xmlFormatErrors($errors)));
    }

    $this->xmlXpath = new \DOMXPath($this->xmlDocument);

    // Register namespaces for XPath queries.
    $namespaces = $this->xmlExtractNamespaces();
    foreach ($namespaces as $prefix => $uri) {
      if (is_string($prefix) && !empty($prefix)) {
        $this->xmlXpath->registerNamespace($prefix, $uri);
      }
    }
  }

  /**
   * Ensure that an XML document is loaded.
   *
   * Reloads the document if the page content has changed since last load.
   *
   * @throws \Exception
   *   If no document is loaded.
   */
  protected function xmlEnsureDocument(): void {
    // Use directly set test content if available.
    if ($this->xmlTestContent !== NULL) {
      if ($this->xmlDocument === NULL) {
        $this->xmlLoadDocument($this->xmlTestContent);
      }
      return;
    }

    $content = $this->getSession()->getPage()->getContent();
    $content_hash = md5((string) $content);

    // Reload document if it's not loaded or content has changed.
    if ($this->xmlDocument === NULL || $this->xmlContentHash !== $content_hash) {
      $this->xmlLoadDocument($content);
      $this->xmlContentHash = $content_hash;
    }
  }

  /**
   * Extract namespaces from the XML document.
   *
   * @return array<string, string>
   *   An associative array of namespace prefixes and URIs.
   */
  protected function xmlExtractNamespaces(): array {
    if ($this->xmlDocument === NULL) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('No XML document is loaded.');
      // @codeCoverageIgnoreEnd
    }

    $namespaces = [];
    $xpath = new \DOMXPath($this->xmlDocument);

    // Query for all namespace declarations.
    $query = '//namespace::*';
    $nodes = $xpath->query($query);

    if ($nodes !== FALSE) {
      foreach ($nodes as $node) {
        $prefix = $node->localName;
        $uri = $node->nodeValue;

        // Skip the default XML namespace.
        if ($uri !== 'http://www.w3.org/XML/1998/namespace') {
          $namespaces[$prefix] = $uri;
        }
      }
    }

    return $namespaces;
  }

  /**
   * Format libxml errors into a readable string.
   *
   * @param array<\LibXMLError> $errors
   *   Array of libxml errors.
   *
   * @return string
   *   Formatted error string.
   */
  protected function xmlFormatErrors(array $errors): string {
    $messages = [];
    foreach ($errors as $error) {
      $messages[] = sprintf('[Line %d] %s', $error->line, trim($error->message));
    }

    return implode('; ', $messages);
  }

  /**
   * Read a fixture file's contents.
   *
   * @param string $filename
   *   The fixture file name relative to the Mink files path.
   *
   * @return string
   *   The file contents.
   */
  protected function xmlReadFile(string $filename): string {
    $files_path = rtrim((string) $this->getMinkParameter('files_path'), '/');
    $file_path = $files_path . '/' . $filename;

    if (!file_exists($file_path)) {
      throw new \RuntimeException(sprintf('The file "%s" does not exist.', $file_path));
    }

    $content = file_get_contents($file_path);
    if ($content === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('Failed to read the file "%s".', $file_path));
      // @codeCoverageIgnoreEnd
    }

    return $content;
  }

  /**
   * Validate the response against an XSD schema.
   *
   * @param string $schema
   *   The XSD schema source.
   */
  protected function xmlValidateXsd(string $schema): void {
    $this->xmlEnsureDocument();

    libxml_clear_errors();
    $valid = @$this->xmlDocument->schemaValidateSource($schema);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if (!$valid) {
      throw new ExpectationException(sprintf('The response does not match the XSD schema: %s', $this->xmlFormatErrors($errors)), $this->getSession()->getDriver());
    }
  }

  /**
   * Validate the response against a RelaxNG schema.
   *
   * @param string $schema
   *   The RelaxNG schema source.
   */
  protected function xmlValidateRelaxNg(string $schema): void {
    $this->xmlEnsureDocument();

    libxml_clear_errors();
    $valid = @$this->xmlDocument->relaxNGValidateSource($schema);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if (!$valid) {
      throw new ExpectationException(sprintf('The response does not match the RelaxNG schema: %s', $this->xmlFormatErrors($errors)), $this->getSession()->getDriver());
    }
  }

  /**
   * Validate the response against a DTD.
   *
   * The DTD is embedded as an internal subset and the response reloaded with
   * validation enabled, so a DTD from a file and an inline DTD share one code
   * path without exposing external-entity loading.
   *
   * DTDs are namespace-unaware, so a namespaced response is validated verbatim
   * and its `xmlns` attributes must be declared in the DTD, matching native DTD
   * validation semantics.
   *
   * @param string $dtd
   *   The DTD source (element, attribute and entity declarations).
   */
  protected function xmlValidateDtd(string $dtd): void {
    $this->xmlEnsureDocument();

    $root = $this->xmlDocument->documentElement;
    if (!$root instanceof \DOMElement) {
      // @codeCoverageIgnoreStart
      throw new ExpectationException('The response has no root element to validate against the DTD.', $this->getSession()->getDriver());
      // @codeCoverageIgnoreEnd
    }

    $body = $this->xmlDocument->saveXML($root);
    if ($body === FALSE) {
      // @codeCoverageIgnoreStart
      throw new ExpectationException('Failed to serialise the response for DTD validation.', $this->getSession()->getDriver());
      // @codeCoverageIgnoreEnd
    }

    $combined = sprintf("<?xml version=\"1.0\"?>\n<!DOCTYPE %s [\n%s\n]>\n%s", $root->nodeName, $dtd, $body);

    $document = new \DOMDocument();
    libxml_clear_errors();
    $loaded = @$document->loadXML($combined, LIBXML_DTDVALID);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if (!$loaded || $errors !== []) {
      throw new ExpectationException(sprintf('The response does not match the DTD: %s', $this->xmlFormatErrors($errors)), $this->getSession()->getDriver());
    }
  }

  /**
   * Validate the response as an RSS 2.0 feed.
   */
  protected function xmlValidateRssFeed(): void {
    $this->xmlEnsureDocument();

    $root = $this->xmlDocument->documentElement;
    if (!$root instanceof \DOMElement || $root->localName !== 'rss') {
      throw new ExpectationException('The response is not a valid RSS feed: the root element must be "rss".', $this->getSession()->getDriver());
    }

    if ($root->getAttribute('version') !== '2.0') {
      throw new ExpectationException('The response is not a valid RSS feed: the "rss" element must have a "version" attribute of "2.0".', $this->getSession()->getDriver());
    }

    $channels = $this->xmlDirectChildElements($root, 'channel');
    if (count($channels) !== 1) {
      throw new ExpectationException('The response is not a valid RSS feed: the "rss" element must contain exactly one "channel" element.', $this->getSession()->getDriver());
    }

    $channel = $channels[0];

    foreach (['title', 'link', 'description'] as $required) {
      if ($this->xmlDirectChildElements($channel, $required) === []) {
        throw new ExpectationException(sprintf('The response is not a valid RSS feed: the "channel" element is missing the required "%s" element.', $required), $this->getSession()->getDriver());
      }
    }

    foreach ($this->xmlDirectChildElements($channel, 'item') as $item) {
      $has_title = $this->xmlDirectChildElements($item, 'title') !== [];
      $has_description = $this->xmlDirectChildElements($item, 'description') !== [];

      if (!$has_title && !$has_description) {
        throw new ExpectationException('The response is not a valid RSS feed: each "item" element must contain a "title" or a "description" element.', $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Validate the response as an Atom feed.
   */
  protected function xmlValidateAtomFeed(): void {
    $this->xmlEnsureDocument();

    $namespace = 'http://www.w3.org/2005/Atom';

    $root = $this->xmlDocument->documentElement;
    if (!$root instanceof \DOMElement || $root->localName !== 'feed' || $root->namespaceURI !== $namespace) {
      throw new ExpectationException('The response is not a valid Atom feed: the root element must be "feed" in the Atom namespace.', $this->getSession()->getDriver());
    }

    foreach (['id', 'title', 'updated'] as $required) {
      if ($this->xmlDirectChildElements($root, $required, $namespace) === []) {
        throw new ExpectationException(sprintf('The response is not a valid Atom feed: the "feed" element is missing the required "%s" element.', $required), $this->getSession()->getDriver());
      }
    }

    foreach ($this->xmlDirectChildElements($root, 'entry', $namespace) as $entry) {
      foreach (['id', 'title', 'updated'] as $required) {
        if ($this->xmlDirectChildElements($entry, $required, $namespace) === []) {
          throw new ExpectationException(sprintf('The response is not a valid Atom feed: an "entry" element is missing the required "%s" element.', $required), $this->getSession()->getDriver());
        }
      }
    }
  }

  /**
   * Get direct child elements matching a local name and optional namespace.
   *
   * @param \DOMNode $parent
   *   The parent node.
   * @param string $name
   *   The local element name to match.
   * @param string|null $namespace
   *   The namespace URI to match, or NULL to match elements in any namespace.
   *
   * @return array<int, \DOMElement>
   *   The matching direct child elements.
   */
  protected function xmlDirectChildElements(\DOMNode $parent, string $name, ?string $namespace = NULL): array {
    $matches = [];

    foreach ($parent->childNodes as $child) {
      if (!$child instanceof \DOMElement || $child->localName !== $name) {
        continue;
      }

      if ($namespace !== NULL && $child->namespaceURI !== $namespace) {
        continue;
      }

      $matches[] = $child;
    }

    return $matches;
  }

}
