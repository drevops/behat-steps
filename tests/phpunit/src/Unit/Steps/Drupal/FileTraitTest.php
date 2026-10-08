<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\BlackboxBackendInterface;
use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\DrushBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\FileTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the file module checks of the managed file steps and the setup hook.
 */
#[CoversTrait(FileTrait::class)]
class FileTraitTest extends UnitTestCase {

  /**
   * Tests that a managed file step or helper fails without the file module.
   *
   * The trait declares an 'enabled' option, which switches off its hooks but
   * not a step, so the message names no way to switch the trait off.
   *
   * @param string $method
   *   The step or helper to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with.
   */
  #[DataProvider('dataProviderMissingModuleFails')]
  public function testMissingModuleFails(string $method, array $args): void {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    try {
      $this->createContext($drupal)->{$method}(...$args);
      $this->fail(sprintf('%s() ran on a site without the file module.', $method));
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('FileTrait requires that the core "file" module is enabled, for the managed file steps, which does not hold.', $exception->getMessage());
    }
  }

  public static function dataProviderMissingModuleFails(): \Iterator {
    $stub = new EntityStub('file', NULL, ['status' => 1]);

    yield 'fileCreateManagedMultiple' => ['fileCreateManagedMultiple', [new TableNode([['path'], ['document.pdf']])]];
    yield 'fileDeleteManagedMultiple' => ['fileDeleteManagedMultiple', [new TableNode([['filename'], ['document.pdf']])]];
    yield 'fileCreateManaged' => ['fileCreateManaged', ['document.pdf', $stub]];
    yield 'fileCreateEntity' => ['fileCreateEntity', ['document.pdf', $stub]];
    yield 'fileLoadMultiple' => ['fileLoadMultiple', []];
  }

  public function testUnmanagedFileStepsRunWithoutTheModule(): void {
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->expects($this->never())->method('moduleIsEnabled');

    $context = $this->createContext($drupal);
    $path = $this->writeFixture('unmanaged.txt', 'debug=true');

    $context->fileAssertUnmanagedExists($path);
    $context->fileAssertUnmanagedNotExists(static::$tmp . '/missing.txt');
    $context->fileAssertUnmanagedContains($path, 'debug=true');
    $context->fileAssertUnmanagedNotContains($path, 'debug=false');
  }

  /**
   * Tests that the setup hook bootstraps nothing without an in-process backend.
   *
   * @param array<string, class-string<\DrevOps\BehatSteps\Backend\BackendInterface>> $backends
   *   The backend interfaces the scenario lists, keyed by name.
   */
  #[DataProvider('dataProviderSetupHookSkipsSuiteWithoutInProcessBackend')]
  public function testSetupHookSkipsSuiteWithoutInProcessBackend(array $backends): void {
    $doubles = [];

    foreach ($backends as $name => $interface) {
      $backend = $this->createMock($interface);
      $backend->expects($this->never())->method('bootstrap');
      $doubles[$name] = $backend;
    }

    $registry = new BackendRegistry($doubles);
    $registry->setScenarioBackends(array_combine(array_keys($doubles), array_keys($doubles)));

    $context = new FileTraitTestImplementation();
    $context->setBackendRegistry($registry);

    $context->fileBeforeScenario($this->createBeforeScenarioScope());
  }

  public static function dataProviderSetupHookSkipsSuiteWithoutInProcessBackend(): \Iterator {
    yield 'drush' => [['drush' => DrushBackendInterface::class]];
    yield 'blackbox' => [['blackbox' => BlackboxBackendInterface::class]];
    yield 'drush and blackbox' => [['drush' => DrushBackendInterface::class, 'blackbox' => BlackboxBackendInterface::class]];
  }

  /**
   * Creates a context backed by the given Drupal backend.
   *
   * @param \DrevOps\BehatSteps\Backend\DrupalBackendInterface $drupal
   *   The backend the scenario lists.
   */
  protected function createContext(DrupalBackendInterface $drupal): FileTraitTestImplementation {
    $registry = new BackendRegistry(['drupal' => $drupal]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new FileTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of FileTrait.
 */
class FileTraitTestImplementation extends WebRawContext {

  use FileTrait;

}
