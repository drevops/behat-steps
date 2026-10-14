<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the draggableviews capability method on Core.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreDraggableviewsMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'views', 'draggableviews'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('draggableviews', ['draggableviews_structure']);

    $this->core = new Core($this->root);
  }

  public function testSetOrderWritesAndReplacesTheWeights(): void {
    $this->core->draggableviewsSetOrder('demo', 'page_1', [0 => '1', 1 => '2']);

    $this->assertSame(['1' => 0, '2' => 1], $this->readWeights('demo', 'page_1'));

    $this->core->draggableviewsSetOrder('demo', 'page_1', [0 => '2', 1 => '1', 2 => '3']);

    $this->assertSame(['2' => 0, '1' => 1, '3' => 2], $this->readWeights('demo', 'page_1'), 'An entity already ordered takes its new weight.');
    $this->assertSame([], $this->readWeights('demo', 'page_2'), 'Another display is untouched.');
  }

  /**
   * Reads the weights a display stores, keyed by entity id.
   *
   * @param string $view_id
   *   The view id.
   * @param string $display_id
   *   The display id.
   *
   * @return array<string, int>
   *   The weights keyed by entity id, in weight order.
   */
  protected function readWeights(string $view_id, string $display_id): array {
    $rows = Database::getConnection()->select('draggableviews_structure', 'd')
      ->fields('d', ['entity_id', 'weight'])
      ->condition('view_name', $view_id)
      ->condition('view_display', $display_id)
      ->orderBy('weight')
      ->execute()
      ->fetchAllKeyed();

    return array_map(intval(...), $rows);
  }

}
