<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait Metatag.
 *
 * Steps to work with Metatag.
 *
 * @package DrevOps\BehatSteps
 */
trait MetaTagTrait {

  /**
   * Assert that a meta tag with specific attributes and values exists.
   *
   * @Then I should see a meta tag with the following attributes:
   */
  public function assertMetaTagWithAttributesExists(TableNode $table): void {
    $page = $this->getSession()->getPage();
    $meta_tags = $page->findAll('css', 'meta');

    $attributes = [];
    foreach ($table->getRowsHash() as $attribute => $value) {
      $attributes[$attribute] = $value;
    }

    $meta_tag_found = FALSE;

    foreach ($meta_tags as $metaTag) {
      $all_attributes_match = TRUE;
      foreach ($attributes as $attribute => $value) {
        if ($metaTag->getAttribute($attribute) !== $value) {
          $all_attributes_match = FALSE;
          break;
        }
      }
      if ($all_attributes_match) {
        $meta_tag_found = TRUE;
        break;
      }
    }

    if (!$meta_tag_found) {
      throw new \Exception('Meta tag with specified attributes was not found: ' . json_encode($attributes));
    }
  }

  /**
   * Assert that a meta tag with specific attributes and values does not exist.
   *
   * @Then I should not see a meta tag with the following attributes:
   */
  public function assertMetaTagWithAttributesDoesNotExists(TableNode $table): void {
    $page = $this->getSession()->getPage();
    $meta_tags = $page->findAll('css', 'meta');

    $attributes = [];
    foreach ($table->getRowsHash() as $attribute => $value) {
      $attributes[$attribute] = $value;
    }

    foreach ($meta_tags as $metaTag) {
      $all_attributes_match = TRUE;
      foreach ($attributes as $attribute => $value) {
        if ($metaTag->getAttribute($attribute) !== $value) {
          $all_attributes_match = FALSE;
          break;
        }
      }
      if ($all_attributes_match) {
        throw new \Exception('Meta tag with specified attributes should not exist: ' . json_encode($attributes));
      }
    }
  }

}
