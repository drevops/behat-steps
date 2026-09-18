<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Driver\Core\Field;

use DrevOps\BehatSteps\Driver\Core\Field\ListIntegerHandler;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use Drupal\entity_test\Entity\EntityTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel round-trip test for ListIntegerHandler via the Core driver.
 *
 * ListIntegerHandler inherits ListHandlerBase, so the label-to-key translation
 * behaviour mirrors ListStringHandler; the difference is storage stores an
 * integer, not a string. This test verifies the integer key round-trips.
 *
 * @group fields
 */
#[CoversClass(ListIntegerHandler::class)]
#[Group('fields')]
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

  /**
   * Tests that a label is translated to its integer key on round-trip.
   */
  public function testLabelToIntegerKeyRoundTrip(): void {
    $this->attachField('field_priority', 'list_integer', [
      'allowed_values' => [
        1 => 'Low',
        2 => 'Medium',
        3 => 'High',
      ],
    ]);

    // Pass the label; handler replaces with integer key 2.
    $this->assertFieldRoundTripViaDriver('field_priority', ['Medium']);

    // Pin the translation explicitly so a regression where the handler stops
    // converting labels to keys is caught even though the mutated-stub
    // round-trip would otherwise pass.
    $stub = new EntityStub('entity_test', 'entity_test', [
      'name' => 'pinned',
      'field_priority' => ['Medium'],
    ]);
    $this->core->entityCreate($stub);
    $reloaded = EntityTest::load($stub->getValue('id'));
    $this->assertSame('2', $reloaded->get('field_priority')->value);
  }

}
