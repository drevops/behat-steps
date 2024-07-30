<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
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
  public function fileBeforeScenarioInit(BeforeScenarioScope $scope): void {
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
  public function fileCreateManaged(TableNode $nodesTable): void {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $node = (object) $nodeHash;
      $this->fileCreateManagedSingle($node);
    }
  }

  /**
   * Create a single managed file.
   */
  protected function fileCreateManagedSingle(\StdClass $stub): FileInterface {
    $this->parseEntityFields('file', $stub);
    $saved = $this->fileCreateEntity($stub);
    $this->files[] = $saved;

    return $saved;
  }

  /**
   * Create file entity.
   */
  protected function fileCreateEntity(\StdClass $stub): FileInterface {
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

    $entity = \Drupal::service('file.repository')->writeData(file_get_contents($path), $destination, FileExists::Replace);
    $fields = get_object_vars($stub);

    foreach ($fields as $property => $value) {
      // If path or URI has been specified then the value has already been
      // handled.
      if (in_array($property, ['path', 'uri'])) {
        continue;
      }
      $entity->set($property, $value);
    }

    $entity->save();

    return $entity;
  }

  /**
   * Clean all created managed files after scenario run.
   *
   * @AfterScenario
   */
  public function fileCleanAll(AfterScenarioScope $scope): void {
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
   * Delete managed files defined by provided properties/fields.
   *
   * Example: filename, uri, status, uid and more.
   *
   * @see Drupal\file\Entity\File
   *
   * @code
   * Given no managed files:
   * | filename      |
   * | myfile.jpg    |
   * | otherfile.jpg |
   * @endcode
   *
   * @code
   *  Given no managed files:
   *  | uri                    |
   *  | public://myfile.jpg    |
   *  | public://otherfile.jpg |
   * @endcode
   *
   * @Given no managed files:
   */
  public function fileDeleteManagedFiles(TableNode $nodesTable): void {
    $storage = \Drupal::entityTypeManager()->getStorage('file');
    $field_values = $nodesTable->getColumn(0);
    // Get field name of the column header.
    $field_name = array_shift($field_values);
    foreach ($field_values as $field_value) {
      $ids = $this->fileLoadMultiple([$field_name => $field_value]);
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
  protected function fileLoadMultiple(array $conditions = []): array|int {
    $query = \Drupal::entityQuery('file')->accessCheck(FALSE);
    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

  /**
   * Create an unmanaged file.
   *
   * @Given unmanaged file :uri created
   */
  public function fileCreateUnmanaged(string $uri, string $content = 'test'): void {
    $directory = \Drupal::service('file_system')->dirname($uri);

    if (!file_exists($directory)) {
      $dir = \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY + FileSystemInterface::MODIFY_PERMISSIONS);
      if (!$dir) {
        throw new \RuntimeException('Unable to prepare directory ' . $directory);
      }
    }

    file_put_contents($uri, $content);

    $this->filesUnmanagedUris[] = $uri;
  }

  /**
   * Create an unmanaged file with specified content.
   *
   * @Given unmanaged file :uri created with content :content
   */
  public function fileCreateUnmanagedWithContent(string $uri, string $content): void {
    $this->fileCreateUnmanaged($uri, $content);
  }

  /**
   * Assert that an unmanaged file with specified URI exists.
   *
   * @Then unmanaged file :uri exists
   */
  public function fileAssertUnmanagedExists(string $uri): void {
    if (!@file_exists($uri)) {
      throw new \Exception(sprintf('The file %s does not exist.', $uri));
    }
  }

  /**
   * Assert that an unmanaged file with specified URI does not exist.
   *
   * @Then unmanaged file :uri does not exist
   */
  public function fileAssertUnmanagedNotExists(string $uri): void {
    if (@file_exists($uri)) {
      throw new \Exception(sprintf('The file %s exists but it should not.', $uri));
    }
  }

  /**
   * Assert that an unmanaged file exists and has specified content.
   *
   * @Then unmanaged file :uri has content :content
   */
  public function fileAssertUnmanagedHasContent(string $uri, string $content): void {
    $this->fileAssertUnmanagedExists($uri);

    $file_content = @file_get_contents($uri);

    if (!str_contains($file_content, $content)) {
      throw new \Exception(sprintf('File contents "%s" does not contain "%s".', $file_content, $content));
    }
  }

  /**
   * Assert that an unmanaged file exists and does not have specified content.
   *
   * @Then unmanaged file :uri does not have content :content
   */
  public function fileAssertUnmanagedHasNoContent(string $uri, string $content): void {
    $this->fileAssertUnmanagedExists($uri);

    $file_content = @file_get_contents($uri);

    if (str_contains($file_content, $content)) {
      throw new \Exception(sprintf('File contents "%s" contains "%s", but should not.', $file_content, $content));
    }
  }

}
