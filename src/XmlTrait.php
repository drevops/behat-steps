<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

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
   * Enable internal XML error handling before each scenario.
   *
   * @BeforeScenario
   */
  public function xmlBeforeScenario(): void {
    libxml_use_internal_errors(TRUE);
    libxml_clear_errors();

    // Clear cached document state to ensure fresh start.
    $this->xmlDocument = NULL;
    $this->xmlXpath = NULL;
    $this->xmlContentHash = NULL;
  }

  /**
   * Clear cached XML document state after each scenario.
   *
   * Ensures fresh document parsing for each scenario.
   *
   * @AfterScenario
   */
  public function xmlAfterScenario(): void {
    $this->xmlDocument = NULL;
    $this->xmlXpath = NULL;
    $this->xmlContentHash = NULL;
  }

  /**
   * Assert that a response is valid XML.
   *
   * @code
   * Then the response should be in XML format
   * @endcode
   *
   * @Then the response should be in XML format
   */
  public function xmlAssertResponseIsXml(): void {
    $content = $this->getSession()->getPage()->getContent();
    $this->xmlLoadDocument($content);
  }

  /**
   * Assert that a response is not valid XML.
   *
   * @code
   * Then the response should not be in XML format
   * @endcode
   *
   * @Then the response should not be in XML format
   */
  public function xmlAssertResponseIsNotXml(): void {
    $content = $this->getSession()->getPage()->getContent();

    $doc = new \DOMDocument();
    libxml_clear_errors();
    $loaded = @$doc->loadXML($content);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if ($loaded && empty($errors)) {
      throw new \Exception('The response is valid XML, but it should not be.');
    }
  }

  /**
   * Assert that an XML element exists.
   *
   * @code
   * Then the XML element "//book" should exist
   * Then the XML element "/library/book[@id='123']" should exist
   * @endcode
   *
   * @Then the XML element :element should exist
   */
  public function xmlAssertElementExists(string $element): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }
  }

  /**
   * Assert that an XML element does not exist.
   *
   * @code
   * Then the XML element "//nonexistent" should not exist
   * Then the XML element "/library/book[@id='999']" should not exist
   * @endcode
   *
   * @Then the XML element :element should not exist
   */
  public function xmlAssertElementNotExists(string $element): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes !== FALSE && $nodes->length > 0) {
      throw new \Exception(sprintf('The XML element "%s" was found, but it should not exist.', $element));
    }
  }

  /**
   * Assert that an XML element content equals specified text.
   *
   * @code
   * Then the XML element "//title" should be equal to "The Great Adventure"
   * Then the XML element "/library/book[1]/author" should be equal to "John Doe"
   * @endcode
   *
   * @Then the XML element :element should be equal to :text
   */
  public function xmlAssertElementEquals(string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new \Exception(sprintf('The XML element "%s" is not a valid node.', $element));
      // @codeCoverageIgnoreEnd
    }

    $actual_text = trim($node->textContent);
    if ($actual_text !== $text) {
      throw new \Exception(sprintf('The XML element "%s" content is "%s", but expected "%s".', $element, $actual_text, $text));
    }
  }

  /**
   * Assert that an XML element content does not equal specified text.
   *
   * @code
   * Then the XML element "//title" should not be equal to "Wrong Title"
   * Then the XML element "/library/book[1]/author" should not be equal to "Wrong Author"
   * @endcode
   *
   * @Then the XML element :element should not be equal to :text
   */
  public function xmlAssertElementNotEquals(string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new \Exception(sprintf('The XML element "%s" is not a valid node.', $element));
      // @codeCoverageIgnoreEnd
    }

    $actual_text = trim($node->textContent);
    if ($actual_text === $text) {
      throw new \Exception(sprintf('The XML element "%s" content is "%s", but it should not be.', $element, $actual_text));
    }
  }

  /**
   * Assert that an XML element contains specified text.
   *
   * @code
   * Then the XML element "//description" should contain "sample book"
   * Then the XML element "/library/book[1]/description" should contain "detailed"
   * @endcode
   *
   * @Then the XML element :element should contain :text
   */
  public function xmlAssertElementContains(string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new \Exception(sprintf('The XML element "%s" is not a valid node.', $element));
      // @codeCoverageIgnoreEnd
    }

    $actual_text = $node->textContent;
    if (!str_contains($actual_text, $text)) {
      throw new \Exception(sprintf('The XML element "%s" does not contain "%s". Actual content: "%s".', $element, $text, trim($actual_text)));
    }
  }

  /**
   * Assert that an XML element does not contain specified text.
   *
   * @code
   * Then the XML element "//description" should not contain "nonexistent"
   * Then the XML element "/library/book[1]/title" should not contain "wrong"
   * @endcode
   *
   * @Then the XML element :element should not contain :text
   */
  public function xmlAssertElementNotContains(string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new \Exception(sprintf('The XML element "%s" is not a valid node.', $element));
      // @codeCoverageIgnoreEnd
    }

    $actual_text = $node->textContent;
    if (str_contains($actual_text, $text)) {
      throw new \Exception(sprintf('The XML element "%s" contains "%s", but it should not.', $element, $text));
    }
  }

  /**
   * Assert that an XML attribute exists on an element.
   *
   * @code
   * Then the XML attribute "id" on element "//book" should exist
   * Then the XML attribute "category" on element "/library/book[1]" should exist
   * @endcode
   *
   * @Then the XML attribute :attribute on element :element should exist
   */
  public function xmlAssertAttributeExists(string $attribute, string $element): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new \Exception(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element));
    }
  }

  /**
   * Assert that an XML attribute does not exist on an element.
   *
   * @code
   * Then the XML attribute "nonexistent" on element "//book" should not exist
   * Then the XML attribute "missing" on element "/library/book[1]" should not exist
   * @endcode
   *
   * @Then the XML attribute :attribute on element :element should not exist
   */
  public function xmlAssertAttributeNotExists(string $attribute, string $element): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $node = $nodes->item(0);
    if ($node instanceof \DOMElement && $node->hasAttribute($attribute)) {
      throw new \Exception(sprintf('The XML attribute "%s" on element "%s" was found, but it should not exist.', $attribute, $element));
    }
  }

  /**
   * Assert that an XML attribute value equals specified text.
   *
   * @code
   * Then the XML attribute "id" on element "//book" should be equal to "123"
   * Then the XML attribute "category" on element "/library/book[1]" should be equal to "fiction"
   * @endcode
   *
   * @Then the XML attribute :attribute on element :element should be equal to :text
   */
  public function xmlAssertAttributeEquals(string $attribute, string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new \Exception(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element));
    }

    $actual_value = $node->getAttribute($attribute);
    if ($actual_value !== $text) {
      throw new \Exception(sprintf('The XML attribute "%s" on element "%s" is "%s", but expected "%s".', $attribute, $element, $actual_value, $text));
    }
  }

  /**
   * Assert that an XML attribute value does not equal specified text.
   *
   * @code
   * Then the XML attribute "id" on element "//book" should not be equal to "999"
   * Then the XML attribute "category" on element "/library/book[1]" should not be equal to "science"
   * @endcode
   *
   * @Then the XML attribute :attribute on element :element should not be equal to :text
   */
  public function xmlAssertAttributeNotEquals(string $attribute, string $element, string $text): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new \Exception(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element));
    }

    $actual_value = $node->getAttribute($attribute);
    if ($actual_value === $text) {
      throw new \Exception(sprintf('The XML attribute "%s" on element "%s" is "%s", but it should not be.', $attribute, $element, $actual_value));
    }
  }

  /**
   * Assert that an XML element has a specific number of child elements.
   *
   * @code
   * Then the XML element "//library" should have "3" elements
   * Then the XML element "/library" should have "3" elements
   * @endcode
   *
   * @Then the XML element :element should have :count element(s)
   */
  public function xmlAssertElementCount(string $element, string $count): void {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new \Exception(sprintf('The XML element "%s" was not found.', $element));
    }

    $parent_node = $nodes->item(0);
    if (!$parent_node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new \Exception(sprintf('The XML element "%s" is not a valid node.', $element));
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
      throw new \Exception(sprintf('The XML element "%s" has %d child element(s), but expected %d.', $element, $child_elements, $expected_count));
    }
  }

  /**
   * Assert that the XML uses a specific namespace.
   *
   * @code
   * Then the XML should use the namespace "http://example.com/custom"
   * @endcode
   *
   * @Then the XML should use the namespace :namespace
   */
  public function xmlAssertNamespaceExists(string $namespace): void {
    $this->xmlEnsureDocument();

    $namespaces = $this->xmlExtractNamespaces();

    if (!in_array($namespace, $namespaces, TRUE)) {
      throw new \Exception(sprintf('The XML does not use the namespace "%s". Available namespaces: %s', $namespace, implode(', ', $namespaces)));
    }
  }

  /**
   * Assert that the XML does not use a specific namespace.
   *
   * @code
   * Then the XML should not use the namespace "http://example.com/nonexistent"
   * @endcode
   *
   * @Then the XML should not use the namespace :namespace
   */
  public function xmlAssertNamespaceNotExists(string $namespace): void {
    $this->xmlEnsureDocument();

    $namespaces = $this->xmlExtractNamespaces();

    if (in_array($namespace, $namespaces, TRUE)) {
      throw new \Exception(sprintf('The XML uses the namespace "%s", but it should not.', $namespace));
    }
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
      throw new \Exception(sprintf('Failed to load XML. Errors: %s', $this->xmlFormatErrors($errors)));
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
      throw new \Exception('No XML document is loaded.');
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

}
