<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\HelperTrait;
use Drupal\Driver\DrupalDriver;
use Drupal\Driver\DrupalDriverInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Manage Drupal media entities with type-specific field handling.
 *
 * - Create structured media items with proper file reference handling.
 * - Assert media browser functionality and edit media entity fields.
 * - Support for multiple media types with field value expansion handling.
 * - Automatically clean up created entities after scenario completion.
 *
 * Skip processing with tag: `@behat-steps-skip:mediaAfterScenario`
 */
trait MediaTrait {

  use HelperTrait;

  /**
   * Array of created media entities.
   *
   * @var array<int,\Drupal\media\MediaInterface>
   */
  protected $mediaEntities = [];

  /**
   * Remove any created media items.
   */
  #[AfterScenario]
  public function mediaAfterScenario(AfterScenarioScope $scope): void {
    // @codeCoverageIgnoreStart
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    // @codeCoverageIgnoreEnd
    foreach ($this->mediaEntities as $media) {
      $media->delete();
    }
    $this->mediaEntities = [];
  }

  /**
   * Remove media type.
   *
   * @code
   * Given "video" media type does not exist
   * @endcode
   */
  #[Given(':media_type media type does not exist')]
  public function mediaRemoveType(string $type): void {
    $type_entity = \Drupal::entityTypeManager()->getStorage('media_type')->load($type);
    if ($type_entity) {
      $type_entity->delete();
    }
  }

  /**
   * Create media of a given type.
   *
   * @code
   * Given the following media "video" exist:
   *   | name     | field1   | field2 | field3           |
   *   | My media | file.jpg | value  | value            |
   *   | ...      | ...      | ...    | ...              |
   * @endcode
   */
  #[Given('the following media :media_type exist:')]
  public function mediaCreate(string $media_type, TableNode $table): void {
    // Delete entities before creating them.
    $this->mediaDelete($media_type, $table);

    foreach ($table->getHash() as $node_hash) {
      $node = (object) $node_hash;
      $node->bundle = $media_type;
      $this->mediaCreateSingle($node);
    }
  }

  /**
   * Create media entities with vertical field format.
   *
   * Supports both single and multiple entity creation using vertical table
   * format where fields are listed in rows instead of columns.
   *
   * @param string $bundle
   *   The media bundle machine name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Vertical format table with field names in first column.
   *
   * @code
   *   Given the following image media with fields:
   *     | name              | [TEST] Image 1       | [TEST] Image 2       |
   *     | field_media_image | image1.jpg           | image2.jpg           |
   * @endcode
   */
  #[Given('the following :bundle media with fields:')]
  public function mediaCreateWithFields(string $bundle, TableNode $table): void {
    $entities = $this->helperTransposeVerticalTable($table);
    $horizontal_table = $this->helperBuildHorizontalTable($entities);

    // Delete entities before creating them.
    $this->mediaDelete($bundle, $horizontal_table);

    foreach ($entities as $entity_data) {
      $stub = (object) $entity_data;
      $stub->bundle = $bundle;
      $this->mediaCreateSingle($stub);
    }
  }

  /**
   * Remove media defined by provided properties.
   *
   * @code
   * Given the following media "image" do not exist:
   *   | name               |
   *   | Media item         |
   *   | Another media item |
   * @endcode
   */
  #[Given('the following media :media_type do not exist:')]
  public function mediaDelete(string $media_type, TableNode $table): void {
    foreach ($table->getHash() as $node_hash) {
      $ids = $this->mediaLoadMultiple($media_type, $node_hash);
      $controller = \Drupal::entityTypeManager()->getStorage('media');
      $entities = $controller->loadMultiple($ids);
      $controller->delete($entities);
    }
  }

