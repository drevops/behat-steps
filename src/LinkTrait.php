<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait LinkTrait.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait LinkTrait {

  /**
   * @Then I should see the link :text with :href in :locator
   */
  public function linkAssertTextHref($text, $href, $locator = NULL) {
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
    $pattern = strpos($href, '*') !== FALSE ? str_replace('\*', '.*', $pattern) : $pattern;
    if (!preg_match($pattern, $link->getAttribute('href'))) {
      throw new \Exception(sprintf('The link href "%s" does not match the specified href "%s"', $link->getAttribute('href'), $href));
    }
  }

}
