<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\ContentBlockTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that the content block steps check for the block_content module.
 */
#[CoversTrait(ContentBlockTrait::class)]
class ContentBlockTraitTest extends UnitTestCase {

  /**
   * Tests that a step or helper fails on a site without the module.
   *
   * @param string $method
   *   The step or helper to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with.
   */
  #[DataProvider('dataProviderMissingModuleFails')]
  public function testMissingModuleFails(string $method, array $args): void {
    try {
      $this->createContext()->{$method}(...$args);
      $this->fail(sprintf('%s() ran on a site without the block_content module.', $method));
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('ContentBlockTrait requires that the core "block_content" module is enabled, which does not hold.', $exception->getMessage());
    }
  }

  public static function dataProviderMissingModuleFails(): \Iterator {
    $table = new TableNode([['info'], ['[TEST] Block']]);

    yield 'contentBlockDeleteMultiple' => ['contentBlockDeleteMultiple', ['basic', $table]];
    yield 'contentBlockCreateMultiple' => ['contentBlockCreateMultiple', ['basic', $table]];
    yield 'contentBlockCreateMultipleWithFields' => ['contentBlockCreateMultipleWithFields', ['basic', new TableNode([['info', '[TEST] Block']])]];
    yield 'contentBlockVisitEditPageWithDescription' => ['contentBlockVisitEditPageWithDescription', ['basic', '[TEST] Block']];
    yield 'contentBlockAssertTypeExists' => ['contentBlockAssertTypeExists', ['basic']];
    yield 'contentBlockCreate' => ['contentBlockCreate', ['basic', ['info' => '[TEST] Block']]];
    yield 'contentBlockLoadMultiple' => ['contentBlockLoadMultiple', ['basic']];
    yield 'contentBlockGetByDescription' => ['contentBlockGetByDescription', ['basic', '[TEST] Block']];
    yield 'contentBlockDelete' => ['contentBlockDelete', ['basic', ['info' => '[TEST] Block']]];
  }

  /**
   * Creates a context whose Drupal backend reports every module disabled.
   */
  protected function createContext(): ContentBlockTraitTestImplementation {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    $registry = new BackendRegistry(['drupal' => $drupal]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new ContentBlockTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of ContentBlockTrait.
 */
class ContentBlockTraitTestImplementation extends WebRawContext {

  use ContentBlockTrait;

}
