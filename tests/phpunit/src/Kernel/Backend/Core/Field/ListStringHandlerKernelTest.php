<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\ListStringHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\entity_test\Entity\EntityTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for list_string fields via the Core backend.
 *
 * The list_string field is single-property but its handler translates labels
 * to the machine keys declared in the field's allowed_values storage
 * setting. This test exercises that translation end-to-end.
 */
#[CoversClass(ListStringHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class ListStringHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'options',
  ];

  public function testLabelToKeyRoundTrip(): void {
    $this->attachField('field_status', 'list_string', [
      'allowed_values' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
      ],
    ]);

    $this->assertFieldRoundTripViaBackend('field_status', ['Active']);

    // The mutated-stub round-trip passes even when the handler stops
    // converting labels to keys, so the key is asserted explicitly.
    $stub = new EntityStub('entity_test', 'entity_test', [
      'name' => 'pinned',
      'field_status' => ['Active'],
    ]);
    $this->core->createEntity($stub);
    $reloaded = EntityTest::load($stub->getValue('id'));
    $this->assertSame('active', $reloaded->get('field_status')->value);
  }

  public function testKeyPassesThroughRoundTrip(): void {
    $this->attachField('field_status', 'list_string', [
      'allowed_values' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
      ],
    ]);

    // 'inactive' is not a label, so the handler leaves it untouched and the
    // key reaches storage directly.
    $this->assertFieldRoundTripViaBackend('field_status', ['inactive']);
  }

}
