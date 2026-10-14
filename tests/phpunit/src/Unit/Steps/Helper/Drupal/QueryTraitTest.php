<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Helper\Drupal;

use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the stubs 'QueryTrait' builds from a lookup and reads ids from.
 */
#[CoversTrait(QueryTrait::class)]
class QueryTraitTest extends UnitTestCase {

  public function testEntityStubsBuildsOneStubPerMatchingId(): void {
    $backend = $this->createStub(DrupalBackendInterface::class);
    $backend->method('findEntityIds')->willReturnMap([['node', ['title' => 'Shared'], 'page', ['3', '12']]]);

    $stubs = $this->createContext($backend)->queryEntityStubs('node', 'nid', ['title' => 'Shared'], 'page');

    $this->assertSame([3, 12], array_keys($stubs));
    $this->assertSame('node', $stubs[12]->getEntityType());
    $this->assertSame('page', $stubs[12]->getBundle());
    $this->assertSame(['nid' => '12'], $stubs[12]->getValues());
    $this->assertFalse($stubs[12]->isSaved());
  }

  public function testEntityStubsReturnsAnEmptyArrayWhenNothingMatches(): void {
    $backend = $this->createStub(DrupalBackendInterface::class);
    $backend->method('findEntityIds')->willReturn([]);

    $this->assertSame([], $this->createContext($backend)->queryEntityStubs('menu', 'id', ['label' => 'Missing']));
  }

  /**
   * Tests that the id a stub carries is read as a string.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStub $stub
   *   The stub to read.
   * @param string $expected
   *   The id the stub carries.
   */
  #[DataProvider('dataProviderGetEntityId')]
  public function testGetEntityId(EntityStub $stub, string $expected): void {
    $this->assertSame($expected, (new QueryTraitTestImplementation())->queryGetEntityId($stub, 'nid'));
  }

  public static function dataProviderGetEntityId(): \Iterator {
    yield 'a string id under the id key' => [new EntityStub('node', 'page', ['nid' => '5']), '5'];
    yield 'an integer id under the id key' => [new EntityStub('node', 'page', ['nid' => 5]), '5'];
    yield 'the id of a saved entity' => [
      (new EntityStub('node', 'page'))->markSaved(new class() {

        public function id(): int {
          return 7;
        }

      }),
      '7',
    ];
  }

  /**
   * Tests that a stub carrying no usable id is rejected.
   *
   * @param array<string, mixed> $values
   *   The values of the stub.
   */
  #[DataProvider('dataProviderGetEntityIdFails')]
  public function testGetEntityIdFails(array $values): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The "node" stub carries no id under the key "nid".');

    (new QueryTraitTestImplementation())->queryGetEntityId(new EntityStub('node', 'page', $values), 'nid');
  }

  public static function dataProviderGetEntityIdFails(): \Iterator {
    yield 'no id' => [['title' => 'A title']];
    yield 'an empty id' => [['nid' => '']];
    yield 'an id that is not a scalar' => [['nid' => ['5']]];
  }

  /**
   * Creates a context whose only backend is the given Drupal backend.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface $backend
   *   The backend the lookups resolve.
   */
  protected function createContext(DrupalBackendInterface $backend): QueryTraitTestImplementation {
    $registry = new BackendRegistry(['drupal' => $backend]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new QueryTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of QueryTrait.
 */
class QueryTraitTestImplementation extends WebRawContext {

  use QueryTrait;

}
