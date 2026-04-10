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
   * Click a button in the modal.
   *
   * @code
   * When I click "Save" in the modal
   * @endcode
   *
   * @javascript
   */
  #[When('I click :button in the modal')]
  public function modalClickButton(string $button): void {
    $modal = $this->modalFindVisible();
    $button_element = NULL;

    foreach ($this->modalGetButtonSelectors() as $button_selector) {
      foreach ($modal->findAll('css', $button_selector) as $candidate) {
        if (!$candidate->isVisible()) {
          continue;
        }
        $candidate_text = trim((string) $candidate->getText());
        $candidate_value = trim((string) $candidate->getAttribute('value'));
        if ($candidate_text === $button || $candidate_value === $button) {
          $button_element = $candidate;
          break 2;
        }
      }
    }

    if ($button_element === NULL) {
      $fallback = $modal->findButton($button);
      if ($fallback !== NULL && $fallback->isVisible()) {
        $button_element = $fallback;
      }
    }

    if ($button_element === NULL) {
      throw new ExpectationException(sprintf('The button "%s" was not found in the modal.', $button), $this->getSession()->getDriver());
    }

    $button_element->click();
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
   * Get the CSS selectors for modal buttons.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  protected function modalGetButtonSelectors(): array {
    return ['.ui-dialog-buttonpane button', '.modal-footer button'];
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
   * Find a modal element on the page.
   *
   * When $only_visible is TRUE (default), returns the first visible modal
   * and falls back to the first DOM match if none are visible. When FALSE,
   * returns the first DOM match regardless of visibility.
   *
   * @param bool $only_visible
   *   Whether to prefer visible modals over hidden ones.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The modal element, or NULL if not found.
   */
  protected function modalFind(bool $only_visible = TRUE): ?NodeElement {
    $page = $this->getSession()->getPage();

    foreach ($this->modalGetSelectors() as $selector) {
      $first_match = NULL;
      foreach ($page->findAll('css', $selector) as $candidate) {
        $first_match ??= $candidate;
        if ($only_visible && $candidate->isVisible()) {
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
