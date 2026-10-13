<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\BlockTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that the block steps check for the block module.
 */
#[CoversTrait(BlockTrait::class)]
class BlockTraitTest extends UnitTestCase {

  /**
   * Tests that a step or helper fails on a site without the block module.
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
      $this->fail(sprintf('%s() ran on a site without the block module.', $method));
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('BlockTrait requires that the core "block" module is enabled, which does not hold.', $exception->getMessage());
    }
  }

  public static function dataProviderMissingModuleFails(): \Iterator {
    $configuration = new TableNode([['region', 'content']]);

    yield 'blockCreateInstance' => ['blockCreateInstance', ['Powered by Drupal', $configuration]];
    yield 'blockConfigure' => ['blockConfigure', ['Powered by Drupal', $configuration]];
    yield 'blockDelete' => ['blockDelete', ['Powered by Drupal']];
    yield 'blockEnable' => ['blockEnable', ['Powered by Drupal']];
    yield 'blockDisable' => ['blockDisable', ['Powered by Drupal']];
    yield 'blockConfigureVisibilityCondition' => ['blockConfigureVisibilityCondition', ['Powered by Drupal', 'request_path', $configuration]];
    yield 'blockRemoveVisibilityCondition' => ['blockRemoveVisibilityCondition', ['Powered by Drupal', 'request_path']];
    yield 'blockAssertExists' => ['blockAssertExists', ['Powered by Drupal']];
    yield 'blockAssertNotExists' => ['blockAssertNotExists', ['Powered by Drupal']];
    yield 'blockAssertExistsInRegion' => ['blockAssertExistsInRegion', ['Powered by Drupal', 'content']];
    yield 'blockAssertNotExistsInRegion' => ['blockAssertNotExistsInRegion', ['Powered by Drupal', 'content']];
    yield 'blockCreate' => ['blockCreate', ['Powered by Drupal']];
    yield 'blockGetByLabel' => ['blockGetByLabel', ['Powered by Drupal']];
    yield 'blockFindByLabel' => ['blockFindByLabel', ['Powered by Drupal']];
  }

  /**
   * Creates a context whose Drupal backend reports every module disabled.
   */
  protected function createContext(): BlockTraitTestImplementation {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    $registry = new BackendRegistry(['drupal' => $drupal]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new BlockTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of BlockTrait.
 */
class BlockTraitTestImplementation extends WebRawContext {

  use BlockTrait;

}
