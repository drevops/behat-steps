<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

/**
 * Verify link elements with attribute and content assertions.
 *
 * - Find links by title, URL, text content, and class attributes.
 * - Test link existence, visibility, and destination accuracy.
 * - Assert absolute and relative link paths.
 */
trait LinkTrait {

  /**
   * Assert a link with a href exists.
   *
   * Note that simplified wildcard is supported in "href".
   *
   * @code
   * Then the link "About us" with the href "/about-us" should exist
   * Then the link "About us" with the href "/about*" should exist
   * @endcode
   *
   * @Then the link :link with the href :href should exist
   */
  public function linkAssertTextWithHrefExists(string $text, string $href): void {
    $this->linkAssertTextWithHrefWithinElementExists($text, $href, NULL);
  }

  /**
   * Assert link with a href exists within an element.
   *
   * Note that simplified wildcard is supported in "href".
   *
   * @code
   * Then the link "About us" with the href "/about-us" within the element ".main-nav" should exist
   * Then the link "About us" with the href "/about*" within the element ".main-nav" should exist
   * @endcode
   *
   * @Then the link :link with the href :href within the element :selector should exist
   */
  public function linkAssertTextWithHrefWithinElementExists(string $text, string $href, ?string $selector): void {
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    if ($selector) {
      $element = $page->find('css', $selector);
      if (!$element) {
        throw new \Exception(sprintf('Selector "%s" does not exist on the page', $selector));
      }
    }
    else {
      $element = $page;
    }

    $link = $element->findLink($text);
    if (!$link) {
      throw new \Exception(sprintf('The link "%s" is not found', $text));
    }

    if (!$link->hasAttribute('href')) {
      throw new \Exception('The link does not contain a href attribute');
    }

    $pattern = '/' . preg_quote($href, '/') . '/';
    // Support for simplified wildcard using '*'.
    $pattern = str_contains($href, '*') ? str_replace('\*', '.*', $pattern) : $pattern;
    if (!preg_match($pattern, (string) $link->getAttribute('href'))) {
      throw new \Exception(sprintf('The link href "%s" does not match the specified href "%s"', $link->getAttribute('href'), $href));
    }
  }

  /**
   * Assert link with a href does not exist.
   *
   * Note that simplified wildcard is supported in "href".
   *
   * @code
   * Then the link "About us" with the href "/about-us" should not exist
   * Then the link "About us" with the href "/about*" should not exist
   * @endcode
   *
   * @Then the link :link with the href :href should not exist
   */
  public function linkAssertTextWithHrefNotExists(string $text, string $href): void {
    $this->linkAssertTextWithHrefWithinElementNotExists($text, $href, NULL);
  }

  /**
   * Assert link with a href does not exist within an element.
   *
   * Note that simplified wildcard is supported in "href".
   *
   * @code
   * Then the link "About us" with the href "/about-us" within the element ".main-nav" should not exist
   * Then the link "About us" with the href "/about*" within the element ".main-nav" should not exist
   * @endcode
   *
   * @Then the link :link with the href :href within the element :selector should not exist
   */
  public function linkAssertTextWithHrefWithinElementNotExists(string $text, string $href, ?string $selector): void {
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    if ($selector) {
      $element = $page->find('css', $selector);
      if (!$element) {
        return;
      }
    }
    else {
      $element = $page;
    }

    $link = $element->findLink($text);
    if (!$link) {
      return;
    }

    if (!$link->hasAttribute('href')) {
      return;
    }

    $pattern = '/' . preg_quote($href, '/') . '/';
    // Support for simplified wildcard using '*'.
    $pattern = str_contains($href, '*') ? str_replace('\*', '.*', $pattern) : $pattern;
    if (preg_match($pattern, (string) $link->getAttribute('href'))) {
      throw new \Exception(sprintf('The link href "%s" matches the specified href "%s" but should not', $link->getAttribute('href'), $href));
    }
  }

  /**
   * Assert that a link with a title exists.
   *
   * @code
   * Then the link with the title "Return to site content" should exist
   * @endcode
   *
   * @Then the link with the title :title should exist
   */
  public function linkAssertWithTitleExists(string $title): void {
    $title = $this->linkFixStepArgument($title);

    $element = $this->getSession()->getPage()->find('css', 'a[title="' . addslashes((string) $title) . '"]');

    if (!$element) {
      throw new \Exception(sprintf('The link with the title "%s" does not exist.', $title));
    }
  }

  /**
   * Assert that a link with a title does not exist.
   *
   * @code
   * Then the link with the title "Some non-existing title" should not exist
   * @endcode
   *
   * @Then the link with the title :title should not exist
   */
  public function linkAssertWithTitleNotExists(string $title): void {
    $title = $this->linkFixStepArgument($title);

    $item = $this->getSession()->getPage()->find('css', 'a[title="' . addslashes((string) $title) . '"]');

    if ($item) {
      throw new \Exception(sprintf('The link with the title "%s" exists, but should not.', $title));
    }
  }

  /**
   * Assert that the link with a text is absolute.
   *
   * @code
   * Then the link "my-link-title" should be an absolute link
   * @endcode
   *
   * @Then the link :link should be an absolute link
   */
  public function linkAssertLinkIsAbsolute(string $text): void {
    $link = $this->getSession()->getPage()->findLink($text);

    if (!$link) {
      throw new \Exception(sprintf('The link "%s" is not found', $text));
    }

    $href = $link->getAttribute('href');

    if (!parse_url((string) $href, PHP_URL_SCHEME)) {
      throw new \Exception(sprintf('The link "%s" is not an absolute link.', $text));
    }
  }

  /**
   * Assert that the link is not an absolute.
   *
   * @code
   * Then the link "Return to site content" should not be an absolute link
   * @endcode
   *
   * @Then the link :link should not be an absolute link
   */
  public function linkAssertLinkIsNotAbsolute(string $text): void {
    $link = $this->getSession()->getPage()->findLink($text);

    if (!$link) {
      throw new \Exception(sprintf('The link "%s" is not found', $text));
    }

    $href = $link->getAttribute('href');

    if (parse_url((string) $href, PHP_URL_SCHEME)) {
      throw new \Exception(sprintf('The link "%s" is an absolute link.', $text));
    }
  }

  /**
   * Click on the link with a title.
   *
   * @code
   * When I click on the link with the title "Return to site content"
   * @endcode
   *
   * @When I click on the link with the title :title
   */
  public function linkClickWithTitle(string $title): void {
    $title = $this->linkFixStepArgument($title);
    $element = $this->getSession()->getPage()->find('css', 'a[title="' . addslashes((string) $title) . '"]');

    if (!$element) {
      throw new \Exception(sprintf('The link with the title "%s" does not exist.', $title));
    }

    $element->click();
  }

  /**
   * Return fixed step argument (with \\" replaced back to ").
   */
  protected function linkFixStepArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

}
