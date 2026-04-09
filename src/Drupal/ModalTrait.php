<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Interact with and assert Drupal modal dialogs.
 *
 * - Assert modal dialog visibility.
 * - Assert modal dialog content.
 * - Interact with modal dialog buttons.
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
    $selector = $this->modalGetSelector();
    $element = $this->getSession()->getPage()->find('css', $selector);

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
    $selector = $this->modalGetSelector();
    $element = $this->getSession()->getPage()->find('css', $selector);

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
    $selector = $this->modalGetContentSelector();
    $element = $this->getSession()->getPage()->find('css', $selector);

    if ($element === NULL) {
      throw new ExpectationException('The modal dialog content element was not found on the page.', $this->getSession()->getDriver());
    }

    $actual_text = $element->getText();

    if (!str_contains($actual_text, $text)) {
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
    $selector = $this->modalGetContentSelector();
    $element = $this->getSession()->getPage()->find('css', $selector);

    if ($element === NULL) {
      return;
    }

    $actual_text = $element->getText();

    if (str_contains($actual_text, $text)) {
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
    $selector = $this->modalGetCloseSelector();
    $element = $this->getSession()->getPage()->find('css', $selector);

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
    $selector = $this->modalGetSelector();
    $dialog = $this->getSession()->getPage()->find('css', $selector);

    if ($dialog === NULL) {
      throw new ExpectationException('The modal dialog was not found on the page.', $this->getSession()->getDriver());
    }

    $button_element = $dialog->findButton($button);

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
    $selector = $this->modalGetSelector();
    $timeout = $this->modalGetWaitTimeout();

    $result = $this->getSession()->getPage()->waitFor($timeout, function () use ($selector) {
      $element = $this->getSession()->getPage()->find('css', $selector);

      return $element !== NULL && $element->isVisible();
    });

    if (!$result) {
      throw new ExpectationException(sprintf('The modal dialog did not appear within %d seconds.', $timeout), $this->getSession()->getDriver());
    }
  }

  /**
   * Get the CSS selector for the modal dialog container.
   */
  protected function modalGetSelector(): string {
    return '.ui-dialog';
  }

  /**
   * Get the CSS selector for the modal dialog content.
   */
  protected function modalGetContentSelector(): string {
    return '.ui-dialog-content';
  }

  /**
   * Get the CSS selector for the modal dialog button pane.
   */
  protected function modalGetButtonSelector(): string {
    return '.ui-dialog-buttonpane .button';
  }

  /**
   * Get the CSS selector for the modal dialog close button.
   */
  protected function modalGetCloseSelector(): string {
    return '.ui-dialog-titlebar-close';
  }

  /**
   * Get the timeout in seconds for waiting for the modal dialog.
   */
  protected function modalGetWaitTimeout(): int {
    return 10;
  }

}
