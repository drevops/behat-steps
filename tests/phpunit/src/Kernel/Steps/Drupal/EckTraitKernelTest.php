<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\EckTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\eck\Entity\EckEntityType;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel test for loading ECK entities through 'EckTrait'.
 */
#[CoversTrait(EckTrait::class)]
#[Group('behat')]
class EckTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'field', 'eck'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['eck']);

    EckEntityType::create(['id' => 'contact', 'label' => 'Contact', 'title' => TRUE])->save();

    $bundle_storage = \Drupal::entityTypeManager()->getStorage('contact_type');
    $bundle_storage->create(['type' => 'person', 'name' => 'Person'])->save();
    $bundle_storage->create(['type' => 'company', 'name' => 'Company'])->save();
  }

  /**
   * Tests that the matching entities of the bundle are loaded, keyed by ID.
   */
  public function testLoadMultipleLoadsTheMatchingEntities(): void {
    $first = $this->createEntity('person', 'Shared');
    $second = $this->createEntity('person', 'Shared');
    $this->createEntity('person', 'Other');
    $this->createEntity('company', 'Shared');

    $entities = $this->context->eckLoadMultiple('contact', 'person', ['title' => 'Shared']);

    $this->assertLoadedSet([$first, $second], $entities, ContentEntityInterface::class);
  }

  /**
   * Tests that an empty array is returned when no entity matches.
   */
  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createEntity('company', 'Shared');

    $this->assertSame([], $this->context->eckLoadMultiple('contact', 'person', ['title' => 'Shared']));
  }

  /**
   * Creates and saves a 'contact' entity.
   */
  protected function createEntity(string $bundle, string $title): EntityInterface {
    $entity = \Drupal::entityTypeManager()->getStorage('contact')->create(['type' => $bundle, 'title' => $title]);
    $entity->save();

    return $entity;
  }

}