  /**
   * Navigate to edit media with specified type and name.
   *
   * @code
   * When I edit the media "document" with the name "Test document"
   * @endcode
   */
  #[When('I edit the media :media_type with the name :name')]
  public function mediaEditWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, '/edit');
  }

  /**
   * Navigate to view page of media with specified type and name.
   *
   * @code
   * When I visit the media "image" with the name "Test media image"
   * @endcode
   */
  #[When('I visit the media :media_type with the name :name')]
  public function mediaVisitViewWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name);
  }

  /**
   * Navigate to delete page of media with specified type and name.
   *
   * @code
   * When I visit the media "image" delete page with the name "Test media image"
   * @endcode
   */
  #[When('I visit the media :media_type delete page with the name :name')]
  public function mediaVisitDeleteWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, '/delete');
  }

  /**
   * Navigate to revisions page of media with specified type and name.
   *
   * @code
   * When I visit the media "image" revisions page with the name "Test media image"
   * @endcode
   */
  #[When('I visit the media :media_type revisions page with the name :name')]
  public function mediaVisitRevisionsWithName(string $media_type, string $name): void {
    $this->mediaVisitActionPageWithName($media_type, $name, '/revisions');
  }

  /**
   * Assert that a media type exists.
   *
   * @code
   * Then the "image" media type should exist
   * @endcode
   */
  #[Then('the :media_type media type should exist')]
  public function mediaAssertTypeExists(string $media_type): void {
    $type_entity = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type);

    if (!$type_entity) {
      throw new ExpectationException(sprintf('The media type "%s" does not exist.', $media_type), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a media type does not exist.
   *
   * @code
   * Then the "test_type" media type should not exist
   * @endcode
   */
  #[Then('the :media_type media type should not exist')]
  public function mediaAssertTypeNotExists(string $media_type): void {
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
    $mids = $this->mediaLoadMultiple($media_type, [
      'name' => $name,
    ]);

    if (empty($mids)) {
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
    $mids = $this->mediaLoadMultiple($media_type, [
      'name' => $name,
    ]);

    if (!empty($mids)) {
      throw new ExpectationException(sprintf('The "%s" media with the name "%s" exists, but it should not.', $media_type, $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Visit the action page of the media with a specified name.
   *
   * @param string $media_type
   *   The media type.
   * @param string $name
   *   The name of the media entity.
   * @param string $action_subpath
   *   The operation subpath, e.g., '/delete', '/edit', '/revisions', etc.
   */
  protected function mediaVisitActionPageWithName(string $media_type, string $name, string $action_subpath = ''): void {
    $mids = $this->mediaLoadMultiple($media_type, [
      'name' => $name,
    ]);

    if (empty($mids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" media with the name "%s".', $media_type, $name));
    }

    ksort($mids);
    $mid = end($mids);
    $path = $this->locatePath('/media/' . $mid . $action_subpath);

    $this->getSession()->visit($path);
  }

  /**
   * Create a single media item.
   *
   * @param \StdClass $stub
   *   The media item properties.
   *
   * @return \Drupal\media\MediaInterface
   *   The created media item.
   */
  protected function mediaCreateSingle(\StdClass $stub): MediaInterface {
    $this->parseEntityFields('media', $stub);
    $saved = $this->mediaCreateEntity($stub);
    $this->mediaEntities[] = $saved;

    return $saved;
  }

  /**
   * Create media entity.
   *
   * @param \StdClass $stub
   *   The media entity properties.
   *
   * @return \Drupal\media\MediaInterface
   *   The created media entity.
   */
  protected function mediaCreateEntity(\StdClass $stub): MediaInterface {
    // Throw an exception if the media type is missing or does not exist.
    // @codeCoverageIgnoreStart
    if (!property_exists($stub, 'bundle') || $stub->bundle === NULL || !$stub->bundle) {
      throw new \Exception("Cannot create media because it is missing the required property 'bundle'.");
    }

    $bundles = \Drupal::getContainer()->get('entity_type.bundle.info')->getBundleInfo('media');
    if (!in_array($stub->bundle, array_keys($bundles))) {
      throw new \Exception(sprintf("Cannot create media because provided bundle '%s' does not exist.", $stub->bundle));
    }
    // @codeCoverageIgnoreEnd
    $this->mediaExpandEntityFieldsFixtures($stub);

    $this->mediaExpandEntityFields('media', $stub);

    $entity = Media::create((array) $stub);
    $entity->save();

    return $entity;
  }

  /**
   * Expand parsed fields into expected field values based on field type.
   *
   * This is a re-use of the functionality provided by DrupalExtension.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \StdClass $stub
   *   The entity stub.
   */
  protected function mediaExpandEntityFields(string $entity_type, \StdClass $stub): void {
    $driver = $this->getDriver();

    if (!$driver instanceof DrupalDriver) {
      throw new \RuntimeException('The current driver does not support Drupal-specific operations. Ensure you are using a compatible Drupal driver.');
    }

    $core = $driver->getCore();

    $class = new \ReflectionClass($core::class);
    $method = $class->getMethod('expandEntityFields');

    $method->invokeArgs($core, func_get_args());
  }

  /**
   * Expand entity fields with fixture values.
   *
   * @param \StdClass $stub
   *   The entity stub.
   */
  protected function mediaExpandEntityFieldsFixtures(\StdClass $stub): void {
    if (!empty($this->getMinkParameter('files_path'))) {
      $fixture_path = rtrim((string) realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    // @codeCoverageIgnoreStart
    if (empty($fixture_path) || !is_dir($fixture_path)) {
      throw new \RuntimeException('Fixture files path is not set or does not exist. Check that the "files_path" parameter is set for Mink.');
    }
    // @codeCoverageIgnoreEnd
    $fields = get_object_vars($stub);

    $driver = $this->getDriver();
    if (!$driver instanceof DrupalDriverInterface) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support content operations required for media field expansion.', $driver::class));
      // @codeCoverageIgnoreEnd
    }

    $field_types = $driver->getCore()->getEntityFieldTypes('media');

    foreach ($fields as $name => $value) {
      if (!str_contains((string) $name, 'field_')) {
        continue;
      }

      if (!empty($field_types[$name]) && ($field_types[$name] == 'image' || $field_types[$name] == 'file')) {
        if (is_array($value)) {
          if (!empty($value[0]) && is_file($fixture_path . $value[0])) {
            $stub->{$name}[0] = $fixture_path . $value[0];
          }
        }
        // @codeCoverageIgnoreStart
        elseif (is_file($fixture_path . $value)) {
          $stub->{$name} = $fixture_path . $value;
        }
        // @codeCoverageIgnoreEnd
      }
    }
  }

  /**
   * Load multiple media entities with specified type and conditions.
   *
   * @param string $type
   *   The node type.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of node ids.
   */
  protected function mediaLoadMultiple(string $type, array $conditions = []): array {
    $query = \Drupal::entityQuery('media')
      ->accessCheck(FALSE)
      ->condition('bundle', $type);

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

}
