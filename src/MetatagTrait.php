<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Step\Then;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Selector\Xpath\Escaper;

/**
 * Assert `<meta>` tags and head/SEO markup in page markup.
 *
 * - Assert presence and content of meta tags with proper attribute handling.
 * - Verify meta tag content is free of HTML markup.
 * - Assert canonical URL, robots directives and indexability.
 * - Assert hreflang alternates are valid and reciprocal.
 * - Assert Open Graph and Twitter Card completeness.
 */
trait MetatagTrait {

  use HelperTrait;

  /**
   * Assert that a meta tag with specific attributes and values exists.
   *
   * @code
   * Then the meta tag should exist with the following attributes:
   *   | name    | description          |
   *   | content | My page description  |
   * @endcode
   */
  #[Then('the meta tag should exist with the following attributes:')]
  public function metatagAssertWithAttributesExists(TableNode $table): void {
    $elements = $this->getSession()->getPage()->findAll('css', 'meta');

    $attributes = [];
    foreach ($table->getRowsHash() as $attribute => $value) {
      $attributes[$attribute] = $value;
    }

    $found = FALSE;

    foreach ($elements as $element) {
      $all_attributes_matched = TRUE;

      foreach ($attributes as $attribute => $value) {
        if ($element->getAttribute($attribute) !== $value) {
          $all_attributes_matched = FALSE;
          break;
        }
      }

      if ($all_attributes_matched) {
        $found = TRUE;
        break;
      }
    }

    if (!$found) {
      throw new \Exception('Meta tag with specified attributes was not found: ' . json_encode($attributes) . '.');
    }
  }

  /**
   * Assert that a meta tag with specific attributes and values does not exist.
   *
   * @code
   * Then the meta tag should not exist with the following attributes:
   *   | name    | nonexistent          |
   *   | content | Some content         |
   * @endcode
   */
  #[Then('the meta tag should not exist with the following attributes:')]
  public function metatagAssertWithAttributesNotExists(TableNode $table): void {
    $meta_tags = $this->getSession()->getPage()->findAll('css', 'meta');

    $attributes = [];
    foreach ($table->getRowsHash() as $attribute => $value) {
      $attributes[$attribute] = $value;
    }

    foreach ($meta_tags as $meta_tag) {
      $all_attributes_matched = TRUE;
      foreach ($attributes as $attribute => $value) {
        if ($meta_tag->getAttribute($attribute) !== $value) {
          $all_attributes_matched = FALSE;
          break;
        }
      }

      if ($all_attributes_matched) {
        throw new \Exception('Meta tag with specified attributes should not exist: ' . json_encode($attributes) . '.');
      }
    }
  }

  /**
   * Assert a meta tag does not contain HTML tags.
   *
   * Looks up the meta tag by either "name" or "property" attribute and checks
   * that the "content" attribute value is free of HTML markup.
   *
   * @code
   * Then the "og:description" meta tag should not contain any HTML tags
   * Then the "description" meta tag should not contain any HTML tags
   * @endcode
   */
  #[Then('the :metaName meta tag should not contain any HTML tags')]
  public function metatagAssertNoHtml(string $meta_name): void {
    $meta_tag = $this->metatagFindMeta($meta_name);

    if ($meta_tag === NULL) {
      throw new \Exception(sprintf('Meta tag with name or property "%s" not found.', $meta_name));
    }

    $content = (string) $meta_tag->getAttribute('content');

    if ($content !== strip_tags($content)) {
      throw new \Exception(sprintf('The "%s" meta tag contains HTML tags: %s', $meta_name, $content));
    }
  }

  /**
   * Assert the canonical URL equals a value.
   *
   * Both the actual and expected URLs are resolved to absolute form against
   * the Mink base URL, so a relative expected value matches an absolute
   * canonical href for the same page.
   *
   * @code
   * Then the canonical URL should be "https://example.com/about"
   * Then the canonical URL should be "/about"
   * @endcode
   */
  #[Then('the canonical URL should be :url')]
  public function metatagAssertCanonicalEquals(string $url): void {
    $href = $this->metatagGetCanonicalHref();

    if ($href === NULL || $href === '') {
      throw new \Exception('The canonical URL is not set.');
    }

    if ($this->metatagResolveUrl($href) !== $this->metatagResolveUrl($url)) {
      throw new \Exception(sprintf('The canonical URL is "%s", but expected "%s".', $href, $url));
    }
  }

