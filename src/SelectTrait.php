<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

/**
 * Trait SelectTrait.
 *
 * Steps to work with HTML select element.
 *
 * @package DrevOps\BehatSteps
 */
trait SelectTrait {

  /**
   * Assert that a select has an option.
   *
   * @Then the option :option should exist within the select element :selector
   */
  public function selectShouldHaveOption(string $select, string $option): void {
    $selectElement = $this->getSession()->getPage()->findField($select);
    if (is_null($selectElement)) {
      throw new \InvalidArgumentException(sprintf('Element "%s" is not found.', $select));
    }

    $optionElement = $selectElement->find('named', ['option', $option]);
    if (is_null($optionElement)) {
      throw new \InvalidArgumentException(sprintf('Option "%s" is not found in select "%s".', $option, $select));
    }
  }

  /**
   * Assert that a select does not have an option.
   *
   * @Then the option :option should not exist within the select element :selector
   */
  public function selectShouldNotHaveOption(string $select, string $option): void {
    $selectElement = $this->getSession()->getPage()->findField($select);
    if (is_null($selectElement)) {
      throw new \InvalidArgumentException(sprintf('Element "%s" is not found.', $select));
    }

    $optionElement = $selectElement->find('named', ['option', $option]);
    if (!is_null($optionElement)) {
      throw new \InvalidArgumentException(sprintf('Option "%s" is found in select "%s", but should not.', $option, $select));
    }
  }

  /**
   * Assert that a select option is selected.
   *
   * @Then the option :option should be selected within the select element :selector
   */
  public function selectOptionSelected(string $value, string $select): void {
    $selectField = $this->getSession()->getPage()->findField($select);
    $currentUrl = $this->getSession()->getCurrentUrl();
    $path = parse_url((string) $currentUrl, PHP_URL_PATH);

    if (!$selectField) {
      throw new \Exception(sprintf('The select "%s" was not found on the page %s', $select, $path));
    }

    $optionField = $selectField->find('named', [
      'option',
      $value,
    ]);

    if (!$optionField) {
      throw new \Exception(sprintf('No option is selected in the %s select on the page %s', $select, $currentUrl));
    }

    if (!$optionField->isSelected()) {
      throw new \Exception(sprintf('The option "%s" was not selected on the page %s', $value, $currentUrl));
    }
  }

  /**
   * Assert that a select option is not selected.
   *
   * @Then the option :option should not be selected within the select element :selector
   */
  public function selectOptionNotSelected(string $value, string $select): void {
    $selectField = $this->getSession()->getPage()->findField($select);
    $currentUrl = $this->getSession()->getCurrentUrl();
    $path = parse_url((string) $currentUrl, PHP_URL_PATH);

    if (!$selectField) {
      throw new \Exception(sprintf('The select "%s" was not found on the page %s', $select, $path));
    }

    $optionField = $selectField->find('named', ['option', $value]);

    if (!$optionField) {
      throw new \Exception(sprintf('The option "%s" was not found in the select "%s" on the page %s', $value, $select, $path));
    }

    if ($optionField->isSelected()) {
      throw new \Exception(sprintf('The option "%s" was selected in the select "%s" on the page %s, but should not be', $value, $select, $path));
    }
  }

}
