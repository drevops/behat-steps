<?php

namespace IntegratedExperts\BehatSteps\D7;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait TaxonomyTrait.
 */
trait TaxonomyTrait {

  /**
   * @Given taxonomy term :name from vocabulary :vocab exists
   */
  public function taxonomyAssertTermExistsByName($name, $vocabulary) {
    $vocab = taxonomy_vocabulary_machine_name_load($vocabulary);
    if (!$vocab) {
      throw new RuntimeException(sprintf('"%s" vocabulary does not exist', $vocabulary));
    }
    $found = taxonomy_term_load_multiple(NULL, [
      'name' => $name,
      'vid' => $vocab->vid,
    ]);

    if (count($found) == 0) {
      throw new \Exception(printf('Taxonomy term "%s" from vocabulary "%s" does not exist', $name, $vocabulary));
    }
  }

  /**
   * @Given :node_title node has :field of :vocab vocabulary with taxonomies:
   */
  public function nodeHasTaxonomyField($node_title, $field, $vocabulary, TableNode $taxomies) {

    // Get term names from given table.
    $given_terms = $taxomies->getColumn(0);

    // Find previously created node title.
    $node = $this->getCurrentEntity([
      'title' => $node_title,
    ]);

    $i = 0;
    foreach ($node->{$field}->getIterator() as $delta => $term_wrapper) {
      $term_title = $term_wrapper->name->value();
      $this->taxonomyAssertTermExistsByName($term_title, $vocabulary);
      if (!in_array($term_title, $given_terms)) {
        throw new \Exception(sprintf('%s taxonomy not exist in %s node.', $given_terms[$delta], $node_title));
      }
      $i++;
    }
    if ($i != count($given_terms)) {
      throw new \Exception(sprintf('Wrong amount items'));
    }
  }

  /**
   * Find node using provided conditions.
   */
  protected function getCurrentEntity($conditions, $entity_type = 'node') {
    $entity_load_multiple = $entity_type . '_load_multiple';
    $entities = $entity_load_multiple(NULL, $conditions);

    if (empty($entities)) {
      throw new \Exception(sprintf('Unable to find %s that matches conditions: "%s"', $entity_type, print_r($conditions, TRUE)));
    }

    $entity = current($entities);

    return entity_metadata_wrapper($entity_type, $entity);
  }

  /**
   * @Given :taxonomy_child has parent :taxonomy_parent with :level level in :vocab vocabulary
   */
  public function taxonomyTermHasNodeParent($taxonomy_child, $taxonomy_parent, $level, $vocabulary) {

    $this->taxonomyAssertTermExistsByName($taxonomy_child, $vocabulary);

    // Return if term has not parent.
    if ($taxonomy_parent === '0') {
      return;
    }

    // Get taxonomy by given title.
    $target_terms = taxonomy_term_load_multiple(NULL, [
      'name' => $taxonomy_child,
    ]);

    // Get parent of current taxonomy.
    $parents = taxonomy_get_parents_all(key($target_terms));
    $target = next($parents);

    if ($target->name != $taxonomy_parent) {
      throw new \Exception(sprintf('%s has not %s as parent. It should be %s', $taxonomy_child, $taxonomy_parent, $target->name));
    }

  }

}
