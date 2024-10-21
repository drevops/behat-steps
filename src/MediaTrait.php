<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Trait MediaTrait.
 *
 * Trait to handle media entities.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
trait MediaTrait {

  /**
   * Array of created media entities.
   *
   * @var array<int,\Drupal\media\MediaInterface>
   */
  protected $mediaEntities = [];

  /**
   * Remove any created media items.
   *
   * @AfterScenario
   */
  public function mediaClean(AfterScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach ($this->mediaEntities as $media) {
      $media->delete();
    }
    $this->mediaEntities = [];
  }

  /**
   * Remove media type.
   *
   * @code
   * @Given no "video" media type
   * @endcode
   *
   * @Given no :type media type
   */
  public function mediaRemoveType(string $type): void {
    $type_entity = \Drupal::entityTypeManager()->getStorage('media_type')->load($type);
    if ($type_entity) {
      $type_entity->delete();
    }
  }

  /**
   * Creates media of a given type.
   *
   * @code
   * Given "video" media:
   * | name     | field1   | field2 | field3           |
   * | My media | file.jpg | value  | value            |
   * | ...      | ...      | ...    | ...              |
   * @endcode
   *
   * @Given :type media:
   */
  public function mediaCreate(string $type, TableNode $nodesTable): void {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $node = (object) $nodeHash;
      $node->bundle = $type;
      $this->mediaCreateSingle($node);
    }
  }

  /**
   * Remove media defined by provided properties.
   *
   * @code
   * Given no "image" media:
   * | name               |
   * | Media item         |
   * | Another media item |
   * @endcode
   *
   * @Given /^no ([a-zA-z0-9_-]+) media:$/
   */
  public function mediaDelete(string $type, TableNode $table): void {
    foreach ($table->getHash() as $node_hash) {
      $ids = $this->mediaLoadMultiple($type, $node_hash);
      $controller = \Drupal::entityTypeManager()->getStorage('media');
      $entities = $controller->loadMultiple($ids);
      $controller->delete($entities);
    }
  }

  /**
   * Navigate to edit media with specified type and name.
   *
   * @code
   * When I edit "document" media "Test document"
   * @endcode
   *
   * @When I edit :type media :name
   */
  public function mediaEditWithName(string $type, string $name): void {
    $mids = $this->mediaLoadMultiple($type, [
      'name' => $name,
    ]);

    if (empty($mids)) {
      throw new \RuntimeException(sprintf('Unable to find %s media "%s"', $type, $name));
    }

    $mid = current($mids);
    $path = $this->locatePath('/media/' . $mid) . '/edit';
    print $path;
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
    if (!property_exists($stub, 'bundle') || $stub->bundle === NULL || !$stub->bundle) {
      throw new \Exception("Cannot create media because it is missing the required property 'bundle'.");
    }

    $bundles = \Drupal::getContainer()->get('entity_type.bundle.info')->getBundleInfo('media');
    if (!in_array($stub->bundle, array_keys($bundles))) {
      throw new \Exception(sprintf("Cannot create media because provided bundle '%s' does not exist.", $stub->bundle));
    }

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
    $core = $this->getDriver()->getCore();

    $class = new \ReflectionClass($core::class);
    $method = $class->getMethod('expandEntityFields');
    $method->setAccessible(TRUE);

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

    if (empty($fixture_path) || !is_dir($fixture_path)) {
      throw new \RuntimeException('Fixture files path is not set or does not exist. Check that the "files_path" parameter is set for Mink.');
    }

    $fields = get_object_vars($stub);

    $field_types = $this->getDrupal()->getDriver()->getCore()->getEntityFieldTypes('media', array_keys($fields));

    foreach ($fields as $name => $value) {
      if (!str_contains($name, 'field_')) {
        continue;
      }

      if (!empty($field_types[$name]) && $field_types[$name] == 'image') {
        if (is_array($value)) {
          if (!empty($value[0]) && is_file($fixture_path . $value[0])) {
            $stub->{$name}[0] = $fixture_path . $value[0];
          }
        }
        elseif (is_file($fixture_path . $value)) {
          $stub->{$name} = $fixture_path . $value;
        }
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
