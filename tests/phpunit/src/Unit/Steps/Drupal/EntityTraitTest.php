<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\EntityTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the entity reference autocomplete steps of EntityTrait.
 */
#[CoversTrait(EntityTrait::class)]
class EntityTraitTest extends UnitTestCase {

  /**
   * The page served by the session under test.
   */
  protected DocumentElement&MockObject $page;

  /**
   * Tests that a step fills the field with the newest entity's label and id.
   *
   * @param string $method
   *   The step to call.
   * @param array<int, string> $args
   *   The arguments to call it with.
   * @param string|null $bundle
   *   The bundle the lookup is expected to be restricted to.
   * @param string $label
   *   The label the lookup is expected to match.
   * @param array<int, string> $ids
   *   The ids the lookup returns.
   * @param string $expected
   *   The value the field is expected to receive.
   */
  #[DataProvider('dataProviderStepFillsTheField')]
  public function testStepFillsTheField(string $method, array $args, ?string $bundle, string $label, array $ids, string $expected): void {
    $backend = $this->createBackend(TRUE);
    $backend->expects($this->once())->method('findEntityIds')->with('taxonomy_term', ['name' => $label], $bundle)->willReturn($ids);

    $context = $this->createContext($backend);
    $this->page->expects($this->once())->method('fillField')->with('Tags', $expected);

    $context->{$method}(...$args);
  }

  public static function dataProviderStepFillsTheField(): \Iterator {
    yield 'any bundle' => ['entityFillReferenceFieldWithLabel', ['Tags', 'taxonomy_term', 'Jazz'], NULL, 'Jazz', ['3'], 'Jazz (3)'];
    yield 'a bundle' => ['entityFillReferenceFieldOfBundleWithLabel', ['Tags', 'tags', 'taxonomy_term', 'Jazz'], 'tags', 'Jazz', ['3'], 'Jazz (3)'];
    yield 'the newest of several' => ['entityFillReferenceFieldOfBundleWithLabel', ['Tags', 'tags', 'taxonomy_term', 'Jazz'], 'tags', 'Jazz', ['3', '9', '12'], 'Jazz (12)'];
    yield 'a label with a comma' => ['entityFillReferenceFieldWithLabel', ['Tags', 'taxonomy_term', 'Rock, pop'], NULL, 'Rock, pop', ['4'], '"Rock, pop (4)"'];
    yield 'a label with a double quote' => ['entityFillReferenceFieldWithLabel', ['Tags', 'taxonomy_term', 'The "best" band'], NULL, 'The "best" band', ['5'], '"The ""best"" band (5)"'];
  }

  /**
   * Tests that a lookup that cannot resolve an id fails naming what it sought.
   *
   * @param string|null $bundle
   *   The bundle to look up in.
   * @param bool $bundle_exists
   *   Whether the bundle exists.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderGetIdByLabelFails')]
  public function testGetIdByLabelFails(?string $bundle, bool $bundle_exists, string $expected_message): void {
    $backend = $this->createBackend($bundle_exists);
    $backend->method('findEntityIds')->willReturn([]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->createContext($backend)->entityGetIdByLabel('taxonomy_term', 'Jazz', $bundle);
  }

  public static function dataProviderGetIdByLabelFails(): \Iterator {
    yield 'an unknown bundle' => ['missing', FALSE, 'The entity type "taxonomy_term" has no bundle "missing".'];
    yield 'no match in a bundle' => ['tags', TRUE, 'The "tags" "taxonomy_term" entity with the label "Jazz" does not exist.'];
    yield 'no match in any bundle' => [NULL, TRUE, 'The "taxonomy_term" entity with the label "Jazz" does not exist.'];
  }

  /**
   * Creates a backend that resolves entity definitions and lookups.
   *
   * @param bool $bundle_exists
   *   What 'bundleExists()' returns.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface&\DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend.
   */
  protected function createBackend(bool $bundle_exists): BackendInterface&EntityDefinitionCapabilityInterface&EntityLookupCapabilityInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface&\DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject $backend */
    $backend = $this->createMockForIntersectionOfInterfaces([BackendInterface::class, EntityDefinitionCapabilityInterface::class, EntityLookupCapabilityInterface::class]);
    $backend->method('getEntityLabelKey')->willReturn('name');
    $backend->method('bundleExists')->willReturn($bundle_exists);

    return $backend;
  }

  /**
   * Creates a context backed by the given backend, with a mocked page.
   *
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend the scenario lists.
   */
  protected function createContext(BackendInterface $backend): EntityTraitTestImplementation {
    $registry = new BackendRegistry(['test' => $backend]);
    $registry->setScenarioBackends(['test' => 'test']);

    $this->page = $this->createMock(DocumentElement::class);
    $session = $this->createMock(Session::class);
    $session->method('getPage')->willReturn($this->page);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $context = new EntityTraitTestImplementation();
    $context->setBackendRegistry($registry);
    $context->setMink($mink);

    return $context;
  }

}

/**
 * Test implementation of EntityTrait.
 */
class EntityTraitTestImplementation extends WebRawContext {

  use EntityTrait;

}
