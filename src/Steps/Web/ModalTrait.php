<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;

/**
 * Interact with and assert modals.
 *
 * - Assert modal visibility.
 * - Assert modal content.
 * - Interact with modal buttons.
 *
 * Supports multiple modal implementations (jQuery UI dialogs, Bootstrap
 * modals, native HTML dialog element, custom modals) via overridable
 * selector methods. All steps require a JavaScript-enabled browser driver.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait ModalTrait {

  /**
   * Close the modal by clicking the close button.
   *
   * @code
   * When I close the modal
   * @endcode
   *
   * @javascript
   */
  #[When('I close the modal')]
  public function modalClose(): void {
    $modal = $this->modalGetVisible();
    $close = $this->modalFindElementIn($modal, $this->modalGetCloseSelectors());

    if ($close === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'modal close button', 'css', implode(', ', $this->modalGetCloseSelectors()));
    }

    $close->click();
  }

  /**
   * Click an element in the modal by CSS selector, button label, or link text.
   *
   * Resolves the element in the following order:
   * 1. CSS selector (e.g., ".btn-save", "a.close").
   * 2. Button by id, name, value, or visible text (via Mink findButton).
   * 3. Link by visible text or title (via Mink findLink).
   *
   * @code
   * When I click on the element "Save" in the modal
   * When I click on the element ".btn-save" in the modal
   * When I click on the element "Cancel" in the modal
   * @endcode
   *
   * @javascript
   */
  #[When('I click on the element :selector in the modal')]
  public function modalClick(string $selector): void {
    $this->modalGetElement($selector)->click();
  }

  /**
   * Wait for the modal to appear.
   *
   * @code
   * When I wait for the modal to appear
   * @endcode
   *
   * @javascript
   */
  #[When('I wait for the modal to appear')]
  public function modalWaitForAppear(): void {
    $this->modalWaitForAppearWithin($this->modalGetWaitTimeout());
  }

  /**
   * Assert that the modal is visible.
   *
   * @code
   * Then the modal should be displayed
   * @endcode
   *
   * @javascript
   */
  #[Then('the modal should be displayed')]
  public function modalAssertVisible(): void {
    $modal = $this->modalFind();

    if ($modal === NULL || !$modal->isVisible()) {
      throw new ExpectationException('The modal is not visible on the page.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the modal is not visible.
   *
   * @code
   * Then the modal should not be displayed
   * @endcode
   *
   * @javascript
   */
  #[Then('the modal should not be displayed')]
  public function modalAssertNotVisible(): void {
    $modal = $this->modalFind();

    if ($modal !== NULL && $modal->isVisible()) {
      throw new ExpectationException('The modal is visible on the page, but it should not be.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the modal contains text.
   *
   * @code
   * Then the modal should contain "Welcome message"
   * @endcode
   *
   * @javascript
   */
  #[Then('the modal should contain :text')]
  public function modalAssertContains(string $text): void {
    $actual_text = $this->modalGetContent()->getText();

    if (!str_contains((string) $actual_text, $text)) {
      throw new ExpectationException(sprintf('The modal does not contain the text "%s". Actual text: "%s".', $text, $actual_text), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the modal does not contain text.
   *
   * @code
   * Then the modal should not contain "Error message"
   * @endcode
   *
   * @javascript
   */
  #[Then('the modal should not contain :text')]
  public function modalAssertNotContains(string $text): void {
    $actual_text = $this->modalGetContent()->getText();

    if (str_contains((string) $actual_text, $text)) {
      throw new ExpectationException(sprintf('The modal contains the text "%s", but it should not.', $text), $this->getSession()->getDriver());
    }
  }

  /**
   * Get the CSS selectors for the modal container.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  public function modalGetSelectors(): array {
    return $this->getOptionArray('modal', 'selectors');
  }

  /**
   * Get the CSS selectors for the modal content.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  public function modalGetContentSelectors(): array {
    return $this->getOptionArray('modal', 'content_selectors');
  }

  /**
   * Get the CSS selectors for the modal close button.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  public function modalGetCloseSelectors(): array {
    return $this->getOptionArray('modal', 'close_selectors');
  }

  /**
   * Get the timeout in seconds for waiting for the modal to appear.
   */
  public function modalGetWaitTimeout(): int {
    return $this->getOptionInt('modal', 'wait_timeout');
  }

  /**
   * Find the first visible modal, or fall back to the first DOM match.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The modal element, or NULL if not found.
   */
  public function modalFind(): ?NodeElement {
    $page = $this->getSession()->getPage();
    $first_match = NULL;

    // A visible modal from any selector outranks a hidden one from an earlier
    // selector, so every selector is examined before falling back. Libraries
    // such as jQuery UI leave their container in the DOM after closing, which
    // would otherwise mask a visible modal of a different kind.
    foreach ($this->modalGetSelectors() as $selector) {
      foreach ($page->findAll('css', $selector) as $candidate) {
        $first_match ??= $candidate;

        if ($candidate->isVisible()) {
          return $candidate;
        }
      }
    }

    return $first_match;
  }

  /**
   * Return the first visible modal.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The visible modal element.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no visible modal is found.
   */
  public function modalGetVisible(): NodeElement {
    $modal = $this->modalFind();

    if ($modal === NULL || !$modal->isVisible()) {
      throw new ExpectationException('The modal is not visible on the page.', $this->getSession()->getDriver());
    }

    return $modal;
  }

  /**
   * Get a visible element in the visible modal.
   *
   * The element is resolved by CSS selector, then as a button by id, name,
   * value or text, then as a link by text or title.
   *
   * @param string $selector
   *   The CSS selector, button label or link text.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The element.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no modal is visible.
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the modal holds no such visible element.
   */
  public function modalGetElement(string $selector): NodeElement {
    $modal = $this->modalGetVisible();

    $element = $modal->find('css', $selector);

    if ($element === NULL || !$element->isVisible()) {
      $element = $modal->findButton($selector);
    }

    if ($element === NULL || !$element->isVisible()) {
      $element = $modal->findLink($selector);
    }

    if ($element === NULL || !$element->isVisible()) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element in the modal', 'css|id|name|title|alt|value|text', $selector);
    }

    return $element;
  }

  /**
   * Get the content element of the visible modal.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The content element.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no modal is visible.
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the modal holds no element matching a content selector.
   */
  public function modalGetContent(): NodeElement {
    $content = $this->modalFindElementIn($this->modalGetVisible(), $this->modalGetContentSelectors());

    if ($content === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'modal content element', 'css', implode(', ', $this->modalGetContentSelectors()));
    }

    return $content;
  }

  /**
   * Wait for the modal to appear within a number of seconds.
   *
   * @param int $seconds
   *   The longest time to wait, in seconds.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When the browser driver cannot run JavaScript.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no modal is visible after the time is up.
   */
  public function modalWaitForAppearWithin(int $seconds): void {
    // Without the capability the wait can only time out, so the browser driver
    // is checked before the timeout is spent.
    $this->browserDriverFor(JavascriptCapabilityInterface::class);

    $result = $this->getSession()->getPage()->waitFor($seconds, function (): bool {
      $modal = $this->modalFind();

      return $modal !== NULL && $modal->isVisible();
    });

    if (!$result) {
      throw new ExpectationException(sprintf('The modal did not appear within %d seconds.', $seconds), $this->getSession()->getDriver());
    }
  }

  /**
   * Find the first matching element within a parent from a list of selectors.
   *
   * @param \Behat\Mink\Element\NodeElement $parent
   *   The parent element to search within.
   * @param array<string> $selectors
   *   CSS selectors to try, in order.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The first matching element, or NULL if none found.
   */
  protected function modalFindElementIn(NodeElement $parent, array $selectors): ?NodeElement {
    foreach ($selectors as $selector) {
      $element = $parent->find('css', $selector);

      if ($element !== NULL) {
        return $element;
      }
    }

    return NULL;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function modalConfigSchema(): array {
    return [
      new Option('selectors', default: ['.ui-dialog', 'dialog[open]', '.modal'], description: 'CSS selectors of the modal container, tried in order.'),
      new Option('content_selectors', default: ['.ui-dialog-content', '.modal-content', '.modal-body'], description: 'CSS selectors of the modal content element, tried in order.'),
      new Option('close_selectors', default: ['.ui-dialog-titlebar-close', '[data-dismiss="modal"]', '.btn-close'], description: 'CSS selectors of the modal close button, tried in order.'),
      new Option('wait_timeout', default: 3, description: 'Maximum time, in seconds, to wait for a modal to appear.'),
    ];
  }

}
