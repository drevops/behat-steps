<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Interact with and assert Drupal modal dialogs.
 *
 * - Assert modal dialog visibility.
 * - Assert modal dialog content.
 * - Interact with modal dialog buttons.
 *
 * Supports multiple modal implementations (jQuery UI dialogs, Bootstrap
 * modals, custom dialogs) via overridable selector methods.
 */
trait ModalTrait {

  /**
   * Assert that the modal dialog is visible.
   *
   * @code
   * Then I should see the modal dialog
   * @endcode
   *
   * @javascript
   */
  #[Then('I should see the modal dialog')]
  public function modalAssertVisible(): void {
    $element = $this->modalFindDialog();

    if ($element === NULL || !$element->isVisible()) {
      throw new ExpectationException('The modal dialog is not visible on the page.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the modal dialog is not visible.
   *
   * @code
   * Then I should not see the modal dialog
   * @endcode
   *
   * @javascript
   */
  #[Then('I should not see the modal dialog')]
  public function modalAssertNotVisible(): void {
    $element = $this->modalFindDialog();

    if ($element !== NULL && $element->isVisible()) {
      throw new ExpectationException('The modal dialog is visible on the page, but it should not be.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the modal dialog contains text.
   *
   * @code
   * Then the modal dialog should contain "Welcome message"
   * @endcode
   *
   * @javascript
   */
  #[Then('the modal dialog should contain :text')]
  public function modalAssertContains(string $text): void {
    $element = $this->modalFindContent();

    if ($element === NULL) {
      throw new ExpectationException('The modal dialog content element was not found on the page.', $this->getSession()->getDriver());
    }

    $actual_text = $element->getText();

    if (!str_contains((string) $actual_text, $text)) {
      throw new ExpectationException(sprintf('The modal dialog does not contain the text "%s". Actual text: "%s".', $text, $actual_text), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the modal dialog does not contain text.
   *
   * @code
   * Then the modal dialog should not contain "Error message"
   * @endcode
   *
   * @javascript
   */
  #[Then('the modal dialog should not contain :text')]
  public function modalAssertNotContains(string $text): void {
    $element = $this->modalFindContent();

    if ($element === NULL) {
      throw new ExpectationException('The modal dialog content element was not found on the page.', $this->getSession()->getDriver());
    }

    $actual_text = $element->getText();

    if (str_contains((string) $actual_text, $text)) {
      throw new ExpectationException(sprintf('The modal dialog contains the text "%s", but it should not.', $text), $this->getSession()->getDriver());
    }
  }

  /**
   * Close the modal dialog by clicking the close button.
   *
   * @code
   * When I close the modal dialog
   * @endcode
   *
   * @javascript
   */
  #[When('I close the modal dialog')]
  public function modalClose(): void {
    $dialog = $this->modalFindDialog();

    if ($dialog === NULL) {
      throw new ExpectationException('The modal dialog was not found on the page.', $this->getSession()->getDriver());
    }

    $element = $this->modalFindElementIn($dialog, $this->modalGetCloseSelectors());

    if ($element === NULL) {
      throw new ExpectationException('The modal dialog close button was not found on the page.', $this->getSession()->getDriver());
    }

    $element->click();
  }

  /**
   * Click a button in the modal dialog.
   *
   * @code
   * When I click "Save" in the modal dialog
   * @endcode
   *
   * @javascript
   */
  #[When('I click :button in the modal dialog')]
  public function modalClickButton(string $button): void {
    $dialog = $this->modalFindDialog();

    if ($dialog === NULL) {
      throw new ExpectationException('The modal dialog was not found on the page.', $this->getSession()->getDriver());
    }

    $button_element = NULL;

    foreach ($this->modalGetButtonSelectors() as $button_selector) {
      foreach ($dialog->findAll('css', $button_selector) as $candidate) {
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
      $fallback = $dialog->findButton($button);
      if ($fallback !== NULL && $fallback->isVisible()) {
        $button_element = $fallback;
      }
    }

    if ($button_element === NULL) {
      throw new ExpectationException(sprintf('The button "%s" was not found in the modal dialog.', $button), $this->getSession()->getDriver());
    }

    $button_element->click();
  }

  /**
   * Wait for the modal dialog to appear.
   *
   * @code
   * When I wait for the modal dialog to appear
   * @endcode
   *
   * @javascript
   */
  #[When('I wait for the modal dialog to appear')]
  public function modalWaitForAppear(): void {
    $timeout = $this->modalGetWaitTimeout();

    $result = $this->getSession()->getPage()->waitFor($timeout, function (): bool {
      $element = $this->modalFindDialog();

      return $element !== NULL && $element->isVisible();
    });

    if (!$result) {
      throw new ExpectationException(sprintf('The modal dialog did not appear within %d seconds.', $timeout), $this->getSession()->getDriver());
    }
  }

  /**
   * Get the CSS selectors for the modal dialog container.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  protected function modalGetSelectors(): array {
    return ['.ui-dialog', '.dialog'];
  }

  /**
   * Get the CSS selectors for the modal dialog content.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  protected function modalGetContentSelectors(): array {
    return ['.ui-dialog-content', '.dialog-content'];
  }

  /**
   * Get the CSS selectors for modal dialog buttons.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  protected function modalGetButtonSelectors(): array {
    return ['.ui-dialog-buttonpane button', '.dialog-actions button'];
  }

  /**
   * Get the CSS selectors for the modal dialog close button.
   *
   * @return array<string>
   *   An array of CSS selectors to try, in order.
   */
  protected function modalGetCloseSelectors(): array {
    return ['.ui-dialog-titlebar-close', '.dialog-close'];
  }

  /**
   * Get the timeout in seconds for waiting for the modal dialog.
   */
  protected function modalGetWaitTimeout(): int {
    return 10;
  }

  /**
   * Find the first visible modal dialog element.
   *
   * Prefers visible elements over hidden ones to avoid matching stale dialogs.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The dialog element, or NULL if not found.
   */
  protected function modalFindDialog(): ?NodeElement {
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
   * Find the modal dialog content element scoped to the resolved dialog.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The content element, or NULL if not found.
   */
  protected function modalFindContent(): ?NodeElement {
    $dialog = $this->modalFindDialog();

    if ($dialog === NULL) {
      return NULL;
    }

    return $this->modalFindElementIn($dialog, $this->modalGetContentSelectors());
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
