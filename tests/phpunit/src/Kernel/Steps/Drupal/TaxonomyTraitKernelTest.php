<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\TaxonomyTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for loading terms through 'TaxonomyTrait'.
 */
#[CoversTrait(TaxonomyTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
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

  public function testLoadMultipleLoadsTheMatchingTerms(): void {
    $first = $this->createTerm('tags', 'Shared');
    $second = $this->createTerm('tags', 'Shared');
    $this->createTerm('tags', 'Other');
    $this->createTerm('topics', 'Shared');

    $terms = $this->context->taxonomyLoadMultiple('tags', ['name' => 'Shared']);

    $this->assertStubSet([$first, $second], $terms, 'tid');
  }

  /**
   * Tests that terms are keyed by ID when their revision IDs differ.
   *
   * Entity queries key revisionable types by revision ID, so a new revision
   * of the first term makes the 2 sets of keys differ.
   */
  public function testLoadMultipleKeysByIdNotRevisionId(): void {
    $first = $this->createTerm('tags', 'Shared');
    $second = $this->createTerm('tags', 'Shared');

    $first->setNewRevision();
    $first->save();

    $this->assertGreaterThan((int) $second->getRevisionId(), (int) $first->getRevisionId());

    $terms = $this->context->taxonomyLoadMultiple('tags', ['name' => 'Shared']);

    $this->assertStubSet([$first, $second], $terms, 'tid');
  }

  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createTerm('topics', 'Shared');

    $this->assertSame([], $this->context->taxonomyLoadMultiple('tags', ['name' => 'Shared']));
  }

  public function testFindVocabularyReturnsTheStubOfTheVocabulary(): void {
    $vocabulary = $this->context->taxonomyFindVocabulary('tags');

    $this->assertNotNull($vocabulary);
    $this->assertSame('taxonomy_vocabulary', $vocabulary->getEntityType());
    $this->assertSame('tags', $vocabulary->getValue('vid'));
    $this->assertNull($this->context->taxonomyFindVocabulary('missing'));
  }

  public function testGetVocabularyFailsForMissingVocabulary(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The vocabulary "missing" does not exist.');

    $this->context->taxonomyGetVocabulary('missing');
  }

  public function testAssertVocabularyExistsPassesOnTheStoredName(): void {
    $this->context->taxonomyAssertVocabularyExists('topics', 'Topics');
    $this->context->taxonomyAssertVocabularyNotExists('missing');

    $this->addToAssertionCount(2);
  }

  protected function createTerm(string $vocabulary, string $name): TermInterface {
    $term = Term::create(['vid' => $vocabulary, 'name' => $name]);
    $term->save();

    return $term;
  }

}
