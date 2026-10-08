<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\ListFloatHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for ListFloatHandler via the Core backend.
 */
#[CoversClass(ListFloatHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class ListFloatHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'options',
  ];

  public function testLabelToFloatKeyRoundTrip(): void {
    // Every key is fractional, so the stored value exercises float handling.
    // A key like '1.0' normalizes to the integer '1' in storage, which would
    // not distinguish list_float from list_integer.
    $this->attachField('field_rating', 'list_float', [
      'allowed_values' => [
        '0.5' => 'Half',
        '1.5' => 'One and a half',
        '2.5' => 'Two and a half',
      ],
    ]);

    $this->assertFieldRoundTripViaBackend('field_rating', ['Half']);
  }

}
