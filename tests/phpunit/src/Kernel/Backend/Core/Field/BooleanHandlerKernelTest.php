<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\BooleanHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for BooleanHandler via the Core backend.
 *
 * Asserts that scenarios can populate boolean fields with human-readable
 * words ('Yes', 'Published') instead of 1/0. It also asserts that
 * unrecognised values raise a clear error rather than silently coercing to
 * FALSE.
 */
#[CoversClass(BooleanHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class BooleanHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
  ];

  public function testCanonicalYesRoundTrip(): void {
    $this->attachField('field_flag', 'boolean');
    $this->assertFieldRoundTripViaBackend('field_flag', ['Yes']);
  }

  public function testCanonicalNoRoundTrip(): void {
    $this->attachField('field_flag', 'boolean');
    $this->assertFieldRoundTripViaBackend('field_flag', ['no']);
  }

  /**
   * Tests the field's configured on_label takes priority over canonical forms.
   *
   * Site builders often customise the labels (e.g. 'Published'/'Draft' on a
   * publishing workflow field), so a scenario must be able to use those exact
   * words.
   */
  public function testFieldOnLabelResolvesToTrue(): void {
    $this->attachField('field_flag', 'boolean', [], [
      'on_label' => 'Published',
      'off_label' => 'Draft',
    ]);

    $this->assertFieldRoundTripViaBackend('field_flag', ['Published']);
  }

  public function testFieldOffLabelResolvesToFalse(): void {
    $this->attachField('field_flag', 'boolean', [], [
      'on_label' => 'Published',
      'off_label' => 'Draft',
    ]);

    $this->assertFieldRoundTripViaBackend('field_flag', ['Draft']);
  }

  public function testUnrecognizedValueThrows(): void {
    $this->attachField('field_flag', 'boolean');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Cannot convert "maybe" to a boolean/');

    $this->assertFieldRoundTripViaBackend('field_flag', ['maybe']);
  }

}
