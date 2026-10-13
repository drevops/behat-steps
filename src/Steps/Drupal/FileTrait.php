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
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\FileCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\FixtureDirectoryTrait;

/**
 * Manage Drupal file entities with upload and storage operations.
 *
 * - Create managed and unmanaged files with specific URIs and content.
 * - Verify file existence, content, and proper storage locations.
 * - Set up file system directories and clean up created files.
 *
 * Skip processing with tag: `@behat-steps-skip:FileTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
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
    // A suite listing no backend that writes files has no directory to
    // prepare.
    if ($this->skipTag(__TRAIT__, $scope) || !$this->getBackendRegistry()->hasCapability(FileCapabilityInterface::class)) {
      return;
    }

    $backend = $this->backendFor(FileCapabilityInterface::class);

    // A stream wrapper the site has not configured cannot be prepared and
    // needs no directory, so the result is not checked.
    $backend->filePrepareDirectory('private://');
    $backend->filePrepareDirectory('temporary://');
  }

  /**
   * Remove unmanaged files created during the scenario.
   *
   * Managed file entities are removed by the shared entity registry cleanup.
   */
  #[AfterScenario]
  public function fileAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope) || $this->fileUnmanagedUris === []) {
      return;
    }

    $backend = $this->backendFor(FileCapabilityInterface::class);

    foreach ($this->fileUnmanagedUris as $uri) {
      $backend->fileDeleteUnmanaged($uri);
    }

    $this->fileUnmanagedUris = [];
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
   *
   * @actor:system
   */
  #[Given('the following managed files exist:')]
  public function fileCreateManagedMultiple(TableNode $table): void {
    foreach ($table->getHash() as $hash) {
      if (empty($hash['path'])) {
        throw new \RuntimeException('Missing required column "path".');
      }

      $this->fileCreateManaged($hash['path'], $this->fileBuildManagedStub($hash), $hash['uri'] ?? NULL);
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
   *
   * @actor:system
   */
  #[Given('the following managed files do not exist:')]
  public function fileDeleteManagedMultiple(TableNode $table): void {
    $this->backendFor(ContentCapabilityInterface::class);

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
   *
   * @actor:system
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
   *
   * @actor:system
   */
  #[Given('the unmanaged file at the URI :uri exists with the content :content')]
  public function fileCreateUnmanagedWithContent(string $uri, string $content): void {
    $this->fileWriteUnmanaged($uri, $content);
  }

  /**
   * Assert that an unmanaged file with specified URI exists.
   *
   * @code
   * Then the unmanaged file at the URI "public://sample.txt" should exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the unmanaged file at the URI :uri should exist')]
  public function fileAssertUnmanagedExists(string $uri): void {
    if (!$this->backendFor(FileCapabilityInterface::class)->fileExistsUnmanaged($uri)) {
      throw new ExpectationException(sprintf('The file "%s" does not exist.', $uri), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file with specified URI does not exist.
   *
   * @code
   * Then the unmanaged file at the URI "public://temp.txt" should not exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the unmanaged file at the URI :uri should not exist')]
  public function fileAssertUnmanagedNotExists(string $uri): void {
    if ($this->backendFor(FileCapabilityInterface::class)->fileExistsUnmanaged($uri)) {
      throw new ExpectationException(sprintf('The file "%s" exists, but it should not.', $uri), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file exists and has specified content.
   *
   * @code
   * Then the unmanaged file at the URI "public://config.txt" should contain "debug=true"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the unmanaged file at the URI :uri should contain :content')]
  public function fileAssertUnmanagedContains(string $uri, string $content): void {
    $file_content = $this->fileGetUnmanagedContent($uri);

    if (!str_contains($file_content, $content)) {
      throw new ExpectationException(sprintf('The file content "%s" does not contain "%s".', $file_content, $content), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file exists and does not have specified content.
   *
   * @code
   * Then the unmanaged file at the URI "public://config.txt" should not contain "debug=false"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the unmanaged file at the URI :uri should not contain :content')]
  public function fileAssertUnmanagedNotContains(string $uri, string $content): void {
    $file_content = $this->fileGetUnmanagedContent($uri);

    if (str_contains($file_content, $content)) {
      throw new ExpectationException(sprintf('The file content "%s" contains "%s", but it should not.', $file_content, $content), $this->getSession()->getDriver());
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
   *   When the directory cannot be created or the file cannot be written.
   */
  public function fileWriteUnmanaged(string $uri, string $content): void {
    $this->backendFor(FileCapabilityInterface::class)->fileWriteUnmanaged($uri, $content);

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
    $backend = $this->backendFor(FileCapabilityInterface::class);

    if (!$backend->fileExistsUnmanaged($uri)) {
      throw new ExpectationException(sprintf('The file "%s" does not exist.', $uri), $this->getSession()->getDriver());
    }

    return $backend->fileReadUnmanaged($uri);
  }

  /**
   * Build a managed file stub from a set of values.
   *
   * @param array<string, string> $values
   *   The values. The "path" and "uri" values locate the file rather than
   *   describe it, so the stub leaves them out.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStub
   *   The file stub.
   */
  protected function fileBuildManagedStub(array $values): EntityStub {
    return new EntityStub('file', NULL, array_diff_key($values, ['path' => TRUE, 'uri' => TRUE]));
  }

  /**
   * Delete the managed files that match conditions.
   *
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   */
  public function fileDeleteManaged(array $conditions): void {
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($this->queryEntityIds('file', $conditions) as $fid) {
      $backend->deleteEntity(new EntityStub('file', NULL, ['fid' => $fid]));
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
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created file, carrying its 'fid'.
   */
  public function fileCreateManaged(string $path, EntityStubInterface $stub, ?string $uri = NULL): EntityStubInterface {
    $this->backendFor(FileCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->entityLifecycleParseFields($stub);

    $created = $this->fileCreateEntity($path, $stub, $uri);

    $this->entityLifecycleRegister($created, 'fid');

    return $created;
  }

  /**
   * Create file entity.
   *
   * @param string $path
   *   The source file path relative to 'files_path'.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Entity fields stub. Its values are set on the file once it is written.
   * @param string|null $uri
   *   Optional destination URI. Defaults to 'public://filename'.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created file, carrying its 'fid'.
   */
  public function fileCreateEntity(string $path, EntityStubInterface $stub, ?string $uri = NULL): EntityStubInterface {
    $backend = $this->backendFor(FileCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $path = ltrim($path, '/');
    $path = $this->fixtureDirectoryFindFile($path) ?? $path;

    if (!is_readable($path)) {
      throw new \RuntimeException(sprintf('Unable to find file "%s".', $path));
    }

    $content = file_get_contents($path);

    // @codeCoverageIgnoreStart
    if ($content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read file "%s".', $path));
    }

    // @codeCoverageIgnoreEnd
    $destination = $uri === NULL || $uri === '' ? 'public://' . basename($path) : $uri;
    $fid = $this->queryGetEntityId($backend->fileWriteManaged($destination, $content, new EntityStub('file')), 'fid');

    if ($stub->getValues() !== []) {
      $this->backendFor(ContentCapabilityInterface::class)->updateEntity(new EntityStub('file', NULL, ['fid' => $fid] + $stub->getValues()));
    }

    return new EntityStub('file', NULL, ['fid' => $fid]);
  }

  /**
   * Load multiple files with specified conditions.
   *
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching file carrying its 'fid', keyed by ID, or an empty
   *   array when none match.
   */
  public function fileLoadMultiple(array $conditions = []): array {
    $this->assertPrerequisites(__TRAIT__);

    return $this->queryEntityStubs('file', 'fid', $conditions);
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
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
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function filePrerequisites(): array {
    return [
      Prerequisite::capability(FileCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('file'), 'the core "file" module is enabled, for the managed file steps'),
    ];
  }

}
