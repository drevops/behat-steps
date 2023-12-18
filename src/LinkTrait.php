<?php

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
   * Then I should see the link "About us" with "/about-us"
   * Then I should see the link "About us" with "/about-us" in ".main-nav"
   * Then I should see the link "About us" with "/about*" in ".main-nav"
   * @endcode
   *
   * @Then I should see the link :text with :href
   * @Then I should see the link :text with :href in :locator
   */
  public function linkAssertTextHref(string $text, string $href, string $locator = NULL): void {
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
    if (!preg_match($pattern, $link->getAttribute('href'))) {
      throw new \Exception(sprintf('The link href "%s" does not match the specified href "%s"', $link->getAttribute('href'), $href));
    }
  }

  /**
   * Assert link with a href does not exist.
   *
   * Note that simplified wildcard is supported in "href".
   *
   * @code
   * Then I should not see the link "About us" with "/about-us"
   * Then I should not see the link "About us" with "/about-us" in ".main-nav"
   * Then I should not see the link "About us" with "/about*" in ".main-nav"
   * @endcode
   *
   * @Then I should not see the link :text with :href
   * @Then I should not see the link :text with :href in :locator
   */
  public function linkAssertTextHrefNotExists(string $text, string $href, string $locator = NULL): void {
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
    if (preg_match($pattern, $link->getAttribute('href'))) {
      throw new \Exception(sprintf('The link href "%s" matches the specified href "%s" but should not', $link->getAttribute('href'), $href));
    }
  }

  /**
   * Assert that a link with a title exists.
   *
   * @Then the link with title :title exists
   */
  public function linkAssertWithTitle(string $title): NodeElement {
    $title = $this->linkFixStepArgument($title);

    $element = $this->getSession()->getPage()->find('css', 'a[title="' . $title . '"]');

    if (!$element) {
      throw new \Exception(sprintf('The link with title "%s" does not exist.', $title));
    }

    return $element;
  }

  /**
   * Assert that a link with a title does not exist.
   *
   * @Then the link with title :title does not exist
   */
  public function linkAssertWithNoTitle(string $title): void {
    $title = $this->linkFixStepArgument($title);

    $item = $this->getSession()->getPage()->find('css', 'a[title="' . $title . '"]');

    if ($item) {
      throw new \Exception(sprintf('The link with title "%s" exists, but should not.', $title));
    }
  }

  /**
   * Click on the link with a title.
   *
   * @Then I click the link with title :title
   */
  public function linkClickWithTitle(string $title): void {
    $link = $this->linkAssertWithTitle($title);
    $link->click();
  }

  /**
   * Assert that the link with a text is absolute.
   *
   * @Then the link( with title) :text is an absolute link
   */
  public function assertLinkAbsolute(string $text): void {
    $link = $this->getSession()->getPage()->findLink($text);
    if (!$link) {
      throw new \Exception(sprintf('The link "%s" is not found', $text));
    }
    $href = $link->getAttribute('href');
    if (!parse_url($href, PHP_URL_SCHEME)) {
      throw new \Exception(sprintf('The link "%s" is not an absolute link.', $text));
    }
  }

  /**
   * Assert that the link with a title is not absolute.
   *
   * @Then the link( with title) :text is not an absolute link
   */
  public function assertLinkNotAbsolute(string $text): void {
    $link = $this->getSession()->getPage()->findLink($text);
    if (!$link) {
      throw new \Exception(sprintf('The link "%s" is not found', $text));
    }
    $href = $link->getAttribute('href');
    if (parse_url($href, PHP_URL_SCHEME)) {
      throw new \Exception(sprintf('The link "%s" is an absolute link.', $text));
    }
  }

  /**
   * Returns fixed step argument (with \\" replaced back to ").
   */
  protected function linkFixStepArgument(string $argument): string|array {
    return str_replace('\\"', '"', $argument);
  }

}
