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
use DrevOps\BehatSteps\Helper\Drupal\FixtureFileTrait;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Helper\Web\TableTransposeTrait;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Manage Drupal media entities with type-specific field handling.
 *
 * - Create structured media items with proper file reference handling.
 * - Assert media type and media item existence.
 * - Visit media view, edit, delete and revision pages.
 * - Support for multiple media types with field value expansion handling.
 * - Created entities are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait MediaTrait {

  use EntityLifecycleTrait;
  use FixtureFileTrait;
  use QueryTrait;
  use TableTransposeTrait;

  /**
   * Remove media type.
   *
   * @code
   * Given the media type "video" does not exist
   * @endcode
   */
  #[Given('the media type :media_type does not exist')]
  public function mediaDeleteType(string $media_type): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $type_entity = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type);

    if ($type_entity) {
      $type_entity->delete();
    }
  }

  /**
   * Create media of a given type.
   *
   * @code
   * Given the following "video" media exist:
   *   | name     | field1   | field2 | field3           |
   *   | My media | file.jpg | value  | value            |
   *   | ...      | ...      | ...    | ...              |
   * @endcode
   */
  #[Given('the following :media_type media exist:')]
  public function mediaCreateMultiple(string $media_type, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($table->getHash() as $media_hash) {
      $this->mediaDelete($media_type, $media_hash);
    }

    foreach ($table->getHash() as $media_hash) {
      $this->mediaCreate(new EntityStub('media', $media_type, $media_hash));
    }
  }

  /**
   * Create media entities with vertical field format.
   *
   * Supports both single and multiple entity creation using vertical table
   * format where fields are listed in rows instead of columns.
   *
   * @param string $media_type
   *   The media bundle machine name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Vertical format table with field names in first column.
   *
   * @code
   *   Given the following image media with fields exist:
   *     | name              | [TEST] Image 1       | [TEST] Image 2       |
   *     | field_media_image | image1.jpg           | image2.jpg           |
   * @endcode
   */
  #[Given('the following :media_type media with fields exist:')]
  public function mediaCreateMultipleWithFields(string $media_type, TableNode $table): void {
    $entities = $this->tableTransposeVertical($table);

    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($entities as $entity_data) {
      $this->mediaDelete($media_type, $entity_data);
    }

    foreach ($entities as $entity_data) {
      $this->mediaCreate(new EntityStub('media', $media_type, $entity_data));
    }
  }

  /**
   * Remove media defined by provided properties.
   *
   * @code
   * Given the following "image" media do not exist:
   *   | name               |
   *   | Media item         |
   *   | Another media item |
   * @endcode
   */
  #[Given('the following :media_type media do not exist:')]
  public function mediaDeleteMultiple(string $media_type, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($table->getHash() as $media_hash) {
      $this->mediaDelete($media_type, $media_hash);
    }
  }

  /**
   * Visit the page of the media with the specified type and name.
   *
   * @code
   * When I visit the "image" media page with the name "Test media image"
   * @endcode
   */
  #[When('I visit the :media_type media page with the name :name')]
  public function mediaVisitPageWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name);
  }

  /**
   * Visit the edit page of the media with the specified type and name.
   *
   * @code
   * When I visit the "document" media edit page with the name "Test document"
   * @endcode
   */
  #[When('I visit the :media_type media edit page with the name :name')]
  public function mediaVisitEditPageWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, '/edit');
  }

  /**
   * Visit the delete page of the media with the specified type and name.
   *
   * @code
   * When I visit the "image" media delete page with the name "Test media image"
   * @endcode
   */
  #[When('I visit the :media_type media delete page with the name :name')]
  public function mediaVisitDeletePageWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, '/delete');
  }

  /**
   * Visit the revisions page of the media with the specified type and name.
   *
   * @code
   * When I visit the "image" media revisions page with the name "Test media image"
   * @endcode
   */
  #[When('I visit the :media_type media revisions page with the name :name')]
  public function mediaVisitRevisionsPageWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, '/revisions');
  }

  /**
   * Assert that a media type exists.
   *
   * @code
   * Then the media type "image" should exist
   * @endcode
   */
  #[Then('the media type :media_type should exist')]
  public function mediaAssertTypeExists(string $media_type): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $type_entity = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type);

    if (!$type_entity) {
      throw new ExpectationException(sprintf('The media type "%s" does not exist.', $media_type), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a media type does not exist.
   *
   * @code
   * Then the media type "test_type" should not exist
   * @endcode
   */
  #[Then('the media type :media_type should not exist')]
  public function mediaAssertTypeNotExists(string $media_type): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $type_entity = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type);

    if ($type_entity) {
      throw new ExpectationException(sprintf('The media type "%s" exists, but it should not.', $media_type), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a media entity with a specific type and name exists.
   *
   * @code
   * Then the "image" media with the name "Test media image" should exist
   * @endcode
   */
  #[Then('the :media_type media with the name :name should exist')]
  public function mediaAssertExistsWithName(string $media_type, string $name): void {
    $media = $this->mediaLoadMultiple($media_type, [
      'name' => $name,
    ]);

    if (empty($media)) {
      throw new ExpectationException(sprintf('The "%s" media with the name "%s" does not exist.', $media_type, $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a media entity with a specific type and name does not exist.
   *
   * @code
   * Then the "image" media with the name "Test media image" should not exist
   * @endcode
   */
  #[Then('the :media_type media with the name :name should not exist')]
  public function mediaAssertNotExistsWithName(string $media_type, string $name): void {
    $media = $this->mediaLoadMultiple($media_type, [
      'name' => $name,
    ]);

    if (!empty($media)) {
      throw new ExpectationException(sprintf('The "%s" media with the name "%s" exists, but it should not.', $media_type, $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Visit the action page of the media with a specified name.
   *
   * When several media items of the type share the name, the newest is
   * visited.
   *
   * @param string $media_type
   *   The media type.
   * @param string $name
   *   The name of the media entity.
   * @param string|null $action_subpath
   *   The operation subpath, e.g., '/delete', '/edit', '/revisions', etc., or
   *   NULL for the media page.
   */
  public function mediaVisitActionPageWithName(string $media_type, string $name, ?string $action_subpath = NULL): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $mid = $this->queryFindNewestEntityId('media', ['name' => $name], $media_type);

    if ($mid === NULL) {
      throw new \RuntimeException(sprintf('Unable to find "%s" media with the name "%s".', $media_type, $name));
    }

    $path = $this->locatePath('/media/' . $mid . ($action_subpath ?? ''));

    $this->getSession()->visit($path);
  }

  /**
   * Create a single media item.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The media item properties.
   *
   * @return \Drupal\media\MediaInterface
   *   The created media item.
   */
  public function mediaCreate(EntityStubInterface $stub): MediaInterface {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->entityLifecycleParseFields($stub);
    $entity = $this->mediaCreateEntity($stub);
    $this->entityLifecycleRegister($entity);

    return $entity;
  }

  /**
   * Create media entity.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The media entity properties.
   *
   * @return \Drupal\media\MediaInterface
   *   The created media entity.
   */
  public function mediaCreateEntity(EntityStubInterface $stub): MediaInterface {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $bundle = $stub->getBundle();

    if ($bundle === NULL || $bundle === '') {
      throw new \RuntimeException('Cannot create media because it is missing the required bundle.');
    }

    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('media');

    if (!array_key_exists($bundle, $bundles)) {
      throw new \RuntimeException(sprintf('Cannot create media because provided bundle "%s" does not exist.', $bundle));
    }

    $this->mediaExpandEntityFieldsFixtures($stub);
    $this->mediaExpandEntityFields($stub);

    $values = $stub->getValues();
    $values['bundle'] = $bundle;
    $entity = Media::create($values);
    $entity->save();

    return $entity;
  }

  /**
   * Expand parsed fields into expected field values based on field type.
   *
   * Reuses the protected expansion provided by the Drupal backend core.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The entity stub.
   */
  protected function mediaExpandEntityFields(EntityStubInterface $stub): void {
    $core = $this->backendFor(CoreCapabilityInterface::class)->getCore();

    $class = new \ReflectionClass($core::class);
    $method = $class->getMethod('expandEntityFields');

    $method->invokeArgs($core, [$stub]);
  }

  /**
   * Expand entity fields with fixture values.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The entity stub.
   */
  protected function mediaExpandEntityFieldsFixtures(EntityStubInterface $stub): void {
    $this->fixtureFileExpandEntityFields('media', $stub);
  }

  /**
   * Load multiple media entities with specified type and conditions.
   *
   * @param string $media_type
   *   The media type.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, \Drupal\media\MediaInterface>
   *   The matching media keyed by ID, or an empty array when none match.
   */
  public function mediaLoadMultiple(string $media_type, array $conditions = []): array {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $ids = $this->queryEntityIds('media', $conditions, $media_type);

    return $ids ? Media::loadMultiple($ids) : [];
  }

  /**
   * Delete the media of a type that match conditions.
   *
   * @param string $media_type
   *   The media type.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   */
  public function mediaDelete(string $media_type, array $conditions): void {
    $media = $this->mediaLoadMultiple($media_type, $conditions);

    \Drupal::entityTypeManager()->getStorage('media')->delete($media);
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function mediaPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('media'), 'the core "media" module is enabled'),
    ];
  }

}
