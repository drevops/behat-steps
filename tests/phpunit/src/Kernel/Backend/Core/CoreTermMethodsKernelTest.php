<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for taxonomy term methods on Core via the backend.
 *
 * Exercises Core::createTerm (with optional parent lookup by name) and
 * Core::deleteTerm against real taxonomy_term storage.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreTermMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'taxonomy',
    'text',
    'filter',
  ];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'filter']);

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();

    $this->core = new Core($this->root);
  }

  /**
   * Tests the term lifecycle: create with parent lookup, then delete.
   */
  public function testTermLifecycle(): void {
    $parent = Term::create(['name' => 'Frameworks', 'vid' => 'tags']);
    $parent->save();

    $child_stub = new EntityStub('taxonomy_term', 'tags', [
      'name' => 'Drupal',
      'parent' => 'Frameworks',
    ]);
    $result = $this->core->createTerm($child_stub);

    $this->assertSame($child_stub, $result);
    $this->assertNotEmpty($result->getValue('tid'));
    $this->assertTrue($result->isSaved());
    $child = Term::load($result->getValue('tid'));
    $this->assertInstanceOf(Term::class, $child);
    $this->assertSame('Drupal', $child->getName());
    $this->assertSame((int) $parent->id(), (int) $child->get('parent')->target_id, 'parent name was resolved to tid.');

    $this->core->deleteTerm($result);
    $this->assertNull(Term::load($result->getValue('tid')));
  }

  public function testDeleteTermToleratesMissingTerm(): void {
    $this->assertNull(Term::load(99999));

    $this->core->deleteTerm(new EntityStub('taxonomy_term', 'tags', ['tid' => 99999]));

    $this->assertNull(Term::load(99999));
  }

  public function testCreateTermRejectsMissingVocabularyProperty(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches("/vocabulary is missing/");

    $this->core->createTerm(new EntityStub('taxonomy_term', NULL, ['name' => 'Orphan']));
  }

  public function testCreateTermRejectsUnknownVocabulary(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/vocabulary "ghosts" does not exist/');

    $this->core->createTerm(new EntityStub('taxonomy_term', 'ghosts', [
      'name' => 'Casper',
    ]));
  }

  public function testCreateTermRejectsUnknownParent(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/parent term "Missing" does not exist in vocabulary "tags"/');

    $this->core->createTerm(new EntityStub('taxonomy_term', 'tags', [
      'name' => 'Orphaned',
      'parent' => 'Missing',
    ]));
  }

  /**
   * Tests that 'vocabulary_machine_name' on a stub selects the vocabulary.
   *
   * The happy-path test passes the vocabulary as the bundle constructor
   * argument; this test passes it as the 'vocabulary_machine_name' stub value.
   */
  public function testCreateTermWithVocabularyMachineNameAlias(): void {
    $stub = new EntityStub('taxonomy_term', NULL, [
      'name' => 'Drupal',
      'vocabulary_machine_name' => 'tags',
    ]);

    $result = $this->core->createTerm($stub);

    $this->assertTrue($result->isSaved(), 'createTerm marked the stub saved.');
    $this->assertFalse($result->hasValue('vocabulary_machine_name'), 'Alias removed after resolution.');
    $term = Term::load($result->getValue('tid'));
    $this->assertInstanceOf(Term::class, $term);
    $this->assertSame('tags', $term->bundle());
  }

}
