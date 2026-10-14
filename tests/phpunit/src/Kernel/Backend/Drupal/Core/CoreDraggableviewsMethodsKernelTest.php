<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the draggableviews capability method on Core.
 *
 * The 'demo' view lists nodes, so an order saved for it invalidates the node
 * list tag beside the view's own tags.
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
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'views', 'draggableviews'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('draggableviews', ['draggableviews_structure']);
    $this->installConfig(['system']);

    $displays = [
      'default' => ['display_plugin' => 'default', 'id' => 'default', 'display_title' => 'Default', 'position' => 0, 'display_options' => []],
      'page_1' => ['display_plugin' => 'page', 'id' => 'page_1', 'display_title' => 'Page', 'position' => 1, 'display_options' => ['path' => 'demo']],
    ];
    View::create(['id' => 'demo', 'label' => 'Demo', 'base_table' => 'node_field_data', 'base_field' => 'nid', 'display' => $displays])->save();

    $this->core = new Core($this->root);
  }

  public function testSetOrderWritesAndReplacesTheWeights(): void {
    $this->core->draggableviewsSetOrder('demo', 'page_1', [0 => '1', 1 => '2']);

    $this->assertSame(['1' => 0, '2' => 1], $this->readWeights('demo', 'page_1'));

    $this->core->draggableviewsSetOrder('demo', 'page_1', [0 => '2', 1 => '1', 2 => '3']);

    $this->assertSame(['2' => 0, '1' => 1, '3' => 2], $this->readWeights('demo', 'page_1'), 'An entity already ordered takes its new weight.');
    $this->assertSame([], $this->readWeights('demo', 'page_2'), 'Another display is untouched.');
  }

  public function testSetOrderInvalidatesTheListAndTheViewTags(): void {
    $cache = \Drupal::cache();
    $cache->set('list', 'cached', Cache::PERMANENT, ['node_list']);
    $cache->set('view', 'cached', Cache::PERMANENT, ['config:views.view.demo']);
    $cache->set('display', 'cached', Cache::PERMANENT, ['config:views.view.demo.page_1']);
    $cache->set('other_display', 'cached', Cache::PERMANENT, ['config:views.view.demo.page_2']);

    $this->core->draggableviewsSetOrder('demo', 'page_1', [0 => '1']);

    $this->assertFalse($cache->get('list'), 'The list tag of the base entity type is invalidated.');
    $this->assertFalse($cache->get('view'), 'The tag of the view is invalidated.');
    $this->assertFalse($cache->get('display'), 'The tag of the display is invalidated.');
    $this->assertNotFalse($cache->get('other_display'), 'Another display of the view keeps its caches.');
  }

  public function testSetOrderRejectsAnUnknownView(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No view with the id "missing" exists.');

    $this->core->draggableviewsSetOrder('missing', 'page_1', [0 => '1']);
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
