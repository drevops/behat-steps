<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Element\NodeElement;

/**
 * Trait LinkTrait.
 *
 * Link-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait LinkTrait {

  /**
   * Assert presence of a link with a href.
   *
   * Note that simplified wildcard is supported in "href".
   *
   * @code
   * Then the link "About us" with the href "/about-us" should exist
   * Then the link "About us" with the href "/about-us" within the element ".main-nav" should exist
   * Then the link "About us" with the href "/about*" within the element ".main-nav" should exist
   * @endcode
   *
   * @Then the link :link with the href :href should exist
   * @Then the link :link with the href :href within the element :locator should exist
   */
  public function linkAssertTextHref(string $text, string $href, ?string $locator = NULL): void {
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    if ($locator) {
      $element = $page->find('css', $locator);
      if (!$element) {
        throw new \Exception(sprintf('Locator "%s" does not exist on the page', $locator));
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
   * Then the link "About us" with the href "/about-us" within the element ".main-nav" should not exist
   * Then the link "About us" with the href "/about*" within the element ".main-nav" should not exist
   * @endcode
   *
   * @Then the link :link with the href :href should not exist
   * @Then the link :link with the href :href within the element :locator should not exist
   */
  public function linkAssertTextHrefNotExists(string $text, string $href, ?string $locator = NULL): void {
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    if ($locator) {
      $element = $page->find('css', $locator);
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
  public function linkAssertWithTitle(string $title): NodeElement {
    $title = $this->linkFixStepArgument($title);

    $element = $this->getSession()->getPage()->find('css', 'a[title="' . $title . '"]');

    if (!$element) {
      throw new \Exception(sprintf('The link with the title "%s" does not exist.', $title));
    }

    return $element;
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
  public function linkAssertWithNoTitle(string $title): void {
    $title = $this->linkFixStepArgument($title);

    $item = $this->getSession()->getPage()->find('css', 'a[title="' . $title . '"]');

    if ($item) {
      throw new \Exception(sprintf('The link with the title "%s" exists, but should not.', $title));
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
    $link = $this->linkAssertWithTitle($title);
    $link->click();
  }

  /**
   * Assert that the link with a text is absolute.
   *
   * @code
   * Then the link "Drupal" should be an absolute link
   * @endcode
   *
   * @Then the link :link should be an absolute link
   */
  public function linkAssertLinkAbsolute(string $text): void {
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
  public function linkAssertLinkNotAbsolute(string $text): void {
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
   * Returns fixed step argument (with \\" replaced back to ").
   */
  protected function linkFixStepArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

}
