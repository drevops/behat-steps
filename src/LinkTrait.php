<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Step\Then;
use Behat\Step\When;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;

/**
 * Verify link elements with attribute and content assertions.
 *
 * - Find links by title, URL, text content, and class attributes.
 * - Test link existence, visibility, and destination accuracy.
 * - Assert absolute and relative link paths.
 */
trait LinkTrait {

  use HelperTrait;

  /**
   * Assert a link with a href exists.
   *
   * Note that simplified wildcard is supported in "href".
   *
   * @code
   * Then the link "About us" with the href "/about-us" should exist
   * Then the link "About us" with the href "/about*" should exist
   * @endcode
   */
  #[Then('the link :link with the href :href should exist')]
  public function linkAssertTextWithHrefExists(string $link, string $href): void {
    $this->linkAssertTextWithHrefWithinElementExists($link, $href, NULL);
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
   */
  #[Then('the link :link with the href :href within the element :selector should exist')]
  public function linkAssertTextWithHrefWithinElementExists(string $link, string $href, ?string $selector): void {
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    if ($selector) {
      $element = $page->find('css', $selector);
      if (!$element) {
        throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
      }
    }
    else {
      $element = $page;
    }

    $link_element = $element->findLink($link);
    if (!$link_element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'link', 'text', $link);
    }

    $pattern = '/' . preg_quote($href, '/') . '/';
    // Support for simplified wildcard using '*'.
    $pattern = str_contains($href, '*') ? str_replace('\*', '.*', $pattern) : $pattern;
    if (!preg_match($pattern, (string) $link_element->getAttribute('href'))) {
      throw new ExpectationException(sprintf('The link href "%s" does not match the specified href "%s"', $link_element->getAttribute('href'), $href), $this->getSession()->getDriver());
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
   */
  #[Then('the link :link with the href :href should not exist')]
  public function linkAssertTextWithHrefNotExists(string $link, string $href): void {
    $this->linkAssertTextWithHrefWithinElementNotExists($link, $href, NULL);
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
   */
  #[Then('the link :link with the href :href within the element :selector should not exist')]
  public function linkAssertTextWithHrefWithinElementNotExists(string $link, string $href, ?string $selector): void {
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

    $link_element = $element->findLink($link);
    if (!$link_element) {
      return;
    }

    $pattern = '/' . preg_quote($href, '/') . '/';
    // Support for simplified wildcard using '*'.
    $pattern = str_contains($href, '*') ? str_replace('\*', '.*', $pattern) : $pattern;
    if (preg_match($pattern, (string) $link_element->getAttribute('href'))) {
      throw new ExpectationException(sprintf('The link href "%s" matches the specified href "%s" but should not', $link_element->getAttribute('href'), $href), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a link with a title exists.
   *
   * @code
   * Then the link with the title "Return to site content" should exist
   * @endcode
   */
  #[Then('the link with the title :title should exist')]
  public function linkAssertWithTitleExists(string $title): void {
    $title = $this->helperFixStepArgument($title);

    $element = $this->getSession()->getPage()->find('css', 'a[title="' . addslashes((string) $title) . '"]');

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'link', 'title', $title);
    }
  }

  /**
   * Assert that a link with a title does not exist.
   *
   * @code
   * Then the link with the title "Some non-existing title" should not exist
   * @endcode
   */
  #[Then('the link with the title :title should not exist')]
  public function linkAssertWithTitleNotExists(string $title): void {
    $title = $this->helperFixStepArgument($title);

    $item = $this->getSession()->getPage()->find('css', 'a[title="' . addslashes((string) $title) . '"]');

    if ($item) {
      throw new ExpectationException(sprintf('The link with the title "%s" exists, but should not.', $title), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the link with a text is absolute.
   *
   * @code
   * Then the link "my-link-title" should be an absolute link
   * @endcode
   */
  #[Then('the link :link should be an absolute link')]
  public function linkAssertLinkIsAbsolute(string $link): void {
    $link_element = $this->getSession()->getPage()->findLink($link);

    if (!$link_element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'link', 'text', $link);
    }

    $href = $link_element->getAttribute('href');

    if (!parse_url((string) $href, PHP_URL_SCHEME)) {
      throw new ExpectationException(sprintf('The link "%s" is not an absolute link.', $link), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the link is not absolute.
   *
   * @code
   * Then the link "Return to site content" should not be an absolute link
   * @endcode
   */
  #[Then('the link :link should not be an absolute link')]
  public function linkAssertLinkIsNotAbsolute(string $link): void {
    $link_element = $this->getSession()->getPage()->findLink($link);

    if (!$link_element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'link', 'text', $link);
    }

    $href = $link_element->getAttribute('href');

    if (parse_url((string) $href, PHP_URL_SCHEME)) {
      throw new ExpectationException(sprintf('The link "%s" is an absolute link.', $link), $this->getSession()->getDriver());
    }
  }

  /**
   * Click on the link with a title.
   *
   * @code
   * When I click on the link with the title "Return to site content"
   * @endcode
   */
  #[When('I click on the link with the title :title')]
  public function linkClickWithTitle(string $title): void {
    $title = $this->helperFixStepArgument($title);
    $element = $this->getSession()->getPage()->find('css', 'a[title="' . addslashes((string) $title) . '"]');

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'link', 'title', $title);
    }

    $element->click();
  }

}
