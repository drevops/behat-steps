<?php

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Trait FileTrait.
 *
 * File-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait FileTrait {

  /**
   * Files ids.
   *
   * @var array
   */
  protected $files = [];

  /**
   * Unmanaged file URIs.
   *
   * @var array
   */
  protected $filesUnmanagedUris = [];

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
   * Create managed file with properties provided in the table.
   *
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
    $entity = \Drupal::service('file.repository')->writeData(file_get_contents($path), $destination, FileSystemInterface::EXISTS_REPLACE);

    if (!$entity) {
      throw new \RuntimeException('Unable to save managed file ' . $path);
    }

    return $entity;
  }

  /**
   * Clean all created managed files after scenario run.
   *
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

    foreach ($this->filesUnmanagedUris as $uri) {
      @unlink($uri);
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
    $storage = \Drupal::entityTypeManager()->getStorage('file');
    $filenames = $nodesTable->getColumn(0);
    // Get rid of the column header.
    array_shift($filenames);
    foreach ($filenames as $filename) {
      $ids = $this->fileLoadMultiple(['filename' => $filename]);
      $entities = $storage->loadMultiple($ids);
      $storage->delete($entities);
    }
  }

  /**
   * Load multiple files with specified conditions.
   *
   * @param array $conditions
   *   Conditions keyed by field names.
   *
   * @return array
   *   Array of file ids.
   */
  protected function fileLoadMultiple(array $conditions = []) {
    $query = \Drupal::entityQuery('file')->accessCheck(FALSE);
    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

  /**
   * Create an unmanaged file with specified content.
   *
   * @Given unmanaged file :uri created
   */
  public function fileCreateUnmanaged($uri, $content = 'test') {
    $dir = dirname($uri);
    if (!file_exists($dir)) {
      mkdir($dir, 0770, TRUE);
    }

    file_put_contents($uri, $content);

    $this->filesUnmanagedUris[] = $uri;
  }

  /**
   * Create an unmanaged file with specified content.
   *
   * @Given unmanaged file :uri created with content :content
   */
  public function fileCreateUnmanagedWithContent($uri, $content) {
    $this->fileCreateUnmanaged($uri, $content);
  }

  /**
   * Assert that an unmanaged file with specified URI exists.
   *
   * @Then unmanaged file :uri exists
   */
  public function fileAssertUnmanagedExists($uri) {
    if (!@file_exists($uri)) {
      throw new \Exception(sprintf('The file %s does not exist.', $uri));
    }
  }

  /**
   * Assert that an unmanaged file with specified URI does not exist.
   *
   * @Then unmanaged file :uri does not exist
   */
  public function fileAssertUnmanagedNotExists($uri) {
    if (@file_exists($uri)) {
      throw new \Exception(sprintf('The file %s exists but it should not.', $uri));
    }
  }

  /**
   * Assert that an unmanaged file exists and has specified content.
   *
   * @Then unmanaged file :uri has content :content
   */
  public function fileAssertUnmanagedHasContent($uri, $content) {
    $this->fileAssertUnmanagedExists($uri);

    $file_content = @file_get_contents($uri);

    if (strpos($file_content, $content) === FALSE) {
      throw new \Exception(sprintf('File contents "%s" does not contain "%s".', $file_content, $content));
    }
  }

  /**
   * Assert that an unmanaged file exists and does not have specified content.
   *
   * @Then unmanaged file :uri does not have content :content
   */
  public function fileAssertUnmanagedHasNoContent($uri, $content) {
    $this->fileAssertUnmanagedExists($uri);

    $file_content = @file_get_contents($uri);

    if (strpos($file_content, $content) !== FALSE) {
      throw new \Exception(sprintf('File contents "%s" contains "%s", but should not.', $file_content, $content));
    }
  }

}
