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

    return $optionElement;
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
   * @Then select :select should have option :option selected
   */
  public function selectShouldHaveOptionSelected($select, $option) {
    $optionElement = $this->selectShouldHaveOption($select, $option);
    if ($optionElement->getAttribute('selected') !== 'selected') {
      throw new \InvalidArgumentException(sprintf('Option "%s" in select "%s" is not selected.', $option, $select));
    }
  }

  /**
   * @Then select :select should not have option :option selected
   */
  public function selectShouldHaveNotOptionSelected($select, $option) {
    $optionElement = $this->selectShouldHaveOption($select, $option);
    if ($optionElement->getAttribute('selected') === 'selected') {
      throw new \InvalidArgumentException(sprintf('Option "%s" in select "%s" is selected, but should not.', $option, $select));
    }
  }

}
