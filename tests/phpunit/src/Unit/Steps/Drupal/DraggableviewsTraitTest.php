<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\DraggableviewsTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests the draggable views order against the capabilities it writes through.
 */
#[CoversTrait(DraggableviewsTrait::class)]
class DraggableviewsTraitTest extends UnitTestCase {

  public function testSetOrderWritesTheWeightsAndInvalidatesTheListTag(): void {
    $nodes = [new EntityStub('node', 'article', ['nid' => '11']), new EntityStub('node', 'article', ['nid' => 12])];

    $backend = $this->createMock(DrupalBackendInterface::class);
    $backend->expects($this->once())->method('draggableviewsSetOrder')->with('demo', 'page_1', [0 => '11', 1 => '12']);
    $backend->expects($this->once())->method('cacheInvalidateTags')->with(['node_list']);

    $this->createContext($backend)->draggableviewsSetOrder('demo', 'page_1', $nodes);
  }

  public function testSaveBundleOrderFailsWithoutTheModule(): void {
    $backend = $this->createStub(DrupalBackendInterface::class);
    $backend->method('moduleIsEnabled')->willReturn(FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('DraggableviewsTrait requires that the "draggableviews" module from the "drupal/draggableviews" package is enabled, which does not hold.');

    $this->createContext($backend)->draggableviewsSaveBundleOrder('demo', 'page_1', 'article', new TableNode([['First']]));
  }

  /**
   * Creates a context whose scenario lists the given backend.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface $backend
   *   The backend the scenario lists.
   */
  protected function createContext(DrupalBackendInterface $backend): DraggableviewsTraitTestImplementation {
    $registry = new BackendRegistry(['drupal' => $backend]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new DraggableviewsTraitTestImplementation();
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of DraggableviewsTrait.
 */
class DraggableviewsTraitTestImplementation extends WebRawContext {

  use DraggableviewsTrait;

}
