<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Steps\Generic\HelperTrait;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Manage Drupal taxonomy terms with vocabulary organization.
 *
 * - Create term vocabulary structures using field values.
 * - Navigate to term pages
 * - Verify vocabulary configurations.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait TaxonomyTrait {

  use HelperTrait;

  /**
   * Create taxonomy terms with vertical field format.
   *
   * Supports both single and multiple entity creation using vertical table
   * format where fields are listed in rows instead of columns.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Vertical format table with field names in first column.
   *
   * @code
   *   Given the following tags terms with fields exist:
   *     | name        | [TEST] Behat    | [TEST] Testing  |
   *     | description | Testing tag     | QA tag          |
   * @endcode
   */
  #[Given('the following :vocabulary terms with fields exist:')]
  public function taxonomyCreateWithFields(string $vocabulary, TableNode $table): void {
    $entities = $this->helperTransposeVerticalTable($table);
    $horizontal_table = $this->helperBuildHorizontalTable($entities);
    $this->taxonomyCreate($vocabulary, $horizontal_table);
  }

  /**
   * Create taxonomy terms in a vocabulary from a table of field values.
   *
   * Each row becomes one term; each column is a base property or a field. The
   * vocabulary accepts either its machine name or its human label.
   *
   * @code
   *   Given the following tags terms exist:
   *     | name         | description |
   *     | [TEST] Behat | Testing tag |
   * @endcode
   */
  #[Given('the following :vocabulary terms exist:')]
  public function taxonomyCreate(string $vocabulary, TableNode $table): void {
    foreach ($table->getHash() as $values) {
      $values['vocabulary_machine_name'] = $vocabulary;
      $this->termCreate(new EntityStub('taxonomy_term', $vocabulary, $values));
    }
  }

  /**
   * Remove terms from a specified vocabulary.
   *
   * @code
   * Given the following "fruits" terms do not exist:
   *   | Apple |
   *   | Pear  |
   * @endcode
   */
  #[Given('the following :vocabulary terms do not exist:')]
  public function taxonomyDeleteTerms(string $vocabulary, TableNode $terms_table): void {
    $this->drupal();

    $vocab = Vocabulary::load($vocabulary);

    if (!$vocab) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary));
    }

    foreach ($terms_table->getColumn(0) as $term_name) {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'name' => $term_name,
        'vid' => $vocabulary,
      ]);

      /** @var \Drupal\taxonomy\Entity\Term $term */
      foreach ($terms as $term) {
        $term->delete();
      }
    }
  }

  /**
   * Visit specified vocabulary term page.
   *
   * @code
   * When I visit the "fruits" term page with the name "Apple"
   * @endcode
   */
  #[When('I visit the :vocabulary term page with the name :term_name')]
  public function taxonomyVisitTermPageWithName(string $vocabulary, string $term_name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary, $term_name);
  }

  /**
   * Visit specified vocabulary term edit page.
   *
   * @code
   * When I visit the "fruits" term edit page with the name "Apple"
   * @endcode
   */
  #[When('I visit the :vocabulary term edit page with the name :term_name')]
  public function taxonomyVisitTermEditPageWithName(string $vocabulary, string $term_name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary, $term_name, '/edit');
  }

  /**
   * Visit specified vocabulary term delete page.
   *
   * @code
   * When I visit the "tags" term delete page with the name "[TEST] Remove"
   * @endcode
   */
  #[When('I visit the :vocabulary term delete page with the name :term_name')]
  public function taxonomyVisitTermDeletePageWithName(string $vocabulary, string $term_name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary, $term_name, '/delete');
  }

  /**
   * Assert that a vocabulary with a specific name exists.
   *
   * @code
   * Then the vocabulary "topics" with the name "Topics" should exist
   * @endcode
   */
  #[Then('the vocabulary :vocabulary with the name :name should exist')]
  public function taxonomyAssertVocabularyExists(string $vocabulary, string $name): void {
    $this->drupal();

    $vocab = Vocabulary::load($vocabulary);

    if (!$vocab) {
      throw new ExpectationException(sprintf('The vocabulary "%s" does not exist.', $vocabulary), $this->getSession()->getDriver());
    }

    $actual_name = $vocab->get('name');
    if ($actual_name !== $name) {
      throw new ExpectationException(sprintf('The vocabulary "%s" exists with a name "%s", but expected "%s".', $vocabulary, $actual_name, $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a vocabulary with a specific name does not exist.
   *
   * @code
   * Then the vocabulary "topics" should not exist
   * @endcode
   */
  #[Then('the vocabulary :vocabulary should not exist')]
  public function taxonomyAssertVocabularyNotExists(string $vocabulary): void {
    $this->drupal();

    $vocab = Vocabulary::load($vocabulary);

    if ($vocab) {
      throw new ExpectationException(sprintf('The vocabulary "%s" exists, but it should not.', $vocabulary), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a taxonomy term exist by name.
   *
   * @code
   * Then the taxonomy term "Apple" from the vocabulary "Fruits" should exist
   * @endcode
   */
  #[Then('the taxonomy term :term_name from the vocabulary :vocabulary should exist')]
  public function taxonomyAssertTermExistsByName(string $term_name, string $vocabulary): void {
    $this->drupal();

    $vocab = Vocabulary::load($vocabulary);

    if (!$vocab) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary));
    }

    $found = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'name' => $term_name,
        'vid' => $vocabulary,
      ]);

    if (count($found) === 0) {
      throw new ExpectationException(sprintf('The taxonomy term "%s" from the vocabulary "%s" does not exist.', $term_name, $vocabulary), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a taxonomy term does not exist by name.
   *
   * @code
   * Then the taxonomy term "Apple" from the vocabulary "Fruits" should not exist
   * @endcode
   */
  #[Then('the taxonomy term :term_name from the vocabulary :vocabulary should not exist')]
  public function taxonomyAssertTermNotExistsByName(string $term_name, string $vocabulary): void {
    $this->drupal();

    $vocab = Vocabulary::load($vocabulary);

    if (!$vocab) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary));
    }

    $found = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'name' => $term_name,
        'vid' => $vocabulary,
      ]);

    if (count($found) > 0) {
      throw new ExpectationException(sprintf('The taxonomy term "%s" from the vocabulary "%s" exists, but it should not.', $term_name, $vocabulary), $this->getSession()->getDriver());
    }
  }

  /**
   * Visit the action page of the term with a specified name.
   *
   * @param string $vocabulary
   *   The term vocabulary machine name.
   * @param string $term_name
   *   The name of the term.
   * @param string $action_subpath
   *   The operation to perform, e.g., '/delete', '/edit', etc.
   */
  protected function taxonomyVisitActionPageWithName(string $vocabulary, string $term_name, string $action_subpath = ''): void {
    $this->drupal();

    $vocab = Vocabulary::load($vocabulary);

    if (!$vocab) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary));
    }

    $tids = $this->taxonomyLoadMultiple($vocabulary, [
      'name' => $term_name,
    ]);

    if (empty($tids)) {
      throw new \RuntimeException(sprintf('Unable to find the term "%s" in the vocabulary "%s".', $term_name, $vocabulary));
    }

    ksort($tids);
    $tid = end($tids);

    $path = $this->locatePath('/taxonomy/term/' . $tid . $action_subpath);

    $this->getSession()->visit($path);
  }

  /**
   * Load multiple terms with specified vocabulary and conditions.
   *
   * @param string $vocabulary
   *   The term vocabulary.
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of term ids.
   */
  protected function taxonomyLoadMultiple(string $vocabulary, array $conditions = []): array {
    $this->drupal();

    $query = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', $vocabulary);

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

}
