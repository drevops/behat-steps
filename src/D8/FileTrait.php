<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\Entity\User;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Trait FileTrait.
 */
trait FileTrait {

  /**
   * Files ids.
   *
   * @var array
   */
  protected $files = [];

  /**
   * Ensures private and temp directories exist.
   *
   * @BeforeScenario
   */
  public function fileBeforeScenarioInit(BeforeScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $fs = new Filesystem();

    $dir = \Drupal::service('file_system')->realpath('private://');
    if ($dir && !$fs->exists($dir)) {
      $fs->mkdir($dir);
    }

    $dir = \Drupal::service('file_system')->realpath('temporary://');
    if ($dir && !$fs->exists($dir)) {
      $fs->mkdir($dir);
    }
  }

  /**
   * @Given managed file:
   */
  public function fileCreateManaged(TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $node = (object) $nodeHash;
      $this->fileCreateManagedSingle($node);
    }
  }

  /**
   * Create a single managed file.
   */
  protected function fileCreateManagedSingle($stub) {
    $this->parseEntityFields('file', $stub);
    $saved = $this->fileCreateEntity($stub);
    $this->files[] = $saved;

    return $saved;
  }

  /**
   * Create file entity.
   */
  protected function fileCreateEntity($stub) {
    if (empty($stub->path)) {
      throw new \RuntimeException('"path" property is required');
    }
    $path = ltrim($stub->path, '/');

    // Get fixture file path.
    if ($this->getMinkParameter('files_path')) {
      $full_path = rtrim(realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
      if (is_file($full_path)) {
        $path = $full_path;
      }
    }

    if (!is_readable($path)) {
      throw new \RuntimeException('Unable to find file ' . $path);
    }

    $destination = 'public://' . basename($path);
    if (!empty($stub->uri)) {
      $destination = $stub->uri;
      $directory = dirname($destination);
      $dir = \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY + FileSystemInterface::MODIFY_PERMISSIONS);
      if (!$dir) {
        throw new \RuntimeException('Unable to prepare directory ' . $directory);
      }
    }
    $entity = file_save_data(file_get_contents($path), $destination, FileSystemInterface::EXISTS_REPLACE);

    if (!$entity) {
      throw new \RuntimeException('Unable to save managed file ' . $path);
    }

    return $entity;
  }

  /**
   * @AfterScenario
   */
  public function fileCleanAll(AfterScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach ($this->files as $file) {
      $file->delete();
    }

    $this->files = [];
  }

  /**
   * Delete managed files defined by provided properties.
   *
   * @code
   * Given no managed files:
   * | filename      |
   * | myfile.jpg    |
   * | otherfile.jpg |
   * @endcode
   *
   * @Given no managed files:
   */
  public function fileDeleteManagedFiles(TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $hash) {
      $ids = $this->fileLoadMultiple(['name' => $hash]);
      $controller = \Drupal::entityTypeManager()->getStorage('file');
      $entities = $controller->loadMultiple($ids);
      $controller->delete($entities);
    }
  }

  /**
   * Helper to load multiple files with specified conditions.
   *
   * @param array $conditions
   *   Conditions keyed by field names.
   *
   * @return array
   *   Array of file ids.
   */
  protected function fileLoadMultiple(array $conditions = []) {
    $query = \Drupal::entityQuery('file');
    $query->addMetaData('account', User::load(1));
    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

}
