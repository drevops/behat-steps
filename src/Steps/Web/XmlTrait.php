<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Helper\Web\FixtureDirectoryTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Assert XML responses with element and attribute checks.
 *
 * - Assert response is valid XML format.
 * - Assert XML element existence and content.
 * - Assert XML attribute values.
 * - Assert XML structure and namespace usage.
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
trait XmlTrait {

  use FixtureDirectoryTrait;
  use StringTrait;

  /**
   * The namespace of the Atom syndication format.
   */
  protected const string XML_ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

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
  public function xmlBeforeScenario(BeforeScenarioScope $scope): void {
    $this->xmlEnableInternalErrors();
    $this->xmlResetState();
  }

  /**
   * Clear cached XML document state after each scenario.
   */
  #[AfterScenario]
  public function xmlAfterScenario(AfterScenarioScope $scope): void {
    $this->xmlResetState();
  }

  /**
   * Set the response XML content from a fixture file.
   *
   * @code
   * Given the response XML is loaded from the file "xml_valid.xml"
   * @endcode
   */
  #[Given('the response XML is loaded from the file :filename')]
  public function xmlSetContentFromFile(string $filename): void {
    $content = $this->fixtureDirectoryReadFile($filename);

    $this->xmlResetState();
    $this->xmlTestContent = $content;
  }

  /**
   * Set the response XML content directly from a PyString.
   *
   * @code
   * Given the response XML is the following:
   *   """
   *   <?xml version="1.0"?><root><item>value</item></root>
   *   """
   * @endcode
   */
  #[Given('the response XML is the following:')]
  public function xmlSetContent(PyStringNode $content): void {
    $this->xmlResetState();
    $this->xmlTestContent = $content->getRaw();
  }

  /**
   * Print the last XML response.
   *
   * @code
   * When I print the last XML response
   * @endcode
   */
  #[When('I print the last XML response')]
  public function xmlPrintLastResponse(): void {
    $this->xmlEnsureDocument();

    $this->xmlDocument->formatOutput = TRUE;
    $output = $this->xmlDocument->saveXML();

    if ($output === FALSE) {
      throw new \RuntimeException('Failed to format the XML response.');
    }

    print $output;
  }

