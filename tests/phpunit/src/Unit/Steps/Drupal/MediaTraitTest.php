<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\MediaTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that the media steps check for the media module.
 */
#[CoversTrait(MediaTrait::class)]
class MediaTraitTest extends UnitTestCase {

  /**
   * Tests that a step or helper fails on a site without the media module.
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
      $this->fail(sprintf('%s() ran on a site without the media module.', $method));
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('MediaTrait requires that the core "media" module is enabled, which does not hold.', $exception->getMessage());
    }
  }

  public static function dataProviderMissingModuleFails(): \Iterator {
    $table = new TableNode([['name'], ['Media item']]);
    $stub = new EntityStub('media', 'image', ['name' => 'Media item']);

    yield 'mediaDeleteType' => ['mediaDeleteType', ['image']];
    yield 'mediaCreateMultiple' => ['mediaCreateMultiple', ['image', $table]];
    yield 'mediaCreateMultipleWithFields' => ['mediaCreateMultipleWithFields', ['image', new TableNode([['name', 'Media item']])]];
    yield 'mediaDeleteMultiple' => ['mediaDeleteMultiple', ['image', $table]];
    yield 'mediaVisitPageWithName' => ['mediaVisitPageWithName', ['image', 'Media item']];
    yield 'mediaVisitEditPageWithName' => ['mediaVisitEditPageWithName', ['image', 'Media item']];
    yield 'mediaVisitDeletePageWithName' => ['mediaVisitDeletePageWithName', ['image', 'Media item']];
    yield 'mediaVisitRevisionsPageWithName' => ['mediaVisitRevisionsPageWithName', ['image', 'Media item']];
    yield 'mediaAssertTypeExists' => ['mediaAssertTypeExists', ['image']];
    yield 'mediaAssertTypeNotExists' => ['mediaAssertTypeNotExists', ['image']];
    yield 'mediaAssertExistsWithName' => ['mediaAssertExistsWithName', ['image', 'Media item']];
    yield 'mediaAssertNotExistsWithName' => ['mediaAssertNotExistsWithName', ['image', 'Media item']];
    yield 'mediaVisitActionPageWithName' => ['mediaVisitActionPageWithName', ['image', 'Media item']];
    yield 'mediaCreate' => ['mediaCreate', [$stub]];
    yield 'mediaCreateEntity' => ['mediaCreateEntity', [$stub]];
    yield 'mediaLoadMultiple' => ['mediaLoadMultiple', ['image']];
  }

  /**
   * Creates a context whose Drupal backend reports every module disabled.
   */
  protected function createContext(): MediaTraitTestImplementation {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    $registry = new BackendRegistry(['drupal' => $drupal]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new MediaTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of MediaTrait.
 */
class MediaTraitTestImplementation extends WebRawContext {

  use MediaTrait;

}
