<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Element\NodeElement;

/**
 * Interact with checkbox form elements.
 *
 * - Ensure checkboxes are checked or unchecked regardless of current state.
 * - Provides intuitive steps for conditional checkbox manipulation.
 */
trait CheckboxTrait {

  /**
   * Find a checkbox by its label and validate it exists.
   *
   * @param string $label
   *   The checkbox label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The checkbox element.
   *
   * @throws \Exception
   *   When the checkbox is not found.
   */
  private function findCheckboxByLabel(string $label): NodeElement {
    $page = $this->getSession()->getPage();
    $checkbox = $page->findField($label);
    if (!$checkbox) {
      throw new \Exception(sprintf('The checkbox with label "%s" was not found on the page.', $label));
    }
    return $checkbox;
  }

  /**
   * Check the box only if it's unchecked.
   *
   * This step ensures that a checkbox is checked, but doesn't change it if it's
   * already checked. This is useful for cases where you want to make sure a
   * checkbox is checked without having to check its current state first.
   *
   * @code
   * Given I ensure the box "Accept terms and conditions" is checked
   * @endcode
   *
   * @Given I ensure the box :label is checked
   */
  public function checkboxEnsureIsChecked(string $label): void {
    $checkbox = $this->findCheckboxByLabel($label);

    if (!$checkbox->isChecked()) {
      $checkbox->check();
    }
  }

  /**
   * Uncheck the box only if it's checked.
   *
   * This step ensures that a checkbox is unchecked, but doesn't change it if it's
   * already unchecked. This is useful for cases where you want to make sure a
   * checkbox is unchecked without having to check its current state first.
   *
   * @code
   * Given I ensure the box "Subscribe to newsletter" is unchecked
   * @endcode
   *
   * @Given I ensure the box :label is unchecked
   */
  public function checkboxEnsureIsUnchecked(string $label): void {
    $checkbox = $this->findCheckboxByLabel($label);

    if ($checkbox->isChecked()) {
      $checkbox->uncheck();
    }
  }

}
