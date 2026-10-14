<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the log capability methods on Core.
 *
 * The entries are logged through the 'dblog' module, which writes them to
 * the 'watchdog' table the methods read.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreLogMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'dblog'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system']);

    $this->core = new Core($this->root);
  }

  public function testGetLastIdReportsTheNewestEntry(): void {
    $this->assertSame(0, $this->core->logGetLastId(), 'An empty log reports 0.');

    \Drupal::logger('php')->warning('First');
    $first = $this->core->logGetLastId();
    $this->assertGreaterThan(0, $first);

    \Drupal::logger('php')->warning('Second');
    $this->assertSame($first + 1, $this->core->logGetLastId());
  }

  public function testReadReturnsTheMatchingEntriesOldestFirst(): void {
    \Drupal::logger('php')->error('Before the watermark');
    $watermark = $this->core->logGetLastId();

    \Drupal::logger('php')->notice('Too mild');
    \Drupal::logger('cron')->error('Another type');
    \Drupal::logger('php')->warning('Warning %name', ['%name' => 'first']);
    \Drupal::logger('custom')->error('Custom error');
    \Drupal::logger('php')->error('Error');

    $entries = $this->core->logRead(['php', 'custom'], $watermark, Core::SEVERITY_WARNING);

    $this->assertSame(['Warning %name', 'Custom error', 'Error'], array_column($entries, 'message'));
    $this->assertSame(['php', 'custom', 'php'], array_column($entries, 'type'));
    $this->assertSame([Core::SEVERITY_WARNING, Core::SEVERITY_ERROR, Core::SEVERITY_ERROR], array_column($entries, 'severity'));
    $this->assertSame(['%name' => 'first'], $entries[0]['variables']);
    $this->assertSame([], $entries[1]['variables']);
    $this->assertGreaterThan($watermark, $entries[0]['wid']);
    $this->assertSame([$entries[0]['wid'] + 1, $entries[0]['wid'] + 2], [$entries[1]['wid'], $entries[2]['wid']]);
  }

  public function testReadWithNoTypesReturnsNothing(): void {
    \Drupal::logger('php')->error('Error');

    $this->assertSame([], $this->core->logRead([], 0, Core::SEVERITY_WARNING));
  }

  public function testDeleteRemovesTheEntriesAndToleratesMissingIds(): void {
    \Drupal::logger('php')->error('Kept');
    \Drupal::logger('php')->error('Deleted');
    $deleted = $this->core->logGetLastId();

    $this->core->logDelete([$deleted, $deleted + 100]);
    $this->core->logDelete([]);

    $this->assertSame(['Kept'], array_column($this->core->logRead(['php'], 0, Core::SEVERITY_WARNING), 'message'));
  }

}
