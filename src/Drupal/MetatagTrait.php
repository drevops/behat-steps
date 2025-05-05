<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Gherkin\Node\TableNode;

/**
 * Assert `<meta>` tags in page markup.
 *
 * - Assert presence and content of meta tags with proper attribute handling.
 */
trait MetatagTrait {

  /**
   * Assert that a meta tag with specific attributes and values exists.
   *
   * @Then the meta tag should exist with the following attributes:
   */
  public function assertMetaTagWithAttributesExists(TableNode $table): void {
    $elements = $this->getSession()->getPage()->findAll('css', 'meta');

    $attributes = [];
    foreach ($table->getRowsHash() as $attribute => $value) {
      $attributes[$attribute] = $value;
    }

    $found = FALSE;

    foreach ($elements as $element) {
      $all_attributes_matched = TRUE;

      foreach ($attributes as $attribute => $value) {
        if ($element->getAttribute($attribute) !== $value) {
          $all_attributes_matched = FALSE;
          break;
        }
      }

      if ($all_attributes_matched) {
        $found = TRUE;
        break;
      }
    }

    if (!$found) {
      throw new \Exception('Meta tag with specified attributes was not found: ' . json_encode($attributes));
    }
  }

  /**
   * Assert that a meta tag with specific attributes and values does not exist.
   *
   * @Then the meta tag should not exist with the following attributes:
   */
  public function assertMetaTagWithAttributesDoesNotExists(TableNode $table): void {
    $meta_tags = $this->getSession()->getPage()->findAll('css', 'meta');

    $attributes = [];
    foreach ($table->getRowsHash() as $attribute => $value) {
      $attributes[$attribute] = $value;
    }

    foreach ($meta_tags as $metaTag) {
      $all_attributes_matched = TRUE;
      foreach ($attributes as $attribute => $value) {
        if ($metaTag->getAttribute($attribute) !== $value) {
          $all_attributes_matched = FALSE;
          break;
        }
      }

      if ($all_attributes_matched) {
        throw new \Exception('Meta tag with specified attributes should not exist: ' . json_encode($attributes));
      }
    }
  }

}
