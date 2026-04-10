<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Interact with and assert modals.
 *
 * - Assert modal visibility.
 * - Assert modal content.
 * - Interact with modal buttons.
 *
 * Supports multiple modal implementations (jQuery UI dialogs, Bootstrap
 * modals, native HTML dialog element, custom modals) via overridable
 * selector methods. All steps require a JavaScript-enabled driver.
 */
trait ModalTrait {

  /**
   * Assert that the modal is visible.
   *
   * @code
   * Then I should see the modal
   * @endcode
   *
   * @javascript
   */
  #[Then('I should see the modal')]
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
   * Then I should not see the modal
   * @endcode
   *
   * @javascript
   */
  #[Then('I should not see the modal')]
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
    $modal = $this->modalFindVisible();
    $content = $this->modalFindElementIn($modal, $this->modalGetContentSelectors());

    if ($content === NULL) {
      throw new ExpectationException('The modal content element was not found.', $this->getSession()->getDriver());
    }

    $actual_text = $content->getText();

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
    $modal = $this->modalFindVisible();
    $content = $this->modalFindElementIn($modal, $this->modalGetContentSelectors());

    if ($content === NULL) {
      throw new ExpectationException('The modal content element was not found.', $this->getSession()->getDriver());
    }

    $actual_text = $content->getText();

    if (str_contains((string) $actual_text, $text)) {
      throw new ExpectationException(sprintf('The modal contains the text "%s", but it should not.', $text), $this->getSession()->getDriver());
    }
  }

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
    $modal = $this->modalFindVisible();
    $close = $this->modalFindElementIn($modal, $this->modalGetCloseSelectors());

    if ($close === NULL) {
      throw new ExpectationException('The modal close button was not found.', $this->getSession()->getDriver());
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
   * When I click "Save" in the modal
   * When I click ".btn-save" in the modal
   * When I click "Cancel" in the modal
   * @endcode
   *
   * @javascript
   */
  #[When('I click :selector in the modal')]
  public function modalClick(string $selector): void {
    $modal = $this->modalFindVisible();

    $element = $modal->find('css', $selector);

    if ($element === NULL || !$element->isVisible()) {
      $element = $modal->findButton($selector);
    }

    if ($element === NULL || !$element->isVisible()) {
      $element = $modal->findLink($selector);
    }

    if ($element === NULL || !$element->isVisible()) {
      throw new ExpectationException(sprintf('The element "%s" was not found in the modal.', $selector), $this->getSession()->getDriver());
    }

    $element->click();
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
    $timeout = $this->modalGetWaitTimeout();

    $result = $this->getSession()->getPage()->waitFor($timeout, function (): bool {
      $modal = $this->modalFind();

      return $modal !== NULL && $modal->isVisible();
    });

    if (!$result) {
      throw new ExpectationException(sprintf('The modal did not appear within %d seconds.', $timeout), $this->getSession()->getDriver());
    }
  }

  /**
   * Get the CSS selectors for the modal container.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  protected function modalGetSelectors(): array {
    return ['.ui-dialog', 'dialog[open]', '.modal'];
  }

  /**
   * Get the CSS selectors for the modal content.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  protected function modalGetContentSelectors(): array {
    return ['.ui-dialog-content', '.modal-content', '.modal-body'];
  }

  /**
   * Get the CSS selectors for the modal close button.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  protected function modalGetCloseSelectors(): array {
    return ['.ui-dialog-titlebar-close', '[data-dismiss="modal"]', '.btn-close'];
  }

  /**
   * Get the timeout in seconds for waiting for the modal to appear.
   */
  protected function modalGetWaitTimeout(): int {
    return 10;
  }

  /**
   * Find the first visible modal, or fall back to the first DOM match.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The modal element, or NULL if not found.
   */
  protected function modalFind(): ?NodeElement {
    $page = $this->getSession()->getPage();

    foreach ($this->modalGetSelectors() as $selector) {
      $first_match = NULL;
      foreach ($page->findAll('css', $selector) as $candidate) {
        $first_match ??= $candidate;
        if ($candidate->isVisible()) {
          return $candidate;
        }
      }
      if ($first_match !== NULL) {
        return $first_match;
      }
    }

    return NULL;
  }

  /**
   * Find the first visible modal or throw an exception.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The visible modal element.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no visible modal is found.
   */
  protected function modalFindVisible(): NodeElement {
    $modal = $this->modalFind();

    if ($modal === NULL || !$modal->isVisible()) {
      throw new ExpectationException('The modal is not visible on the page.', $this->getSession()->getDriver());
    }

    return $modal;
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

}
