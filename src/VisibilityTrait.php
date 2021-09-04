<?php

namespace DrevOps\BehatSteps;

/**
 * Trait VisibilityTrait.
 *
 * @package DrevOps\BehatSteps
 */
trait VisibilityTrait {

  /**
   * Checks that element with specified CSS is visible on page.
   *
   * @Then /^(?:|I )should see a visible "(?P<selector>[^"]*)" element$/
   */
  public function visibilityAssertElementIsVisible($selector) {
    $element = $this->getSession()->getPage();
    $nodes = $element->findAll('css', $selector);

    if (empty($nodes)) {
      throw new \Exception(sprintf('Element defined by "%s" selector is not present on the page.', $selector));
    }

    foreach ($nodes as $node) {
      if (!$node->isVisible()) {
        throw new \Exception(sprintf('Element defined by "%s" selector is not visible on the page.', $selector));
      }
    }
  }

  /**
   * Checks that element with specified CSS is visible on page.
   *
   * @Then /^(?:|I )should not see a visible "(?P<selector>[^"]*)" element$/
   */
  public function visibilityAssertElementIsNotVisible($selector) {
    $element = $this->getSession()->getPage();
    $nodes = $element->findAll('css', $selector);

    foreach ($nodes as $node) {
      if ($node->isVisible()) {
        throw new \Exception(sprintf('Element defined by "%s" selector is visible on the page, but should not be.', $selector));
      }
    }
  }

}
