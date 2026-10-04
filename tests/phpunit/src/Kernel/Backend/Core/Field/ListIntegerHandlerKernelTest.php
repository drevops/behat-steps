<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\ListIntegerHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\entity_test\Entity\EntityTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for ListIntegerHandler via the Core backend.
 *
 * ListIntegerHandler inherits ListHandlerBase, so its label-to-key
 * translation matches ListStringHandler's; the difference is that storage
 * holds an integer, not a string.
 */
#[CoversClass(ListIntegerHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class ListIntegerHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'options',
  ];

  public function testLabelToIntegerKeyRoundTrip(): void {
    $this->attachField('field_priority', 'list_integer', [
      'allowed_values' => [
        1 => 'Low',
        2 => 'Medium',
        3 => 'High',
      ],
    ]);

    $this->assertFieldRoundTripViaBackend('field_priority', ['Medium']);

    // The mutated-stub round-trip passes even when the handler stops
    // converting labels to keys, so the key is asserted explicitly.
    $stub = new EntityStub('entity_test', 'entity_test', [
      'name' => 'pinned',
      'field_priority' => ['Medium'],
    ]);
    $this->core->entityCreate($stub);
    $reloaded = EntityTest::load($stub->getValue('id'));
    $this->assertSame('2', $reloaded->get('field_priority')->value);
  }

}
