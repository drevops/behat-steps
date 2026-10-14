<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the queue capability methods on Core.
 *
 * The fixture site's 'behat_test' queue worker lowers the 'queue_budget'
 * value of the 'mysite_core.settings' configuration once per item, which
 * shows how many items it processed.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreQueueMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'mysite_core'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // 'mysite_core.settings', the fixture module's queue budget, has no
    // schema. Drupal 11 and 12 type the flag differently, so an override of
    // the property loads on only 1 of them.
    $this->strictConfigSchema = FALSE;

    parent::setUp();

    $this->installConfig(['system']);

    $this->core = new Core($this->root);
  }

  public function testQueueLifecycle(): void {
    $this->assertSame(0, $this->core->queueCountItems('behat_test'));

    $this->core->queueAddItem('behat_test', ['nid' => 1]);
    $this->core->queueAddItem('behat_test', ['nid' => 2]);
    $this->core->queueAddItem('behat_test', ['nid' => 3]);

    $this->assertSame(3, $this->core->queueCountItems('behat_test'));

    \Drupal::configFactory()->getEditable('mysite_core.settings')->set('queue_budget', 20)->save();

    $this->assertSame(2, $this->core->queueProcess('behat_test', 2, 30));
    $this->assertSame(1, $this->core->queueCountItems('behat_test'));
    $this->assertSame(18, \Drupal::config('mysite_core.settings')->get('queue_budget'), 'The worker processed 2 items.');

    $this->assertSame(1, $this->core->queueProcess('behat_test', 5, 30), 'Processing stops when the queue runs out.');
    $this->assertSame(0, $this->core->queueProcess('behat_test', 0, 30), 'A limit of 0 processes nothing.');

    $this->core->queueAddItem('behat_test', ['nid' => 4]);
    $this->core->queueDelete('behat_test');
    $this->core->queueDelete('behat_test');

    $this->assertSame(0, $this->core->queueCountItems('behat_test'));
  }

  public function testProcessRejectsQueueWithoutWorker(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No queue worker processes the queue "no_worker".');

    $this->core->queueProcess('no_worker', 1, 30);
  }

}
