<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Selector\Xpath\Escaper;
use Behat\Step\Then;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Assert `<meta>` tags and head/SEO markup in page markup.
 *
 * - Assert presence and content of meta tags.
 * - Verify meta tag content is free of HTML markup.
 * - Assert canonical URL, robots directives and indexability.
 * - Assert hreflang alternates are valid and reciprocal.
 * - Assert Open Graph and Twitter Card completeness.
 * - Assert schema.org JSON-LD items and their properties.
 *
 * @title Meta tags
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait MetatagTrait {

  use StringTrait;

  /**
   * The JSON-LD property value that stands for the URL of the page.
   */
  protected const string METATAG_JSON_LD_CURRENT_URL = '[current_url]';

  /**
   * The JSON-LD property value that asserts the property is not empty.
   */
  protected const string METATAG_JSON_LD_NOT_EMPTY = '[not_empty]';

  /**
   * Assert that a meta tag with specific attributes and values exists.
   *
   * @code
   * Then the meta tag with the following attributes should exist:
   *   | name    | description          |
   *   | content | My page description  |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the meta tag with the following attributes should exist:')]
  public function metatagAssertExistsWithAttributes(TableNode $table): void {
    $attributes = $table->getRowsHash();

    if (!$this->metatagFindMetaWithAttributes($attributes) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'meta tag', 'attributes', (string) json_encode($attributes));
    }
  }

  /**
   * Assert that a meta tag with specific attributes and values does not exist.
   *
   * @code
   * Then the meta tag with the following attributes should not exist:
   *   | name    | nonexistent          |
   *   | content | Some content         |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the meta tag with the following attributes should not exist:')]
  public function metatagAssertNotExistsWithAttributes(TableNode $table): void {
    $attributes = $table->getRowsHash();

    if ($this->metatagFindMetaWithAttributes($attributes) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The meta tag with the attributes "%s" exists, but it should not.', json_encode($attributes)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert a meta tag does not contain HTML tags.
   *
   * Looks up the meta tag by either "name" or "property" attribute and checks
   * that the "content" attribute value is free of HTML markup.
   *
   * @code
   * Then the meta tag "og:description" should not contain any HTML tags
   * Then the meta tag "description" should not contain any HTML tags
   * @endcode
   *
   * @actor:system
   */
  #[Then('the meta tag :name should not contain any HTML tags')]
  public function metatagAssertNotContainsHtml(string $name): void {
    $meta_tag = $this->metatagFindMeta($name);

    if ($meta_tag === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'meta tag', 'name|property', $name);
    }

    $content = (string) $meta_tag->getAttribute('content');

    if ($content !== strip_tags($content)) {
      throw new ExpectationException(sprintf('The meta tag "%s" contains HTML tags: %s.', $name, $content), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the canonical URL equals a value.
   *
   * The actual and expected URLs are both resolved to absolute form against
   * the origin of the Mink base URL. A relative expected value therefore
   * matches an absolute canonical href for the same page.
   *
   * @code
   * Then the canonical URL should be "https://example.com/about"
   * Then the canonical URL should be "/about"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the canonical URL should be :url')]
  public function metatagAssertCanonicalEquals(string $url): void {
    $href = $this->metatagFindCanonicalHref();

    if ($href === NULL || $href === '') {
      throw new ExpectationException('The canonical URL is not set.', $this->getSession()->getDriver());
    }

    if ($this->metatagResolveUrl($href) !== $this->metatagResolveUrl($url)) {
      throw new ExpectationException(sprintf('The canonical URL is "%s", but expected "%s".', $href, $url), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the canonical URL is present.
   *
   * @code
   * Then the canonical URL should exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the canonical URL should exist')]
  public function metatagAssertCanonicalExists(): void {
    $href = $this->metatagFindCanonicalHref();

    if ($href === NULL || $href === '') {
      throw new ExpectationException('The canonical URL is not set.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the canonical URL is absent.
   *
   * @code
   * Then the canonical URL should not exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the canonical URL should not exist')]
  public function metatagAssertCanonicalNotExists(): void {
    $href = $this->metatagFindCanonicalHref();

    if ($href !== NULL && $href !== '') {
      throw new ExpectationException(sprintf('The canonical URL should not be set, but found "%s".', $href), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the page is indexable.
   *
   * The page is indexable when the robots meta tag has no "noindex" (or "none")
   * directive and the "X-Robots-Tag" response header has no "noindex" directive.
   *
   * @code
   * Then the page should be indexable
   * @endcode
   *
   * @actor:system
   */
  #[Then('the page should be indexable')]
  public function metatagAssertIndexable(): void {
    if (!$this->metatagIsIndexable()) {
      throw new ExpectationException('The page is not indexable: a "noindex" directive is present in the robots meta tag or the "X-Robots-Tag" header.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the page is not indexable.
   *
   * @code
   * Then the page should not be indexable
   * @endcode
   *
   * @actor:system
   */
  #[Then('the page should not be indexable')]
  public function metatagAssertNotIndexable(): void {
    if ($this->metatagIsIndexable()) {
      throw new ExpectationException('The page is indexable, but it should not be: no "noindex" directive found in the robots meta tag or the "X-Robots-Tag" header.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the robots meta tag contains a directive.
   *
   * Directives are matched as whole, case-insensitive tokens, so a request for
   * "follow" never matches a "nofollow" directive.
   *
   * @code
   * Then the robots meta tag should contain "noindex"
   * Then the robots meta tag should contain "nofollow"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the robots meta tag should contain :directive')]
  public function metatagAssertRobotsContains(string $directive): void {
    $directives = $this->metatagGetRobotsDirectives();

    if (!in_array(strtolower(trim($directive)), $directives, TRUE)) {
      throw new ExpectationException(sprintf('The robots meta tag does not contain the "%s" directive. Found: %s.', $directive, $directives === [] ? '(none)' : implode(', ', $directives)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the robots meta tag does not contain a directive.
   *
   * @code
   * Then the robots meta tag should not contain "noindex"
   * Then the robots meta tag should not contain "nofollow"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the robots meta tag should not contain :directive')]
  public function metatagAssertRobotsNotContains(string $directive): void {
    $directives = $this->metatagGetRobotsDirectives();

    if (in_array(strtolower(trim($directive)), $directives, TRUE)) {
      throw new ExpectationException(sprintf('The robots meta tag contains the "%s" directive, but it should not.', $directive), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert hreflang alternates are valid.
   *
   * Checks, without fetching any alternate page, that at least 1 hreflang
   * alternate exists and that a self-referencing alternate for the current
   * URL is present. Every hreflang value must be a well-formed language code
   * (or "x-default").
   *
   * @code
   * Then the hreflang alternates should be valid
   * @endcode
   *
   * @actor:system
   */
  #[Then('the hreflang alternates should be valid')]
  public function metatagAssertHreflangValid(): void {
    $this->metatagAssertHreflangAlternatesValid($this->metatagGetHreflangAlternates());
  }

  /**
   * Assert hreflang alternates have reciprocal return links.
   *
   * Fetches each non-"x-default" alternate page (other than the current page)
   * and asserts that it links back to the current URL.
   *
   * @code
   * Then the hreflang alternates should have reciprocal return links
   * @endcode
   *
   * @actor:system
   */
  #[Then('the hreflang alternates should have reciprocal return links')]
  public function metatagAssertHreflangReciprocal(): void {
    $alternates = $this->metatagGetHreflangAlternates();

    $this->metatagAssertHreflangAlternatesValid($alternates);
    $this->metatagAssertHreflangAlternatesReciprocal($alternates);
  }

  /**
   * Assert the required Open Graph meta tags are present and non-empty.
   *
   * The required set defaults to the Open Graph basics and can be overridden by
   * the consuming context via metatagGetRequiredOpenGraphTags().
   *
   * @code
   * Then the Open Graph tags should be valid
   * @endcode
   *
   * @actor:system
   */
  #[Then('the Open Graph tags should be valid')]
  public function metatagAssertOpenGraphValid(): void {
    $this->metatagAssertMetaSetExists($this->metatagGetRequiredOpenGraphTags(), 'Open Graph');
  }

  /**
   * Assert the listed Open Graph meta tags are present and non-empty.
   *
   * @code
   * Then the following Open Graph tags should exist:
   *   | og:title |
   *   | og:image |
   *   | og:url   |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the following Open Graph tags should exist:')]
  public function metatagAssertOpenGraphTagsExist(TableNode $table): void {
    $this->metatagAssertMetaSetExists($this->metatagTablePropertyNames($table), 'Open Graph');
  }

  /**
   * Assert the required Twitter Card meta tags are present and non-empty.
   *
   * The required set defaults to the Twitter Card basics and can be overridden
   * by the consuming context via metatagGetRequiredTwitterCardTags().
   *
   * @code
   * Then the Twitter Card tags should be valid
   * @endcode
   *
   * @actor:system
   */
  #[Then('the Twitter Card tags should be valid')]
  public function metatagAssertTwitterCardValid(): void {
    $this->metatagAssertMetaSetExists($this->metatagGetRequiredTwitterCardTags(), 'Twitter Card');
  }

  /**
   * Assert the listed Twitter Card meta tags are present and non-empty.
   *
   * @code
   * Then the following Twitter Card tags should exist:
   *   | twitter:card  |
   *   | twitter:title |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the following Twitter Card tags should exist:')]
  public function metatagAssertTwitterCardTagsExist(TableNode $table): void {
    $this->metatagAssertMetaSetExists($this->metatagTablePropertyNames($table), 'Twitter Card');
  }

  /**
   * Assert that a schema.org JSON-LD item of a type exists on the page.
   *
   * Every `<script type="application/ld+json">` block on the page is read.
   * An item counts when it sits at the top level of a block, directly or in
   * its `@graph`, and its `@context` names schema.org.
   *
   * @code
   * Then a "WebSite" JSON-LD item should exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('a :schema_type JSON-LD item should exist')]
  public function metatagAssertJsonLdItemExists(string $schema_type): void {
    if ($this->metatagGetJsonLdItems($schema_type) === []) {
      throw new ExpectationException($this->metatagFormatJsonLdItemNotFound($schema_type), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that no schema.org JSON-LD item of a type exists on the page.
   *
   * @code
   * Then a "NewsArticle" JSON-LD item should not exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('a :schema_type JSON-LD item should not exist')]
  public function metatagAssertJsonLdItemNotExists(string $schema_type): void {
    if ($this->metatagGetJsonLdItems($schema_type) !== []) {
      throw new ExpectationException(sprintf('The schema.org JSON-LD item of the type "%s" exists on the page, but it should not.', $schema_type), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a schema.org JSON-LD item of a type has the listed properties.
   *
   * The table lists a property and its expected value per row. A dot path
   * reaches a nested property, and a numeric segment indexes a list, as in
   * `partOfSeries.name` or `author.0.name`. A plain value asserts equality,
   * `[current_url]` stands for the URL of the page and `[not_empty]` asserts
   * the property exists and is not empty.
   *
   * When several items have the type, 1 of them has to match every row.
   *
   * @code
   * Then a "PodcastEpisode" JSON-LD item with the following properties should exist:
   *   | property          | value         |
   *   | url               | [current_url] |
   *   | name              | [not_empty]   |
   *   | partOfSeries.name | My podcast    |
   * @endcode
   *
   * @actor:system
   */
  #[Then('a :schema_type JSON-LD item with the following properties should exist:')]
  public function metatagAssertJsonLdItemExistsWithProperties(string $schema_type, TableNode $table): void {
    $this->metatagAssertJsonLdItemHasProperties($schema_type, $this->metatagParseJsonLdPropertyTable($table));
  }

  /**
   * Find a meta tag by its "name" or "property" attribute.
   *
   * @param string $meta_name
   *   The meta tag name or property.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The meta element, or NULL when not found.
   */
  public function metatagFindMeta(string $meta_name): ?NodeElement {
    $escaped_name = (new Escaper())->escapeLiteral($meta_name);

    return $this->getSession()->getPage()->find('xpath', sprintf('//meta[@name=%s or @property=%s]', $escaped_name, $escaped_name));
  }

  /**
   * Find the content of a meta tag by its "name" or "property" attribute.
   *
   * @param string $meta_name
   *   The meta tag name or property.
   *
   * @return string|null
   *   The content attribute value, or NULL when the meta tag is not found.
   */
  public function metatagFindMetaContent(string $meta_name): ?string {
    $meta = $this->metatagFindMeta($meta_name);

    return $meta === NULL ? NULL : (string) $meta->getAttribute('content');
  }

  /**
   * Find the canonical URL href.
   *
   * @return string|null
   *   The canonical href, or NULL when no canonical link is present.
   */
  public function metatagFindCanonicalHref(): ?string {
    $link = $this->getSession()->getPage()->find('xpath', '//link[@rel="canonical"]');

    return $link === NULL ? NULL : (string) $link->getAttribute('href');
  }

  /**
   * Get the robots meta tag directives as lower-cased tokens.
   *
   * @return array<int, string>
   *   The directive tokens, or an empty array when no robots meta tag exists.
   */
  public function metatagGetRobotsDirectives(): array {
    $content = $this->metatagFindMetaContent('robots');

    if ($content === NULL || trim($content) === '') {
      return [];
    }

    return array_values(array_filter($this->stringSplitCommaSeparated(strtolower($content)), static fn(string $directive): bool => $directive !== ''));
  }

  /**
   * Determine whether the current page is indexable.
   *
   * @return bool
   *   TRUE when the robots meta tag carries neither a "noindex" nor a "none"
   *   directive and the X-Robots-Tag header carries no "noindex" directive.
   */
  public function metatagIsIndexable(): bool {
    $directives = $this->metatagGetRobotsDirectives();

    if (in_array('noindex', $directives, TRUE) || in_array('none', $directives, TRUE)) {
      return FALSE;
    }

    return !$this->metatagResponseHasNoindexHeader();
  }

  /**
   * Determine whether the X-Robots-Tag response header carries "noindex".
   *
   * @return bool
   *   TRUE when any X-Robots-Tag header value includes a "noindex" directive.
   */
  public function metatagResponseHasNoindexHeader(): bool {
    $headers = $this->getSession()->getResponseHeaders();

    foreach ($headers as $name => $values) {
      if (strtolower((string) $name) !== 'x-robots-tag') {
        continue;
      }

      foreach ((array) $values as $value) {
        if (preg_match('/\bnoindex\b/i', (string) $value) === 1) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Find the first meta tag carrying every given attribute value.
   *
   * @param array<int|string, mixed> $attributes
   *   The expected attribute values, keyed by attribute name.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The meta tag, or NULL when no meta tag carries every value.
   */
  public function metatagFindMetaWithAttributes(array $attributes): ?NodeElement {
    foreach ($this->getSession()->getPage()->findAll('css', 'meta') as $element) {
      $all_attributes_matched = TRUE;

      foreach ($attributes as $attribute => $value) {
        if ($element->getAttribute((string) $attribute) !== $value) {
          $all_attributes_matched = FALSE;
          break;
        }
      }

      if ($all_attributes_matched) {
        return $element;
      }
    }

    return NULL;
  }

  /**
   * Assert that hreflang alternates are valid.
   *
   * At least 1 alternate exists, each has an href and a well-formed language
   * code (or "x-default"), and 1 of them references the current URL.
   *
   * @param array<int, array{hreflang: string, href: string}> $alternates
   *   The hreflang alternates, as metatagGetHreflangAlternates() returns them.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the alternates are not valid.
   */
  public function metatagAssertHreflangAlternatesValid(array $alternates): void {
    if ($alternates === []) {
      throw new ExpectationException('No hreflang alternate links were found on the page.', $this->getSession()->getDriver());
    }

    foreach ($alternates as $alternate) {
      if ($alternate['href'] === '') {
        throw new ExpectationException(sprintf('The hreflang alternate for "%s" has an empty href.', $alternate['hreflang']), $this->getSession()->getDriver());
      }

      if (!$this->metatagIsValidHreflang($alternate['hreflang'])) {
        throw new ExpectationException(sprintf('The hreflang value "%s" is not a valid language code.', $alternate['hreflang']), $this->getSession()->getDriver());
      }
    }

    $current = $this->metatagResolveUrl($this->getSession()->getCurrentUrl());

    foreach ($alternates as $alternate) {
      if ($this->metatagResolveUrl($alternate['href']) === $current) {
        return;
      }
    }

    throw new ExpectationException(sprintf('No self-referencing hreflang alternate was found for the current URL "%s".', $current), $this->getSession()->getDriver());
  }

  /**
   * Assert that every hreflang alternate page links back to the current URL.
   *
   * Each alternate other than the current page and "x-default" is fetched.
   *
   * @param array<int, array{hreflang: string, href: string}> $alternates
   *   The hreflang alternates, as metatagGetHreflangAlternates() returns them.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When an alternate page does not link back.
   */
  public function metatagAssertHreflangAlternatesReciprocal(array $alternates): void {
    $current = $this->metatagResolveUrl($this->getSession()->getCurrentUrl());

    foreach ($alternates as $alternate) {
      $target = $this->metatagResolveUrl($alternate['href']);

      if ($target === $current || strtolower((string) $alternate['hreflang']) === 'x-default') {
        continue;
      }

      if (!$this->metatagHtmlLinksBackTo($this->metatagFetchUrl($target), $current, $target)) {
        throw new ExpectationException(sprintf('The hreflang alternate "%s" (%s) does not link back to the current URL "%s".', $alternate['hreflang'], $target, $current), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Get the hreflang alternates present on the current page.
   *
   * @return array<int, array{hreflang: string, href: string}>
   *   The hreflang alternates, each with its raw hreflang value and href.
   */
  public function metatagGetHreflangAlternates(): array {
    $alternates = [];

    foreach ($this->getSession()->getPage()->findAll('xpath', '//link[@rel="alternate"][@hreflang]') as $link) {
      $alternates[] = [
        'hreflang' => (string) $link->getAttribute('hreflang'),
        'href' => (string) $link->getAttribute('href'),
      ];
    }

    return $alternates;
  }

  /**
   * Determine whether a hreflang value is a well-formed language code.
   *
   * Accepts the special "x-default" value and BCP-47-style tags such as "en",
   * "en-US" and "zh-Hant". This is a structural check, not a lookup against the
   * ISO language and region registries.
   *
   * @param string $value
   *   The hreflang value to validate.
   *
   * @return bool
   *   TRUE when the value is a well-formed language code.
   */
  public function metatagIsValidHreflang(string $value): bool {
    if (strtolower($value) === 'x-default') {
      return TRUE;
    }

    return preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i', $value) === 1;
  }

  /**
   * Fetch a URL through the detached client, leaving the page untouched.
   *
   * The request carries the scenario's cookies and headers, so an alternate
   * page behind a login or basic auth is fetched with the scenario's access.
   *
   * @param string $url
   *   The absolute URL to fetch.
   *
   * @return string
   *   The response body.
   */
  protected function metatagFetchUrl(string $url): string {
    $browser = $this->httpDetachedClient(['timeout' => 30]);

    try {
      $browser->request('GET', $url);
    }
    catch (TransportExceptionInterface $exception) {
      throw new \RuntimeException(sprintf('Failed to fetch the hreflang alternate page "%s": %s.', $url, $exception->getMessage()), 0, $exception);
    }

    $response = $browser->getInternalResponse();
    $status = $response->getStatusCode();

    if ($status >= 400) {
      throw new \RuntimeException(sprintf('The hreflang alternate page "%s" returned HTTP status %d.', $url, $status));
    }

    return $response->getContent();
  }

  /**
   * Determine whether fetched HTML links back to a URL via hreflang.
   *
   * @param string $html
   *   The HTML markup to inspect.
   * @param string $url
   *   The absolute URL the markup should link back to.
   * @param string $base_url
   *   The URL of the fetched page, used to resolve its relative links.
   *
   * @return bool
   *   TRUE when a hreflang alternate resolves to the given URL.
   */
  protected function metatagHtmlLinksBackTo(string $html, string $url, string $base_url): bool {
    $original = libxml_use_internal_errors(TRUE);

    try {
      $document = new \DOMDocument();
      $document->loadHTML($html);
      libxml_clear_errors();

      $xpath = new \DOMXPath($document);
      $links = $xpath->query('//link[@rel="alternate"][@hreflang]');

      if ($links === FALSE) {
        // @codeCoverageIgnoreStart
        return FALSE;
        // @codeCoverageIgnoreEnd
      }

      foreach ($links as $link) {
        if ($link instanceof \DOMElement && $this->metatagResolveUrl($link->getAttribute('href'), $base_url) === $url) {
          return TRUE;
        }
      }

      return FALSE;
    }
    finally {
      libxml_use_internal_errors($original);
    }
  }

  /**
   * Resolve an absolute or root-relative URL against a base URL's origin.
   *
   * Absolute URLs are returned unchanged and root-relative URLs are resolved
   * against the base URL's origin. Document-relative URLs (such as "page.html"
   * or "../en") are resolved against the origin rather than the base path.
   *
   * Hreflang and canonical markup should use absolute or root-relative URLs.
   *
   * @param string $url
   *   The URL to resolve.
   * @param string|null $base
   *   The base URL whose origin relative URLs resolve against. Defaults to the
   *   Mink base URL.
   *
   * @return string
   *   The resolved absolute URL.
   */
  protected function metatagResolveUrl(string $url, ?string $base = NULL): string {
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
      return $url;
    }

    $base ??= (string) $this->getMinkParameter('base_url');

    $origin = (string) preg_replace('#^(https?://[^/]+).*$#i', '$1', $base);

    return rtrim($origin, '/') . '/' . ltrim($url, '/');
  }

  /**
   * Assert that a set of meta tags is present and non-empty.
   *
   * @param array<int, string> $names
   *   The meta tag names or properties that must be present.
   * @param string $label
   *   A human-readable label for the set, used in the failure message.
   */
  public function metatagAssertMetaSetExists(array $names, string $label): void {
    $missing = [];

    foreach ($names as $name) {
      $content = $this->metatagFindMetaContent($name);

      if ($content === NULL || trim($content) === '') {
        $missing[] = $name;
      }
    }

    if ($missing !== []) {
      throw new ExpectationException(sprintf('The following required %s meta tags are missing or empty: %s.', $label, implode(', ', $missing)), $this->getSession()->getDriver());
    }
  }

  /**
   * Extract meta tag names from the first column of a table.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table whose first column lists meta tag names or properties.
   *
   * @return array<int, string>
   *   The trimmed, non-empty meta tag names.
   */
  protected function metatagTablePropertyNames(TableNode $table): array {
    $names = [];

    foreach ($table->getRows() as $row) {
      $name = trim((string) ($row[0] ?? ''));

      if ($name !== '') {
        $names[] = $name;
      }
    }

    return $names;
  }

  /**
   * The Open Graph meta tags required by "the Open Graph tags should be valid".
   *
   * @return array<int, string>
   *   The required Open Graph property names.
   */
  public function metatagGetRequiredOpenGraphTags(): array {
    return ['og:title', 'og:type', 'og:image', 'og:url'];
  }

  /**
   * The Twitter Card tags required by "the Twitter Card tags should be valid".
   *
   * @return array<int, string>
   *   The required Twitter Card property names.
   */
  public function metatagGetRequiredTwitterCardTags(): array {
    return ['twitter:card', 'twitter:title', 'twitter:description'];
  }

  /**
   * Get the schema.org JSON-LD items on the page.
   *
   * Every `<script type="application/ld+json">` block is read in document
   * order. The items of a block are the members of its `@graph`, the members
   * of a list, or the block's object itself. An item counts when its own
   * `@context`, or the `@context` of its block, names schema.org.
   *
   * @param string|null $schema_type
   *   The `@type` an item must carry, or NULL for every item.
   *
   * @return array<int, array<int|string, mixed>>
   *   The decoded items, or an empty array when none matches.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When a block is not valid JSON or holds no object or list.
   */
  public function metatagGetJsonLdItems(?string $schema_type = NULL): array {
    $items = [];
    $scripts = $this->getSession()->getPage()->findAll('css', 'script[type="application/ld+json"]');

    foreach ($scripts as $index => $script) {
      $data = json_decode(trim($script->getHtml()), TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new ExpectationException(sprintf('The JSON-LD script at the position "%d" is not valid JSON: %s.', $index + 1, json_last_error_msg()), $this->getSession()->getDriver());
      }

      if (!is_array($data)) {
        throw new ExpectationException(sprintf('The JSON-LD script at the position "%d" holds no JSON object or list.', $index + 1), $this->getSession()->getDriver());
      }

      array_push($items, ...$this->metatagExtractJsonLdItems($data));
    }

    if ($schema_type === NULL) {
      return $items;
    }

    return array_values(array_filter($items, fn(array $item): bool => in_array($schema_type, $this->metatagGetJsonLdItemTypes($item), TRUE)));
  }

  /**
   * Assert that a schema.org JSON-LD item of a type has the given properties.
   *
   * The assertion passes when any item of the type matches every property.
   *
   * @param string $schema_type
   *   The `@type` of the item.
   * @param array<int|string, string> $properties
   *   The expected values, keyed by a property name or a dot path. A value of
   *   `[current_url]` stands for the URL of the page, and `[not_empty]`
   *   asserts the property exists and is not empty.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no item of the type exists, or none has every property.
   */
  public function metatagAssertJsonLdItemHasProperties(string $schema_type, array $properties): void {
    $items = $this->metatagGetJsonLdItems($schema_type);

    if ($items === []) {
      throw new ExpectationException($this->metatagFormatJsonLdItemNotFound($schema_type), $this->getSession()->getDriver());
    }

    $current_url = $this->getSession()->getCurrentUrl();
    $lines = [];

    foreach ($items as $index => $item) {
      $mismatches = $this->metatagCompareJsonLdItem($item, $properties, $current_url);

      if ($mismatches === []) {
        return;
      }

      foreach ($mismatches as $mismatch) {
        $lines[] = sprintf('- Item %d: %s', $index + 1, $mismatch);
      }
    }

    throw new ExpectationException(sprintf('No schema.org JSON-LD item of the type "%s" has the expected properties:', $schema_type) . PHP_EOL . implode(PHP_EOL, $lines), $this->getSession()->getDriver());
  }

  /**
   * Extract the top-level schema.org items from a decoded JSON-LD block.
   *
   * @param array<int|string, mixed> $data
   *   The decoded block, or a list or a graph inside it.
   * @param mixed $context
   *   The `@context` of the enclosing object, or NULL at the top of a block.
   *
   * @return array<int, array<int|string, mixed>>
   *   The items whose `@context` names schema.org.
   */
  protected function metatagExtractJsonLdItems(array $data, mixed $context = NULL): array {
    if (array_is_list($data)) {
      $items = [];

      foreach ($data as $member) {
        if (is_array($member)) {
          array_push($items, ...$this->metatagExtractJsonLdItems($member, $context));
        }
      }

      return $items;
    }

    $context = $data['@context'] ?? $context;

    if (isset($data['@graph']) && is_array($data['@graph'])) {
      return $this->metatagExtractJsonLdItems($data['@graph'], $context);
    }

    return $this->metatagIsSchemaOrgContext($context) ? [$data] : [];
  }

  /**
   * Determine whether a JSON-LD `@context` names schema.org.
   *
   * @param mixed $context
   *   A context URL, a list of contexts or a context object with `@vocab`.
   *
   * @return bool
   *   TRUE when the context, a member of it or its `@vocab` is the schema.org
   *   URL, over HTTP or HTTPS and with or without a trailing slash.
   */
  protected function metatagIsSchemaOrgContext(mixed $context): bool {
    if (is_string($context)) {
      return preg_match('#^https?://schema\.org/?$#i', $context) === 1;
    }

    if (!is_array($context)) {
      return FALSE;
    }

    if (!array_is_list($context)) {
      return $this->metatagIsSchemaOrgContext($context['@vocab'] ?? NULL);
    }

    foreach ($context as $member) {
      if ($this->metatagIsSchemaOrgContext($member)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Get the types a JSON-LD item declares.
   *
   * @param array<int|string, mixed> $item
   *   The decoded item.
   *
   * @return array<int, string>
   *   The values of `@type`, which may be a string or a list of strings.
   */
  protected function metatagGetJsonLdItemTypes(array $item): array {
    $types = $item['@type'] ?? [];

    return array_values(array_filter(is_array($types) ? $types : [$types], is_string(...)));
  }

  /**
   * Format the failure message for a missing JSON-LD item.
   *
   * @param string $schema_type
   *   The `@type` that no item carries.
   *
   * @return string
   *   The message, listing the types the page does carry.
   */
  protected function metatagFormatJsonLdItemNotFound(string $schema_type): string {
    $types = array_unique(array_merge(...array_map($this->metatagGetJsonLdItemTypes(...), $this->metatagGetJsonLdItems())));
    $quoted = array_map(static fn(string $type): string => sprintf('"%s"', $type), $types);

    return sprintf('No schema.org JSON-LD item of the type "%s" was found on the page. Found types: %s.', $schema_type, $quoted === [] ? '(none)' : implode(', ', $quoted));
  }

  /**
   * Compare a JSON-LD item against expected property values.
   *
   * @param array<int|string, mixed> $item
   *   The decoded item.
   * @param array<int|string, string> $properties
   *   The expected values, keyed by a property name or a dot path.
   * @param string $current_url
   *   The URL `[current_url]` stands for.
   *
   * @return array<int, string>
   *   A description of each property that does not match, or an empty array
   *   when every property matches.
   */
  protected function metatagCompareJsonLdItem(array $item, array $properties, string $current_url): array {
    $mismatches = [];

    foreach ($properties as $path => $expected) {
      $mismatch = $this->metatagCompareJsonLdProperty($item, (string) $path, $expected === self::METATAG_JSON_LD_CURRENT_URL ? $current_url : $expected);

      if ($mismatch !== NULL) {
        $mismatches[] = $mismatch;
      }
    }

    return $mismatches;
  }

  /**
   * Compare 1 property of a JSON-LD item against an expected value.
   *
   * @param array<int|string, mixed> $item
   *   The decoded item.
   * @param string $path
   *   The property name, or a dot path whose numeric segments index a list.
   * @param string $expected
   *   The expected value, or `[not_empty]`.
   *
   * @return string|null
   *   A description of the mismatch, or NULL when the property matches.
   */
  protected function metatagCompareJsonLdProperty(array $item, string $path, string $expected): ?string {
    $value = $item;

    foreach (explode('.', $path) as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) {
        return sprintf('the property "%s" does not exist.', $path);
      }

      $value = $value[$segment];
    }

    if ($expected === self::METATAG_JSON_LD_NOT_EMPTY) {
      $is_empty = $value === NULL || $value === [] || (is_string($value) && trim($value) === '');

      return $is_empty ? sprintf('the property "%s" is empty, but it should not be.', $path) : NULL;
    }

    if (is_array($value)) {
      return sprintf('the property "%s" holds an object or a list, but it should have the value "%s".', $path, $expected);
    }

    // JSON writes null in lowercase, which stringFormatValue() does not.
    $actual = $value === NULL ? 'null' : $this->stringFormatValue($value);

    return $actual === $expected ? NULL : sprintf('the property "%s" has the value "%s", but it should have the value "%s".', $path, $actual, $expected);
  }

  /**
   * Parse a `property | value` table into expected JSON-LD property values.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table, with a "property" and a "value" column.
   *
   * @return array<string, string>
   *   The expected values, keyed by property.
   *
   * @throws \RuntimeException
   *   When a column is missing, a property is empty or repeated, or the table
   *   lists no property.
   */
  protected function metatagParseJsonLdPropertyTable(TableNode $table): array {
    $header = $table->getRow(0);

    if (!in_array('property', $header, TRUE) || !in_array('value', $header, TRUE)) {
      throw new \RuntimeException('The properties table must contain "property" and "value" columns.');
    }

    $properties = [];

    foreach ($table->getHash() as $row) {
      $property = trim((string) $row['property']);

      if ($property === '') {
        throw new \RuntimeException('The properties table has a row with an empty property.');
      }

      if (array_key_exists($property, $properties)) {
        throw new \RuntimeException(sprintf('The property "%s" appears more than once in the properties table.', $property));
      }

      $properties[$property] = (string) $row['value'];
    }

    if ($properties === []) {
      throw new \RuntimeException('The properties table must list at least 1 property.');
    }

    return $properties;
  }

}
