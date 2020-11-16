<?php

use Behat\Gherkin\Node\TableNode;
use Drupal\media\Entity\Media;

/**
 * Trait MediaTrait.
 */
trait MediaTrait {

  /**
   * Array of created media entities.
   *
   * @var array
   */
  protected $media = [];

  /**
   * Remove any created media items.
   *
   * @AfterScenario
   */
  public function mediaClean() {
    foreach ($this->media as $media) {
      $media->delete();
    }
    $this->media = [];
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
  public function mediaRemoveType($type) {
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
  public function mediaCreate($type, TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $node = (object) $nodeHash;
      $node->bundle = $type;
      $this->mediaCreateSingle($node);
    }
  }

  /**
   * Create a single media item.
   */
  protected function mediaCreateSingle($stub) {
    $this->parseEntityFields('media', $stub);
    $saved = $this->mediaCreateEntity($stub);
    $this->media[] = $saved;

    return $saved;
  }

  /**
   * Create media entity.
   */
  protected function mediaCreateEntity($stub) {
    // Throw an exception if the media type is missing or does not exist.
    if (!isset($stub->bundle) || !$stub->bundle) {
      throw new \Exception("Cannot create media because it is missing the required property 'bundle'.");
    }

    $bundles = \Drupal::entityTypeManager()->getBundleInfo('media');
    if (!in_array($stub->bundle, array_keys($bundles))) {
      throw new \Exception("Cannot create media because provided bundle '$stub->bundle' does not exist.");
    }

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
   *   Entity type.
   * @param object $stub
   *   Stub stdClass object with fields and raw values.
   *
   * @return object
   *   Stub object with expanded fields.
   */
  protected function mediaExpandEntityFields($entity_type, $stub) {
    $core = $this->getDriver()->getCore();

    $class = new \ReflectionClass(get_class($core));
    $method = $class->getMethod('expandEntityFields');
    $method->setAccessible(TRUE);

    return $method->invokeArgs($core, func_get_args());
  }

}
