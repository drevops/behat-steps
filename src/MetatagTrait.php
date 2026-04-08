<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Step\Then;
use Behat\Gherkin\Node\TableNode;

/**
 * Assert `<meta>` tags in page markup.
 *
 * - Assert presence and content of meta tags with proper attribute handling.
 * - Verify meta tag content is free of HTML markup.
 */
trait MetatagTrait {

  /**
   * Assert that a meta tag with specific attributes and values exists.
   *
   * @code
   * Then the meta tag should exist with the following attributes:
   *   | name    | description          |
   *   | content | My page description  |
   * @endcode
   */
  #[Then('the meta tag should exist with the following attributes:')]
  public function metatagAssertWithAttributesExists(TableNode $table): void {
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
      throw new \Exception('Meta tag with specified attributes was not found: ' . json_encode($attributes) . '.');
    }
  }

  /**
   * Assert that a meta tag with specific attributes and values does not exist.
   *
   * @code
   * Then the meta tag should not exist with the following attributes:
   *   | name    | nonexistent          |
   *   | content | Some content         |
   * @endcode
   */
  #[Then('the meta tag should not exist with the following attributes:')]
  public function metatagAssertWithAttributesNotExists(TableNode $table): void {
    $meta_tags = $this->getSession()->getPage()->findAll('css', 'meta');

    $attributes = [];
    foreach ($table->getRowsHash() as $attribute => $value) {
      $attributes[$attribute] = $value;
    }

    foreach ($meta_tags as $meta_tag) {
      $all_attributes_matched = TRUE;
      foreach ($attributes as $attribute => $value) {
        if ($meta_tag->getAttribute($attribute) !== $value) {
          $all_attributes_matched = FALSE;
          break;
        }
      }

      if ($all_attributes_matched) {
        throw new \Exception('Meta tag with specified attributes should not exist: ' . json_encode($attributes) . '.');
      }
    }
  }

  /**
   * Assert a meta tag does not contain HTML tags.
   *
   * Looks up the meta tag by either "name" or "property" attribute and checks
   * that the "content" attribute value is free of HTML markup.
   *
   * @code
   * Then the "og:description" meta tag should not contain any HTML tags
   * Then the "description" meta tag should not contain any HTML tags
   * @endcode
   */
  #[Then('the :metaName meta tag should not contain any HTML tags')]
  public function metatagAssertNoHtml(string $meta_name): void {
    $page = $this->getSession()->getPage();

    $meta_tag = $page->find('xpath', sprintf(
      "//meta[@name='%s' or @property='%s']",
      $meta_name,
      $meta_name
    ));

    if ($meta_tag === NULL) {
      throw new \Exception(sprintf('Meta tag with name or property "%s" not found.', $meta_name));
    }

    $content = (string) $meta_tag->getAttribute('content');

    if ($content !== strip_tags($content)) {
      throw new \Exception(sprintf('The "%s" meta tag contains HTML tags: %s', $meta_name, $content));
    }
  }

}
