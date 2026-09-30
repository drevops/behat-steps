<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\TaxonomyTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel test for loading terms through 'TaxonomyTrait'.
 */
#[CoversTrait(TaxonomyTrait::class)]
#[Group('behat')]
class TaxonomyTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'taxonomy', 'text', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'filter']);

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    Vocabulary::create(['vid' => 'topics', 'name' => 'Topics'])->save();
  }

  /**
   * Tests that the matching terms of the vocabulary are loaded, keyed by ID.
   */
  public function testLoadMultipleLoadsTheMatchingTerms(): void {
    $first = $this->createTerm('tags', 'Shared');
    $second = $this->createTerm('tags', 'Shared');
    $this->createTerm('tags', 'Other');
    $this->createTerm('topics', 'Shared');

    $terms = $this->context->taxonomyLoadMultiple('tags', ['name' => 'Shared']);

    $this->assertLoadedSet([$first, $second], $terms, TermInterface::class);
  }

  /**
   * Tests that terms are keyed by ID when their revision IDs differ.
   *
   * An entity query keys a revisionable type by revision ID, so a new
   * revision of the first term makes the 2 sets of keys differ.
   */
  public function testLoadMultipleKeysByIdNotRevisionId(): void {
    $first = $this->createTerm('tags', 'Shared');
    $second = $this->createTerm('tags', 'Shared');

    $first->setNewRevision();
    $first->save();

    $this->assertGreaterThan((int) $second->getRevisionId(), (int) $first->getRevisionId());

    $terms = $this->context->taxonomyLoadMultiple('tags', ['name' => 'Shared']);

    $this->assertLoadedSet([$first, $second], $terms, TermInterface::class);
  }

  /**
   * Tests that an empty array is returned when no term matches.
   */
  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createTerm('topics', 'Shared');

    $this->assertSame([], $this->context->taxonomyLoadMultiple('tags', ['name' => 'Shared']));
  }

  /**
   * Creates and saves a term.
   */
  protected function createTerm(string $vocabulary, string $name): TermInterface {
    $term = Term::create(['vid' => $vocabulary, 'name' => $name]);
    $term->save();

    return $term;
  }

}
