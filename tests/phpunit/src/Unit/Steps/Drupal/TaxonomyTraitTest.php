<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\TaxonomyTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that the taxonomy steps check for the taxonomy module.
 */
#[CoversTrait(TaxonomyTrait::class)]
class TaxonomyTraitTest extends UnitTestCase {

  /**
   * Tests that a step or helper fails on a site without the taxonomy module.
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
      $this->fail(sprintf('%s() ran on a site without the taxonomy module.', $method));
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('TaxonomyTrait requires that the core "taxonomy" module is enabled, which does not hold.', $exception->getMessage());
    }
  }

  public static function dataProviderMissingModuleFails(): \Iterator {
    yield 'taxonomyDeleteMultiple' => ['taxonomyDeleteMultiple', ['tags', new TableNode([['Apple']])]];
    yield 'taxonomyVisitTermPageWithName' => ['taxonomyVisitTermPageWithName', ['tags', 'Apple']];
    yield 'taxonomyVisitTermEditPageWithName' => ['taxonomyVisitTermEditPageWithName', ['tags', 'Apple']];
    yield 'taxonomyVisitTermDeletePageWithName' => ['taxonomyVisitTermDeletePageWithName', ['tags', 'Apple']];
    yield 'taxonomyAssertVocabularyExists' => ['taxonomyAssertVocabularyExists', ['tags', 'Tags']];
    yield 'taxonomyAssertVocabularyNotExists' => ['taxonomyAssertVocabularyNotExists', ['tags']];
    yield 'taxonomyAssertTermExistsWithName' => ['taxonomyAssertTermExistsWithName', ['Apple', 'tags']];
    yield 'taxonomyAssertTermNotExistsWithName' => ['taxonomyAssertTermNotExistsWithName', ['Apple', 'tags']];
    yield 'taxonomyVisitActionPageWithName' => ['taxonomyVisitActionPageWithName', ['tags', 'Apple']];
    yield 'taxonomyLoadMultiple' => ['taxonomyLoadMultiple', ['tags']];
  }

  /**
   * Tests that term creation runs on a backend without Drupal's API.
   *
   * No backend in the list provides 'CoreCapabilityInterface', so a check of
   * the trait's prerequisites would fail first. The context runs outside
   * Behat, so creation stops at its first hook dispatch instead.
   *
   * @param string $method
   *   The creation step to call.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table to call it with.
   */
  #[DataProvider('dataProviderTermCreationChecksNoPrerequisite')]
  public function testTermCreationChecksNoPrerequisite(string $method, TableNode $table): void {
    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface&\PHPUnit\Framework\MockObject\Stub $content */
    $content = $this->createStubForIntersectionOfInterfaces([BackendInterface::class, ContentCapabilityInterface::class]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The hook dispatcher is available only after Behat has initialized the context.');

    $this->createContext($content)->{$method}('tags', $table);
  }

  public static function dataProviderTermCreationChecksNoPrerequisite(): \Iterator {
    yield 'taxonomyCreateMultiple' => ['taxonomyCreateMultiple', new TableNode([['name'], ['Apple']])];
    yield 'taxonomyCreateMultipleWithFields' => ['taxonomyCreateMultipleWithFields', new TableNode([['name', 'Apple']])];
  }

  /**
   * Creates a context backed by the given backend.
   *
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend the scenario lists.
   */
  protected function createContext(BackendInterface $backend): TaxonomyTraitTestImplementation {
    $registry = new BackendRegistry(['test' => $backend]);
    $registry->setScenarioBackends(['test' => 'test']);

    $context = new TaxonomyTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of TaxonomyTrait.
 */
class TaxonomyTraitTestImplementation extends WebRawContext {

  use TaxonomyTrait;

}
