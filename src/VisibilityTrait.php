<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

/**
 * Trait VisibilityTrait.
 *
 * Visibility-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait VisibilityTrait {

  /**
   * Assert that element with specified CSS is visible on page.
   *
   * @Then the element :selector should be displayed
   */
  public function visibilityAssertElementIsVisible(string $selector): void {
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
   * @Then the element :selector should not be displayed
   */
  public function visibilityAssertElementIsNotVisible(string $selector): void {
    $element = $this->getSession()->getPage();
    $nodes = $element->findAll('css', $selector);

    foreach ($nodes as $node) {
      if ($node->isVisible()) {
        throw new \Exception(sprintf('Element defined by "%s" selector is visible on the page, but should not be.', $selector));
      }
    }
  }

  /**
   * Assert that element with specified CSS is displayed within a viewport
   *
   * @Then the element :selector should be displayed within a viewport
   */
  public function visibilityAssertElementIsVisuallyVisible(string $selector): void {
    $this->visibilityAssertElementIsVisible($selector);

    if (!$this->visibilityElementIsVisuallyVisible($selector, 0)) {
      throw new \Exception(sprintf('Element(s) defined by "%s" selector is not displayed within a viewport.', $selector));
    }
  }

  /**
   * Assert that element with specified CSS is displayed within a viewport with a top offset.
   *
   * @Then the element :selector should be displayed within a viewport with a top offset of :number pixels
   */
  public function visibilityAssertElementIsVisuallyVisibleWithOffset(string $selector, int $number): void {
    $this->visibilityAssertElementIsVisible($selector);
    if (!$this->visibilityElementIsVisuallyVisible($selector, $number)) {
      throw new \Exception(sprintf('Element(s) defined by "%s" selector is not displayed within a viewport with a top offset of %d pixels.', $selector, $number));
    }
  }

  /**
   * Assert that element with specified CSS is not displayed within a viewport with a top offset.
   *
   * @Then the element :selector should not be displayed within a viewport with a top offset of :number pixels
   */
  public function visibilityAssertElementIsNotVisuallyVisibleWithOffset(string $selector, int $number): void {
    if ($this->visibilityElementIsVisuallyVisible($selector, $number)) {
      throw new \Exception(sprintf('Element(s) defined by "%s" selector is displayed within a viewport with a top offset of %d pixels, but should not be.', $selector, $number));
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
   * @Then the element :selector should not be displayed within a viewport
   */
  public function visibilityAssertElementIsVisuallyHidden(string $selector, int $offset = 0): void {
    if ($this->visibilityElementIsVisuallyVisible($selector, $offset)) {
      throw new \Exception(sprintf('Element(s) defined by "%s" selector is displayed within a viewport, but should not be.', $selector));
    }
  }

  /**
   * Check if an element is displayed withing a viewport using different FE techniques.
   *
   * @param string $selector
   *   CSS query selector.
   * @param int $offset
   *   (optional) Vertical element offset in pixels. Defaults to 0.
   *
   * @return bool
   *   TRUE if an element is displayed withing a viewport, FALSE if not.
   */
  protected function visibilityElementIsVisuallyVisible(string $selector, int $offset) {
    // The contents of this JS function should be copied as-is from the <script>
    // section in the bottom of the tests/behat/fixtures/relative.html file.
    $scriptFunction = <<<JS
      function isElemVisible(selector, offset = 0) {
        var failures = [];
        document.querySelectorAll(selector).forEach(function (el) {
          // Inject a style to disable scrollbars for more consistent results.
          if (document.querySelectorAll('head #relative_style').length === 0) {
            document.querySelector('head').insertAdjacentHTML('beforeend', '<style id="relative_style" type="text/css">::-webkit-scrollbar{display: none;}</style>');
          }

          // Scroll to the element top, accounting for an offset.
          window.scroll({top: el.offsetTop + offset});

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

          if (!isVisible || !hasHeight || !notClipped || !onScreen) {
            failures.push(el);
          }
        });

        return failures.length === 0;
      }
    JS;

    // Include and call visibility assertion function.
    $script = <<<JS
      (function() {
        {$scriptFunction}
        return isElemVisible('{$selector}', {$offset});
      })();
    JS;

    return $this->getSession()->getDriver()->evaluateScript($script);
  }

}
