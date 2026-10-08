<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Helper\Web\TableTransposeTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Manage Drupal taxonomy terms with vocabulary organization.
 *
 * - Create term vocabulary structures using field values.
 * - Navigate to term pages.
 * - Verify vocabulary configurations.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait TaxonomyTrait {

  use EntityLifecycleTrait;
  use QueryTrait;
  use TableTransposeTrait;

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
  public function taxonomyCreateMultipleWithFields(string $vocabulary, TableNode $table): void {
    foreach ($this->tableTransposeVertical($table) as $values) {
      $this->taxonomyCreate($vocabulary, $values);
    }
  }

  /**
   * Create taxonomy terms in a vocabulary from a table of field values.
   *
   * Each row becomes 1 term; each column is a base property or a field. The
   * vocabulary accepts either its machine name or its human label.
   *
   * @code
   *   Given the following tags terms exist:
   *     | name         | description |
   *     | [TEST] Behat | Testing tag |
   * @endcode
   */
  #[Given('the following :vocabulary terms exist:')]
  public function taxonomyCreateMultiple(string $vocabulary, TableNode $table): void {
    // Terms are created through the content capability, which any backend may
    // provide, so this step checks no prerequisite.
    foreach ($table->getHash() as $values) {
      $this->taxonomyCreate($vocabulary, $values);
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
  public function taxonomyDeleteMultiple(string $vocabulary, TableNode $terms_table): void {
    $this->taxonomyGetVocabulary($vocabulary);

    foreach ($terms_table->getColumn(0) as $name) {
      $this->taxonomyDelete($vocabulary, ['name' => $name]);
    }
  }

  /**
   * Visit specified vocabulary term page.
   *
   * @code
   * When I visit the "fruits" term page with the name "Apple"
   * @endcode
   */
  #[When('I visit the :vocabulary term page with the name :name')]
  public function taxonomyVisitTermPageWithName(string $vocabulary, string $name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary, $name);
  }

  /**
   * Visit specified vocabulary term edit page.
   *
   * @code
   * When I visit the "fruits" term edit page with the name "Apple"
   * @endcode
   */
  #[When('I visit the :vocabulary term edit page with the name :name')]
  public function taxonomyVisitTermEditPageWithName(string $vocabulary, string $name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary, $name, '/edit');
  }

  /**
   * Visit specified vocabulary term delete page.
   *
   * @code
   * When I visit the "tags" term delete page with the name "[TEST] Remove"
   * @endcode
   */
  #[When('I visit the :vocabulary term delete page with the name :name')]
  public function taxonomyVisitTermDeletePageWithName(string $vocabulary, string $name): void {
    $this->taxonomyVisitActionPageWithName($vocabulary, $name, '/delete');
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
    $vocab = $this->taxonomyFindVocabulary($vocabulary);

    if ($vocab === NULL) {
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
    if ($this->taxonomyFindVocabulary($vocabulary) !== NULL) {
      throw new ExpectationException(sprintf('The vocabulary "%s" exists, but it should not.', $vocabulary), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a taxonomy term exists by name.
   *
   * @code
   * Then the taxonomy term "Apple" from the vocabulary "Fruits" should exist
   * @endcode
   */
  #[Then('the taxonomy term :name from the vocabulary :vocabulary should exist')]
  public function taxonomyAssertTermExistsWithName(string $name, string $vocabulary): void {
    $this->taxonomyGetVocabulary($vocabulary);

    $found = $this->taxonomyLoadMultiple($vocabulary, [
      'name' => $name,
    ]);

    if (count($found) === 0) {
      throw new ExpectationException(sprintf('The taxonomy term "%s" from the vocabulary "%s" does not exist.', $name, $vocabulary), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a taxonomy term does not exist by name.
   *
   * @code
   * Then the taxonomy term "Apple" from the vocabulary "Fruits" should not exist
   * @endcode
   */
  #[Then('the taxonomy term :name from the vocabulary :vocabulary should not exist')]
  public function taxonomyAssertTermNotExistsWithName(string $name, string $vocabulary): void {
    $this->taxonomyGetVocabulary($vocabulary);

    $found = $this->taxonomyLoadMultiple($vocabulary, [
      'name' => $name,
    ]);

    if (count($found) > 0) {
      throw new ExpectationException(sprintf('The taxonomy term "%s" from the vocabulary "%s" exists, but it should not.', $name, $vocabulary), $this->getSession()->getDriver());
    }
  }

  /**
   * Visit the action page of the term with a specified name.
   *
   * When several terms of the vocabulary share the name, the newest is
   * visited.
   *
   * @param string $vocabulary
   *   The term vocabulary machine name.
   * @param string $name
   *   The name of the term.
   * @param string|null $action_subpath
   *   The operation to perform, e.g., '/delete', '/edit', etc., or NULL for the
   *   term page.
   */
  public function taxonomyVisitActionPageWithName(string $vocabulary, string $name, ?string $action_subpath = NULL): void {
    $this->taxonomyGetVocabulary($vocabulary);

    $tid = $this->queryFindNewestEntityId('taxonomy_term', ['name' => $name], $vocabulary);

    if ($tid === NULL) {
      throw new \RuntimeException(sprintf('Unable to find the term "%s" in the vocabulary "%s".', $name, $vocabulary));
    }

    $path = $this->locatePath('/taxonomy/term/' . $tid . ($action_subpath ?? ''));

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
   * @return array<int, \Drupal\taxonomy\TermInterface>
   *   The matching terms keyed by ID, or an empty array when none match.
   */
  public function taxonomyLoadMultiple(string $vocabulary, array $conditions = []): array {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $ids = $this->queryEntityIds('taxonomy_term', $conditions, $vocabulary);

    return $ids ? Term::loadMultiple($ids) : [];
  }

  /**
   * Create a term in a vocabulary.
   *
   * The term is removed after the scenario.
   *
   * @param string $vocabulary
   *   The vocabulary machine name or human label.
   * @param array<string, mixed> $values
   *   The base properties and field values, keyed by name.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created term.
   */
  public function taxonomyCreate(string $vocabulary, array $values): EntityStubInterface {
    $values['vocabulary_machine_name'] = $vocabulary;

    return $this->entityLifecycleCreateTerm(new EntityStub('taxonomy_term', $vocabulary, $values));
  }

  /**
   * Delete the terms of a vocabulary that match conditions.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   */
  public function taxonomyDelete(string $vocabulary, array $conditions): void {
    foreach ($this->taxonomyLoadMultiple($vocabulary, $conditions) as $term) {
      $term->delete();
    }
  }

  /**
   * Find a vocabulary by machine name.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   *
   * @return \Drupal\taxonomy\VocabularyInterface|null
   *   The vocabulary, or NULL when it does not exist.
   */
  public function taxonomyFindVocabulary(string $vocabulary): ?VocabularyInterface {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    return Vocabulary::load($vocabulary);
  }

  /**
   * Get a vocabulary by machine name.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   *
   * @return \Drupal\taxonomy\VocabularyInterface
   *   The vocabulary.
   *
   * @throws \RuntimeException
   *   When the vocabulary does not exist.
   */
  public function taxonomyGetVocabulary(string $vocabulary): VocabularyInterface {
    $vocab = $this->taxonomyFindVocabulary($vocabulary);

    if ($vocab === NULL) {
      throw new \RuntimeException(sprintf('The vocabulary "%s" does not exist.', $vocabulary));
    }

    return $vocab;
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function taxonomyPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('taxonomy'), 'the core "taxonomy" module is enabled'),
    ];
  }

}
