<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Gherkin\Node\TableNode;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Trait TaxonomyTrait.
 */
trait TaxonomyTrait {

  /**
   * Assert that a vocabulary exist.
   *
   * @Given vocabulary :vid with name :name exists
   */
  public function taxonomyAssertVocabularyExist($name, $vid) {
    $vocab = Vocabulary::load($vid);
    if (!$vocab) {
      throw new \RuntimeException(sprintf('"%s" vocabulary does not exist', $vid));
    }
    elseif ($vocab->get('name') != $name) {
      throw new \RuntimeException(sprintf('"%s" vocabulary name is not "%s"', $vid, $name));
    }
  }

  /**
   * Assert that a taxonomy term exist by name.
   *
   * @Given taxonomy term :name from vocabulary :vocabulary_id exists
   */
  public function taxonomyAssertTermExistsByName($name, $vid) {
    $vocab = Vocabulary::load($vid);
    if (!$vocab) {
      throw new \RuntimeException(sprintf('"%s" vocabulary does not exist', $vid));
    }
    $found = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'name' => $name,
        'vid' => $vid,
      ]);

    if (count($found) == 0) {
      throw new \Exception(sprintf('Taxonomy term "%s" from vocabulary "%s" does not exist', $name, $vid));
    }
  }

  /**
   * @Given no :vocabulary terms:
   */
  public function taxonomyDeleteTerms($vocabulary, TableNode $termsTable) {
    foreach ($termsTable->getColumn(0) as $name) {
      $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties(['name' => $name, 'vid' => $vocabulary]);
      /** @var \Drupal\taxonomy\Entity\Term $term */
      foreach ($terms as $term) {
        $term->delete();
      }
    }
  }

}
