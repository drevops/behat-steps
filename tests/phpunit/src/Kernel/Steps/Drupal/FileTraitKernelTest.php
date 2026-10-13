<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\TestResult;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Steps\Drupal\FileTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for loading managed files through 'FileTrait'.
 */
#[CoversTrait(FileTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class FileTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
  }

  public function testLoadMultipleLoadsTheMatchingFiles(): void {
    $first = $this->createFile('public://first/shared.txt');
    $second = $this->createFile('public://second/shared.txt');
    $this->createFile('public://other.txt');

    $files = $this->context->fileLoadMultiple(['filename' => 'shared.txt']);

    $this->assertStubSet([$first, $second], $files, 'fid');
  }

  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createFile('public://other.txt');

    $this->assertSame([], $this->context->fileLoadMultiple(['filename' => 'missing.txt']));
  }

  public function testCreateManagedWritesTheFileAndSetsTheValues(): void {
    $this->context->setMinkParameters(['files_path' => __DIR__ . '/../../../../fixtures/backend/files']);

    $created = $this->context->fileCreateManaged('sample.txt', new EntityStub('file', NULL, ['uuid' => '9cb1b484-db7b-4496-bd63-8c702e207704', 'status' => '0']));

    $this->assertFalse($created->isSaved(), 'The stub carries the ID rather than the written file.');

    $file = File::load($created->getValue('fid'));
    $this->assertInstanceOf(FileInterface::class, $file);
    $this->assertSame('public://sample.txt', $file->getFileUri());
    $this->assertSame('9cb1b484-db7b-4496-bd63-8c702e207704', $file->uuid());
    $this->assertFalse($file->isPermanent());
  }

  public function testCreateManagedLeavesNoFileWhenValueFailsToResolve(): void {
    $this->context->setMinkParameters(['files_path' => __DIR__ . '/../../../../fixtures/backend/files']);

    try {
      $this->context->fileCreateManaged('sample.txt', new EntityStub('file', NULL, ['uid' => 'nobody']));
      $this->fail('A value that names no user did not fail the create.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('No entity "nobody" of type "user" exists.', $exception->getMessage());
    }

    $this->assertCount(1, File::loadMultiple(), 'The file is written before its values are set.');

    $this->runEntityCleanup();

    $this->assertCount(0, File::loadMultiple(), 'The cleanup removes the file the failed create wrote.');
  }

  public function testCreateEntityWithoutValuesLeavesTheWrittenFileAsItIs(): void {
    $this->context->setMinkParameters(['files_path' => __DIR__ . '/../../../../fixtures/backend/files']);

    $created = $this->context->fileCreateEntity('sample.txt', new EntityStub('file'), 'public://nested/copy.txt');

    $file = File::load($created->getValue('fid'));
    $this->assertInstanceOf(FileInterface::class, $file);
    $this->assertSame('public://nested/copy.txt', $file->getFileUri());
    $this->assertTrue($file->isPermanent());
  }

  /**
   * Creates and saves a managed file record, without writing the file.
   */
  protected function createFile(string $uri): FileInterface {
    $file = File::create(['uri' => $uri, 'filename' => basename($uri)]);
    $file->save();

    return $file;
  }

  /**
   * Runs the entity cleanup the context runs after a scenario.
   */
  protected function runEntityCleanup(): void {
    $scenario = new ScenarioNode('Scenario', [], [], 'Scenario', 1);
    $feature = new FeatureNode('Feature', NULL, [], NULL, [$scenario], 'Feature', 'en', __FILE__, 1);

    $this->context->entityLifecycleAfterScenario(new AfterScenarioScope($this->createStub(Environment::class), $feature, $scenario, $this->createStub(TestResult::class)));
  }

}
