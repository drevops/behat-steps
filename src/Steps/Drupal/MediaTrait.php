<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\FixtureFileTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\TableTransposeTrait;

/**
 * Manage Drupal media entities with type-specific field handling.
 *
 * - Create structured media items with proper file reference handling.
 * - Assert media type and media item existence.
 * - Visit media view, edit, delete and revision pages.
 * - Support for multiple media types with field value expansion handling.
 * - Created entities are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
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
   *
   * @actor:system
   */
  #[Given('the media type :media_type does not exist')]
  public function mediaDeleteType(string $media_type): void {
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $backend->deleteEntity(new EntityStub('media_type', NULL, ['id' => $media_type]));
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
   *
   * @actor:system
   */
  #[Given('the following :media_type media exist:')]
  public function mediaCreateMultiple(string $media_type, TableNode $table): void {
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
   *
   * @actor:system
   */
  #[Given('the following :media_type media with fields exist:')]
  public function mediaCreateMultipleWithFields(string $media_type, TableNode $table): void {
    $entities = $this->tableTransposeVertical($table);

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
   *
   * @actor:system
   */
  #[Given('the following :media_type media do not exist:')]
  public function mediaDeleteMultiple(string $media_type, TableNode $table): void {
    $this->backendFor(ContentCapabilityInterface::class);

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
   *
   * @actor:person
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
   *
   * @actor:person
   */
  #[When('I visit the :media_type media edit page with the name :name')]
  public function mediaVisitEditPageWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, 'edit-form');
  }

  /**
   * Visit the delete page of the media with the specified type and name.
   *
   * @code
   * When I visit the "image" media delete page with the name "Test media image"
   * @endcode
   *
   * @actor:person
   */
  #[When('I visit the :media_type media delete page with the name :name')]
  public function mediaVisitDeletePageWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, 'delete-form');
  }

  /**
   * Visit the revisions page of the media with the specified type and name.
   *
   * @code
   * When I visit the "image" media revisions page with the name "Test media image"
   * @endcode
   *
   * @actor:person
   */
  #[When('I visit the :media_type media revisions page with the name :name')]
  public function mediaVisitRevisionsPageWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, 'version-history');
  }

  /**
   * Assert that a media type exists.
   *
   * @code
   * Then the media type "image" should exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the media type :media_type should exist')]
  public function mediaAssertTypeExists(string $media_type): void {
    $config = $this->backendFor(ConfigCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    if (!$config->configExists('media.type.' . $media_type)) {
      throw new ExpectationException(sprintf('The media type "%s" does not exist.', $media_type), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a media type does not exist.
   *
   * @code
   * Then the media type "test_type" should not exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the media type :media_type should not exist')]
  public function mediaAssertTypeNotExists(string $media_type): void {
    $config = $this->backendFor(ConfigCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    if ($config->configExists('media.type.' . $media_type)) {
      throw new ExpectationException(sprintf('The media type "%s" exists, but it should not.', $media_type), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a media entity with a specific type and name exists.
   *
   * @code
   * Then the "image" media with the name "Test media image" should exist
   * @endcode
   *
   * @actor:system
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
   *
   * @actor:system
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
   * Visit a page of the media with a specified name.
   *
   * When several media items of the type share the name, the newest is
   * visited.
   *
   * @param string $media_type
   *   The media type.
   * @param string $name
   *   The name of the media entity.
   * @param string $rel
   *   The link relationship of the page, such as 'edit-form', or 'canonical'
   *   for the media page.
   */
  public function mediaVisitActionPageWithName(string $media_type, string $name, string $rel = 'canonical'): void {
    $this->backendFor(EntityLookupCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $mid = $this->queryFindNewestEntityId('media', ['name' => $name], $media_type);

    if ($mid === NULL) {
      throw new \RuntimeException(sprintf('Unable to find "%s" media with the name "%s".', $media_type, $name));
    }

    $this->visitPath($this->backendFor(ContentCapabilityInterface::class)->getEntityUrl(new EntityStub('media', $media_type, ['mid' => $mid]), $rel));
  }

  /**
   * Create a single media item.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The media item properties.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created media item, carrying its 'mid'.
   */
  public function mediaCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->entityLifecycleParseFields($stub);
    $created = $this->mediaCreateEntity($stub);
    $this->entityLifecycleRegister($created, 'mid');

    return $created;
  }

  /**
   * Create media entity.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The media entity properties.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created media entity, carrying its 'mid'.
   */
  public function mediaCreateEntity(EntityStubInterface $stub): EntityStubInterface {
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $bundle = $stub->getBundle();

    if ($bundle === NULL || $bundle === '') {
      throw new \RuntimeException('Cannot create media because it is missing the required bundle.');
    }

    $this->mediaExpandEntityFieldsFixtures($stub);

    return $backend->createEntity($stub);
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
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching media item carrying its 'mid', keyed by ID, or an
   *   empty array when none match.
   */
  public function mediaLoadMultiple(string $media_type, array $conditions = []): array {
    $this->assertPrerequisites(__TRAIT__);

    return $this->queryEntityStubs('media', 'mid', $conditions, $media_type);
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
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($this->queryEntityIds('media', $conditions, $media_type) as $mid) {
      $backend->deleteEntity(new EntityStub('media', $media_type, ['mid' => $mid]));
    }
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function mediaPrerequisites(): array {
    return [
      Prerequisite::capability(ContentCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('media'), 'the core "media" module is enabled'),
    ];
  }

}
