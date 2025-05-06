<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manage Drupal file entities with upload and storage operations.
 *
 * - Create managed and unmanaged files with specific URIs and content.
 * - Verify file existence, content, and proper storage locations.
 * - Set up file system directories and clean up created files.
 *
 * Skip processing with tags: `@behat-steps-skip:fileBeforeScenario` or
 * `@behat-steps-skip:fileAfterScenario`
 */
trait FileTrait {

  /**
   * Files entities.
   *
   * @var array<int,FileInterface>
   */
  protected $fileEntities = [];

  /**
   * Unmanaged file URIs.
   *
   * @var array<int,string>
   */
  protected $filesUnmanagedUris = [];

  /**
   * Ensure private and temp directories exist.
   *
   * @BeforeScenario
   */
  public function fileBeforeScenario(BeforeScenarioScope $scope): void {
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
   * Create managed files with properties provided in the table.
   *
   * @code
   * Given the following managed files:
   * | path         | uri                    | status |
   * | document.pdf | public://document.pdf  | 1      |
   * | image.jpg    | public://images/pic.jpg| 1      |
   * @endcode
   *
   * @Given the following managed files:
   */
  public function fileCreateManaged(TableNode $nodesTable): void {
    foreach ($nodesTable->getHash() as $node_hash) {
      $node = (object) $node_hash;
      $this->fileCreateManagedSingle($node);
    }
  }

  /**
   * Create a single managed file.
   */
  protected function fileCreateManagedSingle(\StdClass $stub): FileInterface {
    $this->parseEntityFields('file', $stub);
    $saved = $this->fileCreateEntity($stub);

    $this->fileEntities[] = $saved;

    return $saved;
  }

  /**
   * Create file entity.
   *
   * @param \StdClass $stub
   *   Stub object.
   *
   * @return \Drupal\file\FileInterface
   *   Created file entity.
   */
  protected function fileCreateEntity(\StdClass $stub): FileInterface {
    if (empty($stub->path)) {
      throw new \RuntimeException('"path" property is required');
    }

    $path = ltrim((string) $stub->path, '/');

    // Get fixture file path.
    if (!empty($this->getMinkParameter('files_path'))) {
      $full_path = rtrim((string) realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
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
      $directory = dirname((string) $destination);
      $dir = \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY + FileSystemInterface::MODIFY_PERMISSIONS);
      if (!$dir) {
        throw new \RuntimeException('Unable to prepare directory ' . $directory);
      }
    }

    $content = file_get_contents($path);
    if ($content === FALSE) {
      throw new \RuntimeException('Unable to read file ' . $path);
    }

    $entity = \Drupal::service('file.repository')->writeData($content, $destination, FileExists::Replace);
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
  public function fileAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach ($this->fileEntities as $file) {
      $file->delete();
    }

    foreach ($this->filesUnmanagedUris as $uri) {
      @unlink($uri);
    }

    $this->fileEntities = [];
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
   * @Given the following managed files do not exist:
   */
  public function fileDeleteManagedFiles(TableNode $nodesTable): void {
    $storage = \Drupal::entityTypeManager()->getStorage('file');

    $field_values = $nodesTable->getColumn(0);
    // Get field name of the column header.
    $field_name = array_shift($field_values);

    if (is_numeric($field_name)) {
      throw new \RuntimeException('The first column should be the field name');
    }

    $field_name = (string) $field_name;

    foreach ($field_values as $field_value) {
      $ids = $this->fileLoadMultiple([$field_name => (string) $field_value]);
      $entities = $storage->loadMultiple($ids);
      $storage->delete($entities);
    }
  }

  /**
   * Load multiple files with specified conditions.
   *
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of file ids.
   */
  protected function fileLoadMultiple(array $conditions = []): array {
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
   * @code
   * Given the unmanaged file at the URI "public://sample.txt" exists
   * @endcode
   *
   * @Given the unmanaged file at the URI :uri exists
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
   * @code
   * Given the unmanaged file at the URI "public://data.txt" exists with "Sample content"
   * @endcode
   *
   * @Given the unmanaged file at the URI :uri exists with :content
   */
  public function fileCreateUnmanagedWithContent(string $uri, string $content): void {
    $this->fileCreateUnmanaged($uri, $content);
  }

  /**
   * Assert that an unmanaged file with specified URI exists.
   *
   * @code
   * Then an unmanaged file at the URI "public://sample.txt" should exist
   * @endcode
   *
   * @Then an unmanaged file at the URI :uri should exist
   */
  public function fileAssertUnmanagedExists(string $uri): void {
    if (!@file_exists($uri)) {
      throw new \Exception(sprintf('The file %s does not exist.', $uri));
    }
  }

  /**
   * Assert that an unmanaged file with specified URI does not exist.
   *
   * @code
   * Then an unmanaged file at the URI "public://temp.txt" should not exist
   * @endcode
   *
   * @Then an unmanaged file at the URI :uri should not exist
   */
  public function fileAssertUnmanagedNotExists(string $uri): void {
    if (@file_exists($uri)) {
      throw new \Exception(sprintf('The file %s exists but it should not.', $uri));
    }
  }

  /**
   * Assert that an unmanaged file exists and has specified content.
   *
   * @code
   * Then an unmanaged file at the URI "public://config.txt" should contain "debug=true"
   * @endcode
   *
   * @Then an unmanaged file at the URI :uri should contain :content
   */
  public function fileAssertUnmanagedHasContent(string $uri, string $content): void {
    $this->fileAssertUnmanagedExists($uri);

    $file_content = @file_get_contents($uri);
    if ($file_content === FALSE) {
      throw new \Exception(sprintf('Unable to read file %s.', $uri));
    }

    if (!str_contains($file_content, $content)) {
      throw new \Exception(sprintf('File contents "%s" does not contain "%s".', $file_content, $content));
    }
  }

  /**
   * Assert that an unmanaged file exists and does not have specified content.
   *
   * @code
   * Then an unmanaged file at the URI "public://config.txt" should not contain "debug=false"
   * @endcode
   *
   * @Then an unmanaged file at the URI :uri should not contain :content
   */
  public function fileAssertUnmanagedHasNoContent(string $uri, string $content): void {
    $this->fileAssertUnmanagedExists($uri);

    $file_content = @file_get_contents($uri);
    if ($file_content === FALSE) {
      throw new \Exception(sprintf('Unable to read file %s.', $uri));
    }

    if (str_contains($file_content, $content)) {
      throw new \Exception(sprintf('File contents "%s" contains "%s", but should not.', $file_content, $content));
    }
  }

}
