<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Steps\Web\MetatagTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests for MetatagTrait.
 */
#[CoversTrait(MetatagTrait::class)]
class MetatagTraitTest extends UnitTestCase {

  public function testFetchUrlReturnsTheBody(): void {
    $object = new MetatagTraitTestImplementation(new MockResponse('<html lang="de"></html>'));

    $this->assertSame('<html lang="de"></html>', $object->metatagFetchUrl('http://example.com/de'));
    $this->assertSame(['timeout' => 30], $object->detachedOptions);
  }

  /**
   * Tests that a failed fetch names the alternate page.
   *
   * @param \Symfony\Component\HttpClient\Response\MockResponse $response
   *   The response the alternate page answers with.
   * @param string $expected_message
   *   The message the fetch is expected to fail with.
   */
  #[DataProvider('dataProviderFetchUrlFailureNamesThePage')]
  public function testFetchUrlFailureNamesThePage(MockResponse $response, string $expected_message): void {
    $object = new MetatagTraitTestImplementation($response);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $object->metatagFetchUrl('http://example.com/de');
  }

  public static function dataProviderFetchUrlFailureNamesThePage(): \Iterator {
    yield 'a missing page' => [new MockResponse('Not found', ['http_code' => 404]), 'The hreflang alternate page "http://example.com/de" returned HTTP status 404.'];
    yield 'an unreachable host' => [new MockResponse('', ['error' => 'Could not resolve host']), 'Failed to fetch the hreflang alternate page "http://example.com/de": Could not resolve host.'];
  }

  /**
   * Tests extracting the top-level schema.org items of a JSON-LD block.
   *
   * @param array<int|string, mixed> $data
   *   The decoded block.
   * @param array<int, array<int|string, mixed>> $expected
   *   The expected items.
   */
  #[DataProvider('dataProviderExtractJsonLdItems')]
  public function testExtractJsonLdItems(array $data, array $expected): void {
    $object = new MetatagTraitTestImplementation(new MockResponse(''));

    $this->assertSame($expected, $object->metatagExtractJsonLdItems($data));
  }

  public static function dataProviderExtractJsonLdItems(): \Iterator {
    $website = ['@type' => 'WebSite', 'name' => 'Example'];
    $person = ['@type' => 'Person', 'name' => 'Jane'];

    yield 'an object' => [['@context' => 'https://schema.org'] + $website, [['@context' => 'https://schema.org'] + $website]];
    yield 'an object of another vocabulary' => [['@context' => 'https://example.com'] + $website, []];
    yield 'an object without a context' => [$website, []];
    yield 'a graph inheriting the context' => [['@context' => 'https://schema.org', '@graph' => [$website, $person]], [$website, $person]];
    yield 'a graph of another vocabulary' => [['@context' => 'https://example.com', '@graph' => [$website]], []];
    yield 'a graph member with its own context' => [['@context' => 'https://example.com', '@graph' => [['@context' => 'https://schema.org'] + $person, $website]], [['@context' => 'https://schema.org'] + $person]];
    yield 'a list of objects' => [[['@context' => 'https://schema.org'] + $website, ['@context' => 'https://example.com'] + $person], [['@context' => 'https://schema.org'] + $website]];
    yield 'a list skipping scalars' => [['text', 1, ['@context' => 'https://schema.org'] + $person], [['@context' => 'https://schema.org'] + $person]];
    yield 'a nested list' => [[[['@context' => 'https://schema.org'] + $person]], [['@context' => 'https://schema.org'] + $person]];
    yield 'an empty list' => [[], []];
    yield 'a graph that is not a list' => [['@context' => 'https://schema.org', '@graph' => 'text'], [['@context' => 'https://schema.org', '@graph' => 'text']]];
  }

