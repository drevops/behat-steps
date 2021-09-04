<?php

namespace DrevOps\BehatSteps;

/**
 * Trait VisibilityTrait.
 *
 * @package DrevOps\BehatSteps
 */
trait VisibilityTrait {

  /**
   * Assert that element with specified CSS is visible on page.
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
   * Assert that element with specified CSS is visible on page.
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

  /**
   * Assert that element with specified CSS is visually visible on page.
   *
   * @Then /^(?:|I )should see a visually visible "(?P<selector>[^"]*)" element(?: with top offset of "([^"]*)" pixels)?$/
   */
  public function visibilityAssertElementIsVisuallyVisible($selector, $offset = 0) {
    $this->visibilityAssertElementIsVisible($selector);

    $element = $this->getSession()->getPage();
    $nodes = $element->findAll('css', $selector);

    foreach ($nodes as $k => $node) {
      $index = $k + 1;
      // Add :nth-child() to address every element.
      if (strpos($selector, ':nth-child') === FALSE) {
        $selector .= ":nth-child($index)";
      }

      if (!$this->visibilityElementIsVisuallyVisible($selector, $offset)) {
        throw new \Exception(sprintf('Element defined by "%s" selector with index "%s" is not visually visible on the page.', $selector, $index));
      }
    }
  }

  /**
   * Assert that element with specified CSS is visually hidden on page.
   *
   * Visually hidden means either:
   * - element is not rendered in the layout (i.e., CSS is "display: none").
   * - element is rendered in the layout, but not visible to the viewer (i.e.,
   *   when one of the screen reader-only techniques is used).
   *
   * @Then /^(?:|I )should not see a visually hidden "(?P<selector>[^"]*)" element(?: with top offset of "([^"]*)" pixels)?$/
   */
  public function visibilityAssertElementIsVisuallyHidden($selector, $offset = 0) {
    $element = $this->getSession()->getPage();
    $nodes = $element->findAll('css', $selector);

    foreach ($nodes as $k => $node) {
      if (!$node->isVisible()) {
        // Node is not rendered in layout.
        continue;
      }

      $index = $k + 1;
      // Add :nth-child() to address every element.
      if (strpos($selector, ':nth-child') === FALSE) {
        $selector .= ":nth-child($index)";
      }

      if ($this->visibilityElementIsVisuallyVisible($selector, $offset)) {
        throw new \Exception(sprintf('Element defined by "%s" selector with index "%s" is visually visible on the page, but should not be.', $selector, $index));
      }
    }
  }

  /**
   * Check if an element is visually visible using different FE techniques.
   *
   * @param string $selector
   *   CSS query selector.
   * @param int $offset
   *   (optional) Vertical element offset in pixels. Defaults to 0.
   *
   * @return bool
   *   TRUE if an element is visually visible, FALSE if not.
   */
  protected function visibilityElementIsVisuallyVisible($selector, $offset) {
    // The contents of this JS function should be copied as-is from the <script>
    // section in the bottom of the tests/behat/fixtures/relative.html file.
    $scriptFunction = <<<JS
      function isElemVisible(selector, offset = 0) {
        selector = selector.startsWith('#') || selector.indexOf(':nth-child') !== -1 ? selector : selector + ':nth-child(1)';
    
        el = document.querySelectorAll(selector)[0];
        if (!el) {
          return false;
        }
    
        // Inject a style to disable scrollbars for more consistent results.
        if (document.querySelectorAll('head #relative_style').length === 0) {
          document.querySelector('head').insertAdjacentHTML('beforeend', '<style id="relative_style" type="text/css">::-webkit-scrollbar{display: none;}</style>');
        }
    
        // Scroll to the element top, accounting for an offset.
        window.scroll({top: el.offsetTop - offset});
    
        // Gather visibility constraints.
        isVisible = !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        hasHeight = el.clientHeight > 1 || el.offsetHeight > 1;
        notClipped = !(getComputedStyle(el).clip === 'rect(0px 0px 0px 0px)' && getComputedStyle(el).position === 'absolute');
        rect = el.getBoundingClientRect();
        onScreen = !(
          rect.left + rect.width <= 0
          || rect.top + rect.height <= 0
          || rect.left >= window.innerWidth
          || rect.top >= window.innerHeight
        );
    
        return isVisible && hasHeight && notClipped && onScreen;
      }
    JS;

    // Include and call visibility assertion function.
    $script = <<<JS
      (function() {
        $scriptFunction
        return isElemVisible('$selector', $offset);
      })();
    JS;

    return $this->getSession()->getDriver()->evaluateScript($script);
  }

}
