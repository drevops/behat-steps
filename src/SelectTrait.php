<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait SelectTrait.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait SelectTrait {

  /**
   * @Then select :select should have an option :option
   */
  public function selectShouldHaveOption($select, $option) {
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
   * @Then select :select should not have an option :option
   */
  public function selectShouldNotHaveOption($select, $option) {
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
   * @Then /^the option "([^"]*)" from select "([^"]*)" is selected$/
   */
  public function theOptionFromSelectIsSelected($optionValue, $select) {
    $selectField = $this->getSession()->getPage()->findField($select);
    $currentUrl = $this->getSession()->getCurrentUrl();

    if (!$selectField) {
      throw new \Exception(sprintf('The select "%s" was not found on the page %s', $select, $currentUrl));
    }

    $optionField = $selectField->find('named', [
      'option',
      $optionValue,
    ]);

    if (!$optionField) {
      throw new \Exception(sprintf('No option is selected in the %s select on the page %s', $select, $currentUrl));
    }

    if (!$optionField->isSelected()) {
      throw new \Exception(sprintf('The option "%s" was not selected on the page %s', $optionValue, $currentUrl));
    }
  }

}