  /**
   * Assert the canonical URL is present.
   *
   * @code
   * Then the canonical URL should exist
   * @endcode
   */
  #[Then('the canonical URL should exist')]
  public function metatagAssertCanonicalExists(): void {
    $href = $this->metatagGetCanonicalHref();

    if ($href === NULL || $href === '') {
      throw new \Exception('The canonical URL is not set.');
    }
  }

  /**
   * Assert the canonical URL is absent.
   *
   * @code
   * Then the canonical URL should not exist
   * @endcode
   */
  #[Then('the canonical URL should not exist')]
  public function metatagAssertCanonicalNotExists(): void {
    $href = $this->metatagGetCanonicalHref();

    if ($href !== NULL && $href !== '') {
      throw new \Exception(sprintf('The canonical URL should not be set, but found "%s".', $href));
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
   */
  #[Then('the page should be indexable')]
  public function metatagAssertIndexable(): void {
    if (!$this->metatagIsIndexable()) {
      throw new \Exception('The page is not indexable: a "noindex" directive is present in the robots meta tag or the "X-Robots-Tag" header.');
    }
  }

  /**
   * Assert the page is not indexable.
   *
   * @code
   * Then the page should not be indexable
   * @endcode
   */
  #[Then('the page should not be indexable')]
  public function metatagAssertNotIndexable(): void {
    if ($this->metatagIsIndexable()) {
      throw new \Exception('The page is indexable, but it should not be: no "noindex" directive found in the robots meta tag or the "X-Robots-Tag" header.');
    }
  }

  /**
   * Assert the robots meta tag includes a directive.
   *
   * Directives are matched as whole, case-insensitive tokens, so a request for
   * "follow" never matches a "nofollow" directive.
   *
   * @code
   * Then the meta robots should include "noindex"
   * Then the meta robots should include "nofollow"
   * @endcode
   */
  #[Then('the meta robots should include :directive')]
  public function metatagAssertRobotsIncludes(string $directive): void {
    $directives = $this->metatagGetRobotsDirectives();

    if (!in_array(strtolower(trim($directive)), $directives, TRUE)) {
      throw new \Exception(sprintf('The robots meta tag does not include the "%s" directive. Found: %s.', $directive, $directives === [] ? '(none)' : implode(', ', $directives)));
    }
  }

  /**
   * Assert the robots meta tag does not include a directive.
   *
   * @code
   * Then the meta robots should not include "noindex"
   * Then the meta robots should not include "nofollow"
   * @endcode
   */
  #[Then('the meta robots should not include :directive')]
  public function metatagAssertRobotsNotIncludes(string $directive): void {
    $directives = $this->metatagGetRobotsDirectives();

    if (in_array(strtolower(trim($directive)), $directives, TRUE)) {
      throw new \Exception(sprintf('The robots meta tag includes the "%s" directive, but it should not.', $directive));
    }
  }

  /**
   * Assert hreflang alternates are valid.
   *
   * Checks, without fetching any alternate page, that at least one hreflang
   * alternate exists, that a self-referencing alternate for the current URL is
   * present, and that every hreflang value is a well-formed language code (or
   * "x-default").
   *
   * @code
   * Then the hreflang alternates should be valid
   * @endcode
   */
  #[Then('the hreflang alternates should be valid')]
  public function metatagAssertHreflangValid(): void {
    $alternates = $this->metatagGetHreflangAlternates();

    if ($alternates === []) {
      throw new \Exception('No hreflang alternate links were found on the page.');
    }

    foreach ($alternates as $alternate) {
      if ($alternate['href'] === '') {
        throw new \Exception(sprintf('The hreflang alternate for "%s" has an empty href.', $alternate['hreflang']));
      }

      if (!$this->metatagIsValidHreflang($alternate['hreflang'])) {
        throw new \Exception(sprintf('The hreflang value "%s" is not a valid language code.', $alternate['hreflang']));
      }
    }

    $current = $this->metatagResolveUrl($this->getSession()->getCurrentUrl());

    foreach ($alternates as $alternate) {
      if ($this->metatagResolveUrl($alternate['href']) === $current) {
        return;
      }
    }

    throw new \Exception(sprintf('No self-referencing hreflang alternate was found for the current URL "%s".', $current));
  }

  /**
   * Assert hreflang alternates have reciprocal return links.
   *
   * Fetches each non-"x-default" alternate page (other than the current page)
   * and asserts that it links back to the current URL, failing clearly when an
   * alternate does not reciprocate.
   *
   * @code
   * Then the hreflang alternates should have reciprocal return links
   * @endcode
   */
  #[Then('the hreflang alternates should have reciprocal return links')]
  public function metatagAssertHreflangReciprocal(): void {
    $this->metatagAssertHreflangValid();

    $alternates = $this->metatagGetHreflangAlternates();
    $current = $this->metatagResolveUrl($this->getSession()->getCurrentUrl());

    foreach ($alternates as $alternate) {
      $target = $this->metatagResolveUrl($alternate['href']);

      if ($target === $current || strtolower((string) $alternate['hreflang']) === 'x-default') {
        continue;
      }

      if (!$this->metatagHtmlLinksBackTo($this->metatagFetchUrl($target), $current, $target)) {
        throw new \Exception(sprintf('The hreflang alternate "%s" (%s) does not link back to the current URL "%s".', $alternate['hreflang'], $target, $current));
      }
    }
  }

  /**
   * Assert the required Open Graph meta tags are present and non-empty.
   *
   * The required set defaults to the Open Graph basics and can be overridden by
   * the consuming context via metatagOpenGraphRequired().
   *
   * @code
   * Then the Open Graph tags should be valid
   * @endcode
   */
  #[Then('the Open Graph tags should be valid')]
  public function metatagAssertOpenGraphValid(): void {
    $this->metatagAssertMetaSetPresent($this->metatagOpenGraphRequired(), 'Open Graph');
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
   */
  #[Then('the following Open Graph tags should exist:')]
  public function metatagAssertOpenGraphTags(TableNode $table): void {
    $this->metatagAssertMetaSetPresent($this->metatagTablePropertyNames($table), 'Open Graph');
  }

  /**
   * Assert the required Twitter Card meta tags are present and non-empty.
   *
   * The required set defaults to the Twitter Card basics and can be overridden
   * by the consuming context via metatagTwitterCardRequired().
   *
   * @code
   * Then the Twitter Card tags should be valid
   * @endcode
   */
  #[Then('the Twitter Card tags should be valid')]
  public function metatagAssertTwitterCardValid(): void {
    $this->metatagAssertMetaSetPresent($this->metatagTwitterCardRequired(), 'Twitter Card');
  }

  /**
   * Assert the listed Twitter Card meta tags are present and non-empty.
   *
   * @code
   * Then the following Twitter Card tags should exist:
   *   | twitter:card  |
   *   | twitter:title |
   * @endcode
   */
  #[Then('the following Twitter Card tags should exist:')]
  public function metatagAssertTwitterCardTags(TableNode $table): void {
    $this->metatagAssertMetaSetPresent($this->metatagTablePropertyNames($table), 'Twitter Card');
  }

  /**
   * Find a meta tag by its "name" or "property" attribute.
   *
   * @param string $name
   *   The meta tag name or property.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The meta element, or NULL when not found.
   */
  protected function metatagFindMeta(string $name): ?NodeElement {
    $escaped_name = (new Escaper())->escapeLiteral($name);

    return $this->getSession()->getPage()->find('xpath', sprintf('//meta[@name=%s or @property=%s]', $escaped_name, $escaped_name));
  }

  /**
   * Get the content of a meta tag by its "name" or "property" attribute.
   *
   * @param string $name
   *   The meta tag name or property.
   *
   * @return string|null
   *   The content attribute value, or NULL when the meta tag is not found.
   */
  protected function metatagGetMetaContent(string $name): ?string {
    $meta = $this->metatagFindMeta($name);

    return $meta === NULL ? NULL : (string) $meta->getAttribute('content');
  }

  /**
   * Get the canonical URL href.
   *
   * @return string|null
   *   The canonical href, or NULL when no canonical link is present.
   */
  protected function metatagGetCanonicalHref(): ?string {
    $link = $this->getSession()->getPage()->find('xpath', '//link[@rel="canonical"]');

    return $link === NULL ? NULL : (string) $link->getAttribute('href');
  }

  /**
   * Get the robots meta tag directives as lower-cased tokens.
   *
   * @return array<int, string>
   *   The directive tokens, or an empty array when no robots meta tag exists.
   */
  protected function metatagGetRobotsDirectives(): array {
    $content = $this->metatagGetMetaContent('robots');

    if ($content === NULL || trim($content) === '') {
      return [];
    }

    return array_values(array_filter($this->helperSplitCommaSeparated(strtolower($content)), static fn(string $directive): bool => $directive !== ''));
  }

  /**
   * Determine whether the current page is indexable.
   *
   * @return bool
   *   TRUE when neither the robots meta tag nor the X-Robots-Tag header carries
   *   a "noindex" directive.
   */
  protected function metatagIsIndexable(): bool {
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
  protected function metatagResponseHasNoindexHeader(): bool {
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
   * Get the hreflang alternates present on the current page.
   *
   * @return array<int, array{hreflang: string, href: string}>
   *   The hreflang alternates, each with its raw hreflang value and href.
   */
  protected function metatagGetHreflangAlternates(): array {
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
  protected function metatagIsValidHreflang(string $value): bool {
    if (strtolower($value) === 'x-default') {
      return TRUE;
    }

    return preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i', $value) === 1;
  }

  /**
   * Fetch a URL out of band without disturbing the Mink session.
   *
   * @param string $url
   *   The absolute URL to fetch.
   *
   * @return string
   *   The response body.
   */
  protected function metatagFetchUrl(string $url): string {
    $handle = curl_init($url);

    if ($handle === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \Exception(sprintf('Failed to initialise a request for "%s".', $url));
      // @codeCoverageIgnoreEnd
    }

    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);

    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if (!is_string($body)) {
      // @codeCoverageIgnoreStart
      throw new \Exception(sprintf('Failed to fetch the hreflang alternate page "%s".', $url));
      // @codeCoverageIgnoreEnd
    }

    if ($status >= 400) {
      throw new \Exception(sprintf('The hreflang alternate page "%s" returned HTTP status %d.', $url, $status));
    }

    return $body;
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
    $previous = libxml_use_internal_errors(TRUE);

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
      libxml_use_internal_errors($previous);
    }
  }

  /**
   * Resolve an absolute or root-relative URL against a base URL's origin.
   *
   * Absolute URLs are returned unchanged and root-relative URLs are resolved
   * against the base URL's origin. Document-relative URLs (such as "page.html"
   * or "../en") are resolved against the origin rather than the base path, so
   * hreflang and canonical markup should use absolute or root-relative URLs, in
   * line with search-engine guidance to use fully-qualified URLs.
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

    // Resolve root-relative URLs against the base URL's origin so that links on
    // a fetched alternate page resolve against that page's host rather than the
    // Mink base URL.
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
  protected function metatagAssertMetaSetPresent(array $names, string $label): void {
    $missing = [];

    foreach ($names as $name) {
      $content = $this->metatagGetMetaContent($name);

      if ($content === NULL || trim($content) === '') {
        $missing[] = $name;
      }
    }

    if ($missing !== []) {
      throw new \Exception(sprintf('The following required %s meta tags are missing or empty: %s.', $label, implode(', ', $missing)));
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
  protected function metatagOpenGraphRequired(): array {
    return ['og:title', 'og:type', 'og:image', 'og:url'];
  }

  /**
   * The Twitter Card tags required by "the Twitter Card tags should be valid".
   *
   * @return array<int, string>
   *   The required Twitter Card property names.
   */
  protected function metatagTwitterCardRequired(): array {
    return ['twitter:card', 'twitter:title', 'twitter:description'];
  }

}