  /**
   * Assert that a response is valid XML.
   *
   * @code
   * Then the response should be in XML format
   *
   * # Content set by a fixture step is validated instead of the page content.
   * Given the response XML is loaded from the file "xml_valid.xml"
   * Then the response should be in XML format
   * @endcode
   */
  #[Then('the response should be in XML format')]
  public function xmlAssertResponseXml(): void {
    $parsed = $this->xmlParse($this->xmlGetContent());

    if (!$parsed['loaded'] || $parsed['errors'] !== []) {
      throw new ExpectationException(sprintf('The response is not valid XML: %s.', $this->xmlFormatErrors($parsed['errors'])), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a response is not valid XML.
   *
   * @code
   * Then the response should not be in XML format
   *
   * # Content set by a fixture step is validated instead of the page content.
   * Given the response XML is loaded from the file "xml_invalid.xml"
   * Then the response should not be in XML format
   * @endcode
   */
  #[Then('the response should not be in XML format')]
  public function xmlAssertResponseNotXml(): void {
    $parsed = $this->xmlParse($this->xmlGetContent());

    if ($parsed['loaded'] && $parsed['errors'] === []) {
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
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'XML element', 'xpath', $element);
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
   * Then the XML element "//title" should be equal to the value "The Great Adventure"
   * Then the XML element "/library/book[1]/author" should be equal to the value "John Doe"
   * @endcode
   */
  #[Then('the XML element :element should be equal to the value :value')]
  public function xmlAssertElementEquals(string $element, string $value): void {
    $node = $this->xmlGetFirstNode($element);

    $actual_text = trim($node->textContent);
    if ($actual_text !== $value) {
      throw new ExpectationException(sprintf('The XML element "%s" content is "%s", but expected "%s".', $element, $actual_text, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element content does not equal specified text.
   *
   * @code
   * Then the XML element "//title" should not be equal to the value "Wrong Title"
   * Then the XML element "/library/book[1]/author" should not be equal to the value "Wrong Author"
   * @endcode
   */
  #[Then('the XML element :element should not be equal to the value :value')]
  public function xmlAssertElementNotEquals(string $element, string $value): void {
    $node = $this->xmlGetFirstNode($element);

    $actual_text = trim($node->textContent);
    if ($actual_text === $value) {
      throw new ExpectationException(sprintf('The XML element "%s" content is "%s", but it should not be.', $element, $actual_text), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element contains specified text.
   *
   * @code
   * Then the XML element "//description" should contain the value "sample book"
   * Then the XML element "/library/book[1]/description" should contain the value "detailed"
   * @endcode
   */
  #[Then('the XML element :element should contain the value :value')]
  public function xmlAssertElementContains(string $element, string $value): void {
    $node = $this->xmlGetFirstNode($element);

    $actual_text = $node->textContent;
    if (!str_contains($actual_text, $value)) {
      throw new ExpectationException(sprintf('The XML element "%s" does not contain "%s". Actual content: "%s".', $element, $value, trim($actual_text)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML element does not contain specified text.
   *
   * @code
   * Then the XML element "//description" should not contain the value "nonexistent"
   * Then the XML element "/library/book[1]/title" should not contain the value "wrong"
   * @endcode
   */
  #[Then('the XML element :element should not contain the value :value')]
  public function xmlAssertElementNotContains(string $element, string $value): void {
    $node = $this->xmlGetFirstNode($element);

    $actual_text = $node->textContent;
    if (str_contains($actual_text, $value)) {
      throw new ExpectationException(sprintf('The XML element "%s" contains "%s", but it should not.', $element, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute exists on an element.
   *
   * @code
   * Then the XML attribute "id" on the element "//book" should exist
   * Then the XML attribute "category" on the element "/library/book[1]" should exist
   * @endcode
   */
  #[Then('the XML attribute :attribute on the element :element should exist')]
  public function xmlAssertAttributeExists(string $attribute, string $element): void {
    $node = $this->xmlGetFirstNode($element);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute does not exist on an element.
   *
   * @code
   * Then the XML attribute "nonexistent" on the element "//book" should not exist
   * Then the XML attribute "missing" on the element "/library/book[1]" should not exist
   * @endcode
   */
  #[Then('the XML attribute :attribute on the element :element should not exist')]
  public function xmlAssertAttributeNotExists(string $attribute, string $element): void {
    $node = $this->xmlGetFirstNode($element);
    if ($node instanceof \DOMElement && $node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was found, but it should not exist.', $attribute, $element), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute value equals specified text.
   *
   * @code
   * Then the XML attribute "id" on the element "//book" should be equal to the value "123"
   * Then the XML attribute "category" on the element "/library/book[1]" should be equal to the value "fiction"
   * @endcode
   */
  #[Then('the XML attribute :attribute on the element :element should be equal to the value :value')]
  public function xmlAssertAttributeEquals(string $attribute, string $element, string $value): void {
    $node = $this->xmlGetFirstNode($element);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element), $this->getSession()->getDriver());
    }

    $actual_value = $node->getAttribute($attribute);
    if ($actual_value !== $value) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" is "%s", but expected "%s".', $attribute, $element, $actual_value, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute value does not equal specified text.
   *
   * @code
   * Then the XML attribute "id" on the element "//book" should not be equal to the value "999"
   * Then the XML attribute "category" on the element "/library/book[1]" should not be equal to the value "science"
   * @endcode
   */
  #[Then('the XML attribute :attribute on the element :element should not be equal to the value :value')]
  public function xmlAssertAttributeNotEquals(string $attribute, string $element, string $value): void {
    $node = $this->xmlGetFirstNode($element);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element), $this->getSession()->getDriver());
    }

    $actual_value = $node->getAttribute($attribute);
    if ($actual_value === $value) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" is "%s", but it should not be.', $attribute, $element, $actual_value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute value contains specified text.
   *
   * @code
   * Then the XML attribute "category" on the element "//book" should contain the value "fic"
   * Then the XML attribute "id" on the element "/library/book[1]" should contain the value "12"
   * @endcode
   */
  #[Then('the XML attribute :attribute on the element :element should contain the value :value')]
  public function xmlAssertAttributeContains(string $attribute, string $element, string $value): void {
    $node = $this->xmlGetFirstNode($element);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element), $this->getSession()->getDriver());
    }

    $actual_value = $node->getAttribute($attribute);
    if (!str_contains($actual_value, $value)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" does not contain "%s". Actual value: "%s".', $attribute, $element, $value, $actual_value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an XML attribute value does not contain specified text.
   *
   * @code
   * Then the XML attribute "category" on the element "//book" should not contain the value "science"
   * Then the XML attribute "id" on the element "/library/book[1]" should not contain the value "999"
   * @endcode
   */
  #[Then('the XML attribute :attribute on the element :element should not contain the value :value')]
  public function xmlAssertAttributeNotContains(string $attribute, string $element, string $value): void {
    $node = $this->xmlGetFirstNode($element);
    if (!$node instanceof \DOMElement || !$node->hasAttribute($attribute)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" was not found.', $attribute, $element), $this->getSession()->getDriver());
    }

    $actual_value = $node->getAttribute($attribute);
    if (str_contains($actual_value, $value)) {
      throw new ExpectationException(sprintf('The XML attribute "%s" on element "%s" contains "%s", but it should not.', $attribute, $element, $value), $this->getSession()->getDriver());
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
    $count = $this->stringParseInteger($count, 'count', 0);

    $child_elements = $this->xmlCountChildElements($this->xmlGetFirstNode($element));

    if ($child_elements !== $count) {
      throw new ExpectationException(sprintf('The XML element "%s" has %d child element(s), but expected %d.', $element, $child_elements, $count), $this->getSession()->getDriver());
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
      throw new ExpectationException(sprintf('The XML does not use the namespace "%s". Available namespaces: %s.', $namespace, implode(', ', $namespaces)), $this->getSession()->getDriver());
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
    $this->xmlAssertResponseMatchesXsd($schema->getRaw());
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
    $this->xmlAssertResponseMatchesXsd($this->fixtureDirectoryReadFile($filename));
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
    $this->xmlAssertResponseMatchesDtd($dtd->getRaw());
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
    $this->xmlAssertResponseMatchesDtd($this->fixtureDirectoryReadFile($filename));
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
    $this->xmlAssertResponseMatchesRelaxNg($schema->getRaw());
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
    $this->xmlAssertResponseMatchesRelaxNg($this->fixtureDirectoryReadFile($filename));
  }

  /**
   * Assert that the response is a valid RSS 2.0 feed.
   *
   * Checks the required RSS 2.0 structure: an `rss` root with a `version` of
   * `2.0` and a single `channel` with `title`, `link` and `description`. Each
   * `item` present has a `title` or a `description`.
   *
   * @code
   * Then the response should be a valid RSS feed
   * @endcode
   */
  #[Then('the response should be a valid RSS feed')]
  public function xmlAssertRssFeedValid(): void {
    $channel = $this->xmlGetRssChannel();
    $missing = $this->xmlFindMissingChildElement([$channel], ['title', 'link', 'description']);

    if ($missing !== NULL) {
      throw new ExpectationException(sprintf('The response is not a valid RSS feed: the "channel" element is missing the required "%s" element.', $missing), $this->getSession()->getDriver());
    }

    foreach ($this->xmlDirectChildElements($channel, 'item') as $item) {
      if ($this->xmlDirectChildElements($item, 'title') === [] && $this->xmlDirectChildElements($item, 'description') === []) {
        throw new ExpectationException('The response is not a valid RSS feed: each "item" element must contain a "title" or a "description" element.', $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that the response is a valid Atom feed.
   *
   * Checks the required Atom structure. The `feed` root is in the Atom
   * namespace with `id`, `title` and `updated`, and each `entry` has `id`,
   * `title` and `updated`.
   *
   * @code
   * Then the response should be a valid Atom feed
   * @endcode
   */
  #[Then('the response should be a valid Atom feed')]
  public function xmlAssertAtomFeedValid(): void {
    $feed = $this->xmlGetAtomFeed();
    $missing = $this->xmlFindMissingChildElement([$feed], ['id', 'title', 'updated'], self::XML_ATOM_NAMESPACE);

    if ($missing !== NULL) {
      throw new ExpectationException(sprintf('The response is not a valid Atom feed: the "feed" element is missing the required "%s" element.', $missing), $this->getSession()->getDriver());
    }

    $entries = $this->xmlDirectChildElements($feed, 'entry', self::XML_ATOM_NAMESPACE);
    $missing = $this->xmlFindMissingChildElement($entries, ['id', 'title', 'updated'], self::XML_ATOM_NAMESPACE);

    if ($missing !== NULL) {
      throw new ExpectationException(sprintf('The response is not a valid Atom feed: an "entry" element is missing the required "%s" element.', $missing), $this->getSession()->getDriver());
    }
  }

  /**
   * Switch libxml to collecting its errors, and drop those collected so far.
   */
  protected function xmlEnableInternalErrors(): void {
    libxml_use_internal_errors(TRUE);
    libxml_clear_errors();
  }

  /**
   * Reset all cached XML state.
   */
  protected function xmlResetState(): void {
    $this->xmlDocument = NULL;
    $this->xmlXpath = NULL;
    $this->xmlContentHash = NULL;
    $this->xmlTestContent = NULL;
  }

  /**
   * Get the response content, or the content a fixture step set in its place.
   *
   * @return string
   *   The content set by a fixture step, or the live page content.
   */
  public function xmlGetContent(): string {
    return $this->xmlTestContent ?? (string) $this->getSession()->getPage()->getContent();
  }

  /**
   * Parse XML content without altering the cached document.
   *
   * @param string $content
   *   The XML content to parse.
   *
   * @return array{loaded: bool, errors: array<int, \LibXMLError>}
   *   Whether the content parsed into a document, and the errors libxml raised
   *   while parsing it. A document can parse and still raise errors.
   */
  public function xmlParse(string $content): array {
    $document = new \DOMDocument();

    libxml_clear_errors();
    $is_loaded = (bool) @$document->loadXML($content);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    return ['loaded' => $is_loaded, 'errors' => $errors];
  }

  /**
   * Load XML content into the document and XPath.
   *
   * @param string $content
   *   The XML content to load.
   *
   * @throws \RuntimeException
   *   If the XML cannot be loaded.
   */
  protected function xmlLoadDocument(string $content): void {
    $this->xmlDocument = new \DOMDocument();

    libxml_clear_errors();
    $is_loaded = @$this->xmlDocument->loadXML($content);

    if (!$is_loaded) {
      $errors = libxml_get_errors();
      throw new \RuntimeException(sprintf('Failed to load XML. Errors: %s.', $this->xmlFormatErrors($errors)));
    }

    $this->xmlXpath = new \DOMXPath($this->xmlDocument);

    $namespaces = $this->xmlExtractNamespaces();
    foreach ($namespaces as $prefix => $uri) {
      if (is_string($prefix) && $prefix !== '') {
        $this->xmlXpath->registerNamespace($prefix, $uri);
      }
    }
  }

  /**
   * Ensure that an XML document is loaded.
   *
   * Reloads the document if the page content has changed since last load.
   *
   * @throws \RuntimeException
   *   If the content cannot be loaded as XML.
   */
  protected function xmlEnsureDocument(): void {
    if ($this->xmlTestContent !== NULL) {
      if ($this->xmlDocument === NULL) {
        $this->xmlLoadDocument($this->xmlTestContent);
      }
      return;
    }

    $content = $this->getSession()->getPage()->getContent();
    $content_hash = md5((string) $content);

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

    $query = '//namespace::*';
    $nodes = $xpath->query($query);

    if ($nodes !== FALSE) {
      foreach ($nodes as $node) {
        $prefix = $node->localName;
        $uri = $node->nodeValue;

        // Skip the reserved namespace bound to the 'xml' prefix, which every
        // document declares implicitly.
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
   * Assert that the response validates against an XSD schema.
   *
   * @param string $schema
   *   The XSD schema source.
   */
  public function xmlAssertResponseMatchesXsd(string $schema): void {
    $this->xmlEnsureDocument();

    libxml_clear_errors();
    $is_valid = @$this->xmlDocument->schemaValidateSource($schema);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if (!$is_valid) {
      throw new ExpectationException(sprintf('The response does not match the XSD schema: %s.', $this->xmlFormatErrors($errors)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the response validates against a RelaxNG schema.
   *
   * @param string $schema
   *   The RelaxNG schema source.
   */
  public function xmlAssertResponseMatchesRelaxNg(string $schema): void {
    $this->xmlEnsureDocument();

    libxml_clear_errors();
    $is_valid = @$this->xmlDocument->relaxNGValidateSource($schema);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if (!$is_valid) {
      throw new ExpectationException(sprintf('The response does not match the RelaxNG schema: %s.', $this->xmlFormatErrors($errors)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the response validates against a DTD.
   *
   * The DTD is embedded as an internal subset and the response is reloaded
   * with validation enabled.
   *
   * External references are not resolved during validation, so a `SYSTEM`
   * entity in the DTD is loaded from neither a local path nor the network.
   * Validation fails with a resolver error if a DTD references one.
   *
   * DTDs are namespace-unaware, so a namespaced response is validated verbatim
   * and its `xmlns` attributes must be declared in the DTD. This matches
   * native DTD validation semantics.
   *
   * @param string $dtd
   *   The DTD source (element, attribute and entity declarations).
   */
  public function xmlAssertResponseMatchesDtd(string $dtd): void {
    $this->xmlEnsureDocument();

    $root = $this->xmlDocument->documentElement;
    if (!$root instanceof \DOMElement) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('The response has no root element to validate against the DTD.');
      // @codeCoverageIgnoreEnd
    }

    $body = $this->xmlDocument->saveXML($root);
    if ($body === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('Failed to serialize the response for DTD validation.');
      // @codeCoverageIgnoreEnd
    }

    $combined = sprintf("<?xml version=\"1.0\"?>\n<!DOCTYPE %s [\n%s\n]>\n%s", $root->nodeName, $dtd, $body);

    $document = new \DOMDocument();
    libxml_clear_errors();

    // A SYSTEM entity declared in the DTD is dereferenced while validating, so
    // the loader returns NULL for every external reference. LIBXML_NONET is
    // passed as well, but on its own it blocks only network references.
    $original_loader = function_exists('libxml_get_external_entity_loader') ? libxml_get_external_entity_loader() : NULL;
    libxml_set_external_entity_loader(static fn(): null => NULL);

    try {
      $is_loaded = @$document->loadXML($combined, LIBXML_DTDVALID | LIBXML_NONET);
      $errors = libxml_get_errors();
    }
    finally {
      libxml_set_external_entity_loader($original_loader);
      libxml_clear_errors();
    }

    if (!$is_loaded || $errors !== []) {
      throw new ExpectationException(sprintf('The response does not match the DTD: %s.', $this->xmlFormatErrors($errors)), $this->getSession()->getDriver());
    }
  }

  /**
   * Get the channel element of an RSS 2.0 response.
   *
   * @return \DOMElement
   *   The single "channel" element of the "rss" root.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the root is not an "rss" element with a "version" of "2.0", or it
   *   does not hold exactly 1 "channel" element.
   */
  public function xmlGetRssChannel(): \DOMElement {
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

    return $channels[0];
  }

  /**
   * Get the feed element of an Atom response.
   *
   * @return \DOMElement
   *   The "feed" root in the Atom namespace.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the root is not a "feed" element in the Atom namespace.
   */
  public function xmlGetAtomFeed(): \DOMElement {
    $this->xmlEnsureDocument();

    $root = $this->xmlDocument->documentElement;
    if (!$root instanceof \DOMElement || $root->localName !== 'feed' || $root->namespaceURI !== self::XML_ATOM_NAMESPACE) {
      throw new ExpectationException('The response is not a valid Atom feed: the root element must be "feed" in the Atom namespace.', $this->getSession()->getDriver());
    }

    return $root;
  }

  /**
   * Find the first required child element that 1 of the elements lacks.
   *
   * @param array<int, \DOMElement> $elements
   *   The elements to check, in order.
   * @param array<int, string> $names
   *   The local names of the required direct child elements, in order.
   * @param string|null $namespace
   *   The namespace URI of the child elements, or NULL to match any.
   *
   * @return string|null
   *   The first name an element has no direct child element for, or NULL
   *   when every element has them all.
   */
  public function xmlFindMissingChildElement(array $elements, array $names, ?string $namespace = NULL): ?string {
    foreach ($elements as $element) {
      foreach ($names as $name) {
        if ($this->xmlDirectChildElements($element, $name, $namespace) === []) {
          return $name;
        }
      }
    }

    return NULL;
  }

  /**
   * Count the direct child elements of a node.
   *
   * @param \DOMNode $node
   *   The parent node.
   *
   * @return int
   *   The number of direct children that are elements.
   */
  protected function xmlCountChildElements(\DOMNode $node): int {
    $count = 0;

    foreach ($node->childNodes as $child) {
      if ($child->nodeType === XML_ELEMENT_NODE) {
        $count++;
      }
    }

    return $count;
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

  /**
   * Return the first node matching an XPath expression.
   *
   * @param string $element
   *   The XPath expression.
   *
   * @return \DOMNode
   *   The first matching node.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   If no node matches the expression.
   */
  public function xmlGetFirstNode(string $element): \DOMNode {
    $this->xmlEnsureDocument();

    $nodes = $this->xmlXpath->query($element);
    if ($nodes === FALSE || $nodes->length === 0) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'XML element', 'xpath', $element);
    }

    $node = $nodes->item(0);
    if (!$node instanceof \DOMNode) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('The XML element "%s" is not a valid node.', $element));
      // @codeCoverageIgnoreEnd
    }

    return $node;
  }

}