  /**
   * Tests detecting a schema.org JSON-LD context.
   *
   * @param mixed $context
   *   The context.
   * @param bool $expected
   *   Whether the context names schema.org.
   */
  #[DataProvider('dataProviderIsSchemaOrgContext')]
  public function testIsSchemaOrgContext(mixed $context, bool $expected): void {
    $object = new MetatagTraitTestImplementation(new MockResponse(''));

    $this->assertSame($expected, $object->metatagIsSchemaOrgContext($context));
  }

  public static function dataProviderIsSchemaOrgContext(): \Iterator {
    yield 'https' => ['https://schema.org', TRUE];
    yield 'http with a trailing slash' => ['http://schema.org/', TRUE];
    yield 'uppercase' => ['HTTPS://SCHEMA.ORG', TRUE];
    yield 'a path' => ['https://schema.org/Person', FALSE];
    yield 'another host' => ['https://example.com', FALSE];
    yield 'a list naming schema.org' => [['https://example.com', 'https://schema.org'], TRUE];
    yield 'a list not naming schema.org' => [['https://example.com'], FALSE];
    yield 'a list with a vocabulary object' => [[['@vocab' => 'https://schema.org/']], TRUE];
    yield 'a vocabulary object' => [['@vocab' => 'https://schema.org/'], TRUE];
    yield 'an object without a vocabulary' => [['name' => 'https://schema.org/name'], FALSE];
    yield 'null' => [NULL, FALSE];
    yield 'a number' => [1, FALSE];
  }

  /**
   * Tests reading the types of a JSON-LD item.
   *
   * @param array<int|string, mixed> $item
   *   The decoded item.
   * @param array<int, string> $expected
   *   The expected types.
   */
  #[DataProvider('dataProviderGetJsonLdItemTypes')]
  public function testGetJsonLdItemTypes(array $item, array $expected): void {
    $object = new MetatagTraitTestImplementation(new MockResponse(''));

    $this->assertSame($expected, $object->metatagGetJsonLdItemTypes($item));
  }

  public static function dataProviderGetJsonLdItemTypes(): \Iterator {
    yield 'a string' => [['@type' => 'Person'], ['Person']];
    yield 'a list' => [['@type' => ['Article', 'NewsArticle']], ['Article', 'NewsArticle']];
    yield 'a list with a non-string' => [['@type' => ['Article', 1]], ['Article']];
    yield 'no type' => [['name' => 'Jane'], []];
    yield 'a non-string' => [['@type' => 1], []];
  }

  /**
   * Tests comparing a JSON-LD item against expected property values.
   *
   * @param array<string, string> $properties
   *   The expected values, keyed by property.
   * @param array<int, string> $expected
   *   The expected mismatches.
   */
  #[DataProvider('dataProviderCompareJsonLdItem')]
  public function testCompareJsonLdItem(array $properties, array $expected): void {
    $object = new MetatagTraitTestImplementation(new MockResponse(''));
    $item = [
      '@type' => 'PodcastEpisode',
      'url' => 'http://example.com/episode',
      'name' => 'Episode one',
      'blank' => '  ',
      'nothing' => NULL,
      'none' => [],
      'free' => FALSE,
      'number' => 3,
      'keywords' => ['behat', 'testing'],
      'partOfSeries' => ['name' => 'Radio'],
    ];

    $this->assertSame($expected, $object->metatagCompareJsonLdItem($item, $properties, 'http://example.com/episode'));
  }

