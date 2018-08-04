<?php

namespace IntegratedExperts\BehatSteps\D7;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait TaxonomyTrait.
 */
trait TaxonomyTrait {

  /**
   * @Given no :vocabulary terms:
   */
  public function taxonomyRemoveTerms($vocabulary, TableNode $termsTable) {
    $vocab = taxonomy_vocabulary_machine_name_load($vocabulary);
    foreach ($termsTable->getColumn(0) as $name) {
      $terms = taxonomy_term_load_multiple([], [
        'name' => $name,
        'vid' => $vocab->vid,
      ]);

      foreach ($terms as $term) {
        taxonomy_term_delete($term->tid);
      }
    }
  }

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

    $term = reset($found);

    return $term;
  }

  /**
   * @Given :node_title has :field_name field populated with( the following) terms from :vocabulary( vocabulary):
   */
  public function taxonomyNodeHasTaxonomyField($node_title, $field_name, $vocabulary, TableNode $table) {
    $term_names = $table->getColumn(0);

    $node = node_load_multiple(NULL, [
      'title' => $node_title,
    ]);
    $node = reset($node);

    $field_terms = [];
    foreach ($node->{$field_name}[LANGUAGE_NONE] as $value) {
      $term = taxonomy_term_load($value['tid']);
      $field_terms[] = $term->name;
    }

    $diff_provided = array_diff($term_names, $field_terms);
    $diff_actual = array_diff($field_terms, $term_names);

    if (count($diff_provided) > 0 || count($diff_actual) > 0) {
      $message = '';
      if (count($diff_provided) > 0) {
        $message .= sprintf('Missing expected terms: %s', implode(', ', $diff_provided));
      }
      if (count($diff_provided) > 0) {
        $message .= (strlen($message) > 0 ? ' ' : '') . sprintf('More terms exist then expected: %s', implode(', ', $diff_actual));
      }
      throw new \Exception($message);
    }
  }

  /**
   * @Given /^"(?P<term_name>[^"]*)" in "(?P<vocabulary>[^"]*)" vocabulary has parent "(?P<parent_term_name>[^"]*)"( and depth "(?P<depth>[^"]*)")?$/
   */
  public function taxonomyTermHasNodeParent($term_name, $vocabulary, $parent_term_name, $depth = NULL) {
    $term = $this->taxonomyAssertTermExistsByName($term_name, $vocabulary);
    $parent_term = $this->taxonomyAssertTermExistsByName($parent_term_name, $vocabulary);

    $parents = taxonomy_get_parents_all($term->tid);
    if (!in_array($parent_term, $parents)) {
      throw new \Exception(sprintf('Expected parent term "%s" is not found among parents of term "%s"', $parent_term_name, $term_name));
    }

    if (!is_null($depth)) {
      $vocab = taxonomy_vocabulary_machine_name_load($vocabulary);
      $tree = taxonomy_get_tree($vocab->vid);
      foreach ($tree as $leaf) {
        if ($term->tid == $leaf->tid) {
          $term = $leaf;
          if ($term->depth != $depth) {
            throw new \Exception(sprintf('Term "%s" has actual depth of "%s" but expected "%s"', $term_name, $term->depth, $depth));
          }
          break;
        }
      }
    }
  }

}
