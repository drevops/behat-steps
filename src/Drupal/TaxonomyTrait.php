<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Manage Drupal taxonomy terms with vocabulary organization.
 *
 * - Create term vocabulary structures using field values.
 * - Navigate to term pages
 * - Verify vocabulary configurations.
 */
trait TaxonomyTrait {

  /**
   * {@inheritdoc}
   */
  public function createTerms(mixed $vocabulary, TableNode $table): void {
    $vocabulary = (string) $vocabulary;
    // Delete entities before creating them.
    $this->taxonomyDeleteTerms($vocabulary, $table);
    parent::createTerms($vocabulary, $table);
  }

  /**
   * Remove terms from a specified vocabulary.
   *
   * @code
   * Given the following "fruits" vocabulary terms do not exist:
   *   | Apple |
   *   | Pear  |
   * @endcode
   *
   * @Given the following :vocabulary_machine_name vocabulary terms do not exist:
   */
  public function taxonomyDeleteTerms(string $vocabulary_machine_name, TableNode $terms_table): void {
    $vocab = Vocabulary::load($vocabulary_machine_name);

    if (!$vocab) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary_machine_name));
    }

    foreach ($terms_table->getColumn(0) as $term_name) {
      $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties([
        'name' => $term_name,
        'vid' => $vocabulary_machine_name,
      ]);

      /** @var \Drupal\taxonomy\Entity\Term $term */
      foreach ($terms as $term) {
        $term->delete();
      }
    }
  }

  /**
   * Assert that a vocabulary with a specific name exists.
   *
   * @code
   * Then the vocabulary "topics" with the name "Topics" should exist
   * @endcode
   *
   * @Then the vocabulary :machine_name with the name :name should exist
   */
  public function taxonomyAssertVocabularyExist(string $machine_name, string $name): void {
    $vocab = Vocabulary::load($machine_name);

    if (!$vocab) {
      throw new \Exception(sprintf('The vocabulary "%s" does not exist.', $machine_name));
    }

    $actual_name = $vocab->get('name');
    if ($actual_name != $name) {
      throw new \Exception(sprintf('The vocabulary "%s" exists with a name "%s", but expected "%s".', $machine_name, $actual_name, $name));
    }
  }

  /**
   * Assert that a vocabulary with a specific name does not exist.
   *
   * @code
   * Then the vocabulary "topics" should not exist
   * @endcode
   *
   * @Then the vocabulary :machine_name should not exist
   */
  public function taxonomyAssertVocabularyNotExist(string $machine_name): void {
    $vocab = Vocabulary::load($machine_name);

    if ($vocab) {
      throw new \Exception(sprintf('The vocabulary "%s" exist, but it should not.', $machine_name));
    }
  }

  /**
   * Assert that a taxonomy term exist by name.
   *
   * @code
   * Then the taxonomy term "Apple" from the vocabulary "Fruits" should exist
   * @endcode
   *
   * @Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist
   */
  public function taxonomyAssertTermExistsByName(string $term_name, string $vocabulary_machine_name): void {
    $vocab = Vocabulary::load($vocabulary_machine_name);

    if (!$vocab) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary_machine_name));
    }

    $found = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'name' => $term_name,
        'vid' => $vocabulary_machine_name,
      ]);

    if (count($found) == 0) {
      throw new \Exception(sprintf('The taxonomy term "%s" from the vocabulary "%s" does not exist.', $term_name, $vocabulary_machine_name));
    }
  }

  /**
   * Assert that a taxonomy term does not exist by name.
   *
   * @code
   * Then the taxonomy term "Apple" from the vocabulary "Fruits" should not exist
   * @endcode
   *
   * @Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist
   */
  public function taxonomyAssertTermNotExistsByName(string $term_name, string $vocabulary_machine_name): void {
    $vocab = Vocabulary::load($vocabulary_machine_name);

    if (!$vocab) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary_machine_name));
    }

    $found = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'name' => $term_name,
        'vid' => $vocabulary_machine_name,
      ]);

    if (count($found) > 0) {
      throw new \Exception(sprintf('The taxonomy term "%s" from the vocabulary "%s" exists, but it should not.', $term_name, $vocabulary_machine_name));
    }
  }

  /**
   * Visit specified vocabulary term page.
   *
   * @code
   * When I visit the "fruits" term page with the name "Apple"
   * @endcode
   *
   * @When I visit the :vocabulary_machine_name term page with the name :term_name
   */
  public function taxonomyVisitTermPageWithName(string $vocabulary_machine_name, string $term_name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary_machine_name, $term_name);
  }

  /**
   * Visit specified vocabulary term edit page.
   *
   * @code
   * When I visit the "fruits" term edit page with the name "Apple"
   * @endcode
   *
   * @When I visit the :vocabulary_machine_name term edit page with the name :term_name
   */
  public function taxonomyVisitTermEditPageWithName(string $vocabulary_machine_name, string $term_name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary_machine_name, $term_name, '/edit');
  }

  /**
   * Visit specified vocabulary term delete page.
   *
   * @code
   * When I visit the "tags" term delete page with the name "[TEST] Remove"
   * @endcode
   *
   * @When I visit the :vocabulary_machine_name term delete page with the name :term_name
   */
  public function taxonomyVisitTermDeletePageWithName(string $vocabulary_machine_name, string $term_name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary_machine_name, $term_name, '/delete');
  }

  /**
   * Visit the action page of the term with a specified name.
   *
   * @param string $vocabulary_machine_name
   *   The term vocabulary machine name.
   * @param string $term_name
   *   The name of the term.
   * @param string $action_subpath
   *   The operation to perform, e.g., '/delete', '/edit', etc.
   */
  protected function taxonomyVisitActionPageWithName(string $vocabulary_machine_name, string $term_name, string $action_subpath = ''): void {
    $vocab = Vocabulary::load($vocabulary_machine_name);

    if (!$vocab) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary_machine_name));
    }

    $tids = $this->taxonomyLoadMultiple($vocabulary_machine_name, [
      'name' => $term_name,
    ]);

    if (empty($tids)) {
      throw new \RuntimeException(sprintf('Unable to find the term "%s" in the vocabulary "%s".', $term_name, $vocabulary_machine_name));
    }

    ksort($tids);
    $tid = end($tids);

    $path = $this->locatePath('/taxonomy/term/' . $tid . $action_subpath);

    $this->getSession()->visit($path);
  }

  /**
   * Load multiple terms with specified vocabulary and conditions.
   *
   * @param string $vocabulary_machine_name
   *   The term vocabulary.
   * @param array<string,string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of term ids.
   */
  protected function taxonomyLoadMultiple(string $vocabulary_machine_name, array $conditions = []): array {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', $vocabulary_machine_name);

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

}
