<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manage Drupal file entities with upload and storage operations.
 *
 * - Create managed and unmanaged files with specific URIs and content.
 * - Verify file existence, content, and proper storage locations.
 * - Set up file system directories and clean up created files.
 *
 * Skip processing with tag: `@behat-steps-skip:FileTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait FileTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

  /**
   * Unmanaged file URIs.
   *
   * @var array<int, string>
   */
  protected array $fileUnmanagedUris = [];

  /**
   * Ensure private and temp directories exist.
   */
  #[BeforeScenario]
  public function fileBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    $this->backendFor(CoreCapabilityInterface::class);

    $fs = new Filesystem();

    // @codeCoverageIgnoreStart
    $directory = \Drupal::service('file_system')->realpath('private://');
    if ($directory && !$fs->exists($directory)) {
      $fs->mkdir($directory);
    }

    $directory = \Drupal::service('file_system')->realpath('temporary://');
    if ($directory && !$fs->exists($directory)) {
      $fs->mkdir($directory);
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Remove unmanaged files created during the scenario.
   *
   * Managed file entities are removed by the shared entity registry cleanup.
   */
  #[AfterScenario]
  public function fileAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }
    foreach ($this->fileUnmanagedUris as $uri) {
      @unlink($uri);
    }
  }

  /**
   * Create managed files with properties provided in the table.
   *
   * @code
   * Given the following managed files exist:
   *   | path         | uri                    | status |
   *   | document.pdf | public://document.pdf  | 1      |
   *   | image.jpg    | public://images/pic.jpg| 1      |
   * @endcode
   */
  #[Given('the following managed files exist:')]
  public function fileCreateManagedMultiple(TableNode $table): void {
    foreach ($table->getHash() as $hash) {
      if (empty($hash['path'])) {
        throw new \RuntimeException('Missing required column "path".');
      }

      $path = $hash['path'];
      $uri = $hash['uri'] ?? NULL;
      unset($hash['path'], $hash['uri']);

      $stub = new EntityStub('file', NULL, $hash);
      $this->fileCreateManaged($path, $stub, $uri);
    }
  }

  /**
   * Delete managed files defined by provided properties/fields.
   *
   * The column header names a file entity property, such as filename, uri,
   * status or uid.
   *
   * @see Drupal\file\Entity\File
   *
   * @code
   * Given the following managed files do not exist:
   *   | filename      |
   *   | myfile.jpg    |
   *   | otherfile.jpg |
   * @endcode
   *
   * @code
   * Given the following managed files do not exist:
   *   | uri                    |
   *   | public://myfile.jpg    |
   *   | public://otherfile.jpg |
   * @endcode
   */
  #[Given('the following managed files do not exist:')]
  public function fileDeleteManagedMultiple(TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $storage = \Drupal::entityTypeManager()->getStorage('file');

    $field_values = $table->getColumn(0);
    $field_name = array_shift($field_values);

    // @codeCoverageIgnoreStart
    if (is_numeric($field_name)) {
      throw new \RuntimeException('The first column should be the field name.');
    }
    // @codeCoverageIgnoreEnd
    $field_name = (string) $field_name;

    foreach ($field_values as $field_value) {
      $storage->delete($this->fileLoadMultiple([$field_name => (string) $field_value]));
    }
  }

  /**
   * Create an unmanaged file.
   *
   * @code
   * Given the unmanaged file at the URI "public://sample.txt" exists
   * @endcode
   */
  #[Given('the unmanaged file at the URI :uri exists')]
  public function fileCreateUnmanaged(string $uri): void {
    $this->fileCreateUnmanagedWithContent($uri, 'test');
  }

  /**
   * Create an unmanaged file with specified content.
   *
   * @code
   * Given the unmanaged file at the URI "public://data.txt" exists with the content "Sample content"
   * @endcode
   */
  #[Given('the unmanaged file at the URI :uri exists with the content :content')]
  public function fileCreateUnmanagedWithContent(string $uri, string $content): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $directory = \Drupal::service('file_system')->dirname($uri);

    // @codeCoverageIgnoreStart
    if (!file_exists($directory)) {
      $is_prepared = \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY + FileSystemInterface::MODIFY_PERMISSIONS);
      if (!$is_prepared) {
        throw new \RuntimeException(sprintf('Unable to prepare directory "%s".', $directory));
      }
    }
    // @codeCoverageIgnoreEnd
    file_put_contents($uri, $content);

    $this->fileUnmanagedUris[] = $uri;
  }

  /**
   * Assert that an unmanaged file with specified URI exists.
   *
   * @code
   * Then an unmanaged file at the URI "public://sample.txt" should exist
   * @endcode
   */
  #[Then('an unmanaged file at the URI :uri should exist')]
  public function fileAssertUnmanagedExists(string $uri): void {
    $this->backendFor(CoreCapabilityInterface::class);

    if (!@file_exists($uri)) {
      throw new ExpectationException(sprintf('The file "%s" does not exist.', $uri), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file with specified URI does not exist.
   *
   * @code
   * Then an unmanaged file at the URI "public://temp.txt" should not exist
   * @endcode
   */
  #[Then('an unmanaged file at the URI :uri should not exist')]
  public function fileAssertUnmanagedNotExists(string $uri): void {
    $this->backendFor(CoreCapabilityInterface::class);

    if (@file_exists($uri)) {
      throw new ExpectationException(sprintf('The file "%s" exists, but it should not.', $uri), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file exists and has specified content.
   *
   * @code
   * Then an unmanaged file at the URI "public://config.txt" should contain the value "debug=true"
   * @endcode
   */
  #[Then('an unmanaged file at the URI :uri should contain the value :value')]
  public function fileAssertUnmanagedContains(string $uri, string $value): void {
    $this->fileAssertUnmanagedExists($uri);

    $file_content = @file_get_contents($uri);
    // @codeCoverageIgnoreStart
    if ($file_content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read file "%s".', $uri));
    }
    // @codeCoverageIgnoreEnd
    if (!str_contains($file_content, $value)) {
      throw new ExpectationException(sprintf('The file content "%s" does not contain "%s".', $file_content, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file exists and does not have specified content.
   *
   * @code
   * Then an unmanaged file at the URI "public://config.txt" should not contain the value "debug=false"
   * @endcode
   */
  #[Then('an unmanaged file at the URI :uri should not contain the value :value')]
  public function fileAssertUnmanagedNotContains(string $uri, string $value): void {
    $this->fileAssertUnmanagedExists($uri);

    $file_content = @file_get_contents($uri);
    // @codeCoverageIgnoreStart
    if ($file_content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read file "%s".', $uri));
    }
    // @codeCoverageIgnoreEnd
    if (str_contains($file_content, $value)) {
      throw new ExpectationException(sprintf('The file content "%s" contains "%s", but it should not.', $file_content, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Create a single managed file.
   *
   * @param string $path
   *   The source file path relative to 'files_path'.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Entity fields stub (must not contain 'path' or 'uri').
   * @param string|null $uri
   *   Optional destination URI. Defaults to 'public://filename'.
   *
   * @return \Drupal\file\FileInterface
   *   Created file entity.
   */
  public function fileCreateManaged(string $path, EntityStubInterface $stub, ?string $uri = NULL): FileInterface {
    $this->entityLifecycleParseFields($stub);

    $entity = $this->fileCreateEntity($path, $stub, $uri);

    $this->entityLifecycleRegister($entity);

    return $entity;
  }

  /**
   * Create file entity.
   *
   * @param string $path
   *   The source file path relative to 'files_path'.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Entity fields stub.
   * @param string|null $uri
   *   Optional destination URI. Defaults to 'public://filename'.
   *
   * @return \Drupal\file\FileInterface
   *   Created file entity.
   */
  public function fileCreateEntity(string $path, EntityStubInterface $stub, ?string $uri = NULL): FileInterface {
    $this->backendFor(CoreCapabilityInterface::class);

    $path = ltrim($path, '/');

    if (!empty($this->getMinkParameter('files_path'))) {
      $full_path = rtrim((string) realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR) . '/' . $path;
      if (is_file($full_path)) {
        $path = $full_path;
      }
    }

    // @codeCoverageIgnoreStart
    if (!is_readable($path)) {
      throw new \RuntimeException(sprintf('Unable to find file "%s".', $path));
    }
    // @codeCoverageIgnoreEnd
    $destination = 'public://' . basename($path);
    if ($uri !== NULL && $uri !== '') {
      $destination = $uri;
      $directory = dirname($destination);
      $is_prepared = \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY + FileSystemInterface::MODIFY_PERMISSIONS);
      // @codeCoverageIgnoreStart
      if (!$is_prepared) {
        throw new \RuntimeException(sprintf('Unable to prepare directory "%s".', $directory));
      }
      // @codeCoverageIgnoreEnd
    }

    $content = file_get_contents($path);
    // @codeCoverageIgnoreStart
    if ($content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read file "%s".', $path));
    }
    // @codeCoverageIgnoreEnd
    $entity = \Drupal::service('file.repository')->writeData($content, $destination, FileExists::Replace);

    foreach ($stub->getValues() as $property => $value) {
      $entity->set($property, $value);
    }

    $entity->save();

    return $entity;
  }

  /**
   * Load multiple files with specified conditions.
   *
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, \Drupal\file\FileInterface>
   *   The matching files keyed by ID, or an empty array when none match.
   */
  public function fileLoadMultiple(array $conditions = []): array {
    $ids = $this->queryEntityIds('file', $conditions);

    return $ids ? File::loadMultiple($ids) : [];
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function fileConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Create the private and temporary directories before a scenario, and remove the unmanaged files it created afterwards.'),
    ];
  }

}