  public static function dataProviderCompareJsonLdItem(): \Iterator {
    yield 'equal values' => [['@type' => 'PodcastEpisode', 'name' => 'Episode one', 'number' => '3', 'free' => 'false'], []];
    yield 'the page URL' => [['url' => '[current_url]'], []];
    yield 'a nested property' => [['partOfSeries.name' => 'Radio', 'keywords.0' => 'behat'], []];
    yield 'non-empty values' => [['name' => '[not_empty]', 'free' => '[not_empty]', 'partOfSeries' => '[not_empty]'], []];
    yield 'a different value' => [['name' => 'Episode two'], ['the property "name" has the value "Episode one", but it should have the value "Episode two".']];
    yield 'a different page URL' => [['name' => '[current_url]'], ['the property "name" has the value "Episode one", but it should have the value "http://example.com/episode".']];
    yield 'a missing property' => [['duration' => 'PT1H'], ['the property "duration" does not exist.']];
    yield 'a path through a scalar' => [['name.first' => 'Episode'], ['the property "name.first" does not exist.']];
    yield 'an index out of range' => [['keywords.2' => 'behat'], ['the property "keywords.2" does not exist.']];
    yield 'empty values' => [['blank' => '[not_empty]', 'nothing' => '[not_empty]', 'none' => '[not_empty]'], ['the property "blank" is empty, but it should not be.', 'the property "nothing" is empty, but it should not be.', 'the property "none" is empty, but it should not be.']];
    yield 'an object compared to a value' => [['partOfSeries' => 'Radio'], ['the property "partOfSeries" holds an object or a list, but it should have the value "Radio".']];
    yield 'a null' => [['nothing' => 'null'], []];
    yield 'a null compared to a value' => [['nothing' => 'Radio'], ['the property "nothing" has the value "null", but it should have the value "Radio".']];
  }

  /**
   * Tests parsing a properties table.
   */
  public function testParseJsonLdPropertyTable(): void {
    $object = new MetatagTraitTestImplementation(new MockResponse(''));
    $table = new TableNode([['property', 'value'], [' url ', '[current_url]'], ['name', '']]);

    $this->assertSame(['url' => '[current_url]', 'name' => ''], $object->metatagParseJsonLdPropertyTable($table));
  }

  /**
   * Tests that a malformed properties table is rejected.
   *
   * @param array<int, list<string>> $rows
   *   The table rows, the header first.
   * @param string $expected_message
   *   The message the parse is expected to fail with.
   */
  #[DataProvider('dataProviderParseJsonLdPropertyTableRejectsMalformedTable')]
  public function testParseJsonLdPropertyTableRejectsMalformedTable(array $rows, string $expected_message): void {
    $object = new MetatagTraitTestImplementation(new MockResponse(''));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $object->metatagParseJsonLdPropertyTable(new TableNode($rows));
  }

  public static function dataProviderParseJsonLdPropertyTableRejectsMalformedTable(): \Iterator {
    yield 'no property column' => [[['name', 'value'], ['url', 'x']], 'The properties table must contain "property" and "value" columns.'];
    yield 'no value column' => [[['property', 'expected'], ['url', 'x']], 'The properties table must contain "property" and "value" columns.'];
    yield 'an empty property' => [[['property', 'value'], [' ', 'x']], 'The properties table has a row with an empty property.'];
    yield 'a repeated property' => [[['property', 'value'], ['url', 'x'], ['url', 'y']], 'The property "url" appears more than once in the properties table.'];
    yield 'no rows' => [[['property', 'value']], 'The properties table must list at least 1 property.'];
  }

}

/**
 * Test implementation of MetatagTrait.
 */
class MetatagTraitTestImplementation extends WebRawContext {

  use MetatagTrait {
    metatagFetchUrl as public;
    metatagExtractJsonLdItems as public;
    metatagIsSchemaOrgContext as public;
    metatagGetJsonLdItemTypes as public;
    metatagCompareJsonLdItem as public;
    metatagParseJsonLdPropertyTable as public;
  }

  /**
   * The options the trait passed to httpDetachedClient().
   *
   * @var array<string, mixed>
   */
  public array $detachedOptions = [];

  /**
   * Constructs a MetatagTraitTestImplementation object.
   *
   * @param \Symfony\Component\HttpClient\Response\MockResponse $response
   *   The response the detached browser answers with.
   */
  public function __construct(protected MockResponse $response) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public function httpDetachedClient(array $options = []): AbstractBrowser {
    $this->detachedOptions = $options;

    return new HttpBrowser(new MockHttpClient($this->response));
  }

}
