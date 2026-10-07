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
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Helper\Web\FixtureDirectoryTrait;
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
  use FixtureDirectoryTrait;
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

    $this->fileEnsureDirectory('private://');
    $this->fileEnsureDirectory('temporary://');
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

      $this->fileCreateManaged($hash['path'], new EntityStub('file', NULL, array_diff_key($hash, ['path' => TRUE, 'uri' => TRUE])), $hash['uri'] ?? NULL);
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

    $this->assertPrerequisites(__TRAIT__);

    $field_name = $table->getRow(0)[0];

    if (is_numeric($field_name)) {
      throw new \RuntimeException('The first column should be the field name.');
    }

    foreach (array_slice($table->getColumn(0), 1) as $field_value) {
      $this->fileDeleteManaged([$field_name => (string) $field_value]);
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
    $this->fileWriteUnmanaged($uri, 'test');
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
    $this->fileWriteUnmanaged($uri, $content);
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
    $file_content = $this->fileGetUnmanagedContent($uri);

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
    $file_content = $this->fileGetUnmanagedContent($uri);

    if (str_contains($file_content, $value)) {
      throw new ExpectationException(sprintf('The file content "%s" contains "%s", but it should not.', $file_content, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Write an unmanaged file, creating its directory when it is missing.
   *
   * The file is deleted after the scenario.
   *
   * @param string $uri
   *   The file URI.
   * @param string $content
   *   The file content.
   *
   * @throws \RuntimeException
   *   When the directory cannot be created.
   */
  public function fileWriteUnmanaged(string $uri, string $content): void {
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
   * Get the content of an unmanaged file.
   *
   * @param string $uri
   *   The file URI.
   *
   * @return string
   *   The file content.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the file does not exist.
   * @throws \RuntimeException
   *   When the file cannot be read.
   */
  public function fileGetUnmanagedContent(string $uri): string {
    $this->backendFor(CoreCapabilityInterface::class);

    if (!@file_exists($uri)) {
      throw new ExpectationException(sprintf('The file "%s" does not exist.', $uri), $this->getSession()->getDriver());
    }

    $file_content = @file_get_contents($uri);
    // @codeCoverageIgnoreStart
    if ($file_content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read file "%s".', $uri));
    }
    // @codeCoverageIgnoreEnd
    return $file_content;
  }

  /**
   * Delete the managed files that match conditions.
   *
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   */
  public function fileDeleteManaged(array $conditions): void {
    $files = $this->fileLoadMultiple($conditions);

    \Drupal::entityTypeManager()->getStorage('file')->delete($files);
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
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

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

    $this->assertPrerequisites(__TRAIT__);

    $path = ltrim($path, '/');
    $path = $this->fixtureDirectoryFindFile($path) ?? $path;

    if (!is_readable($path)) {
      throw new \RuntimeException(sprintf('Unable to find file "%s".', $path));
    }

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
   * Create the directory a stream wrapper URI points at, when it is missing.
   *
   * @param string $uri
   *   The URI of the directory, such as `private://`.
   */
  protected function fileEnsureDirectory(string $uri): void {
    $directory = \Drupal::service('file_system')->realpath($uri);
    $fs = new Filesystem();

    if ($directory && !$fs->exists($directory)) {
      // @codeCoverageIgnoreStart
      $fs->mkdir($directory);
      // @codeCoverageIgnoreEnd
    }
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
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

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

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function filePrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('file'), 'the core "file" module is enabled, for the managed file steps'),
    ];
  }

}
