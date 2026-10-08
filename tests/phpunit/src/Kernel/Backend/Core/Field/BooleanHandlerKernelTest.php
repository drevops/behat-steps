<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\BooleanHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for BooleanHandler via the Core backend.
 *
 * Asserts that scenarios can populate boolean fields with human-readable
 * words ('Yes', 'Published') instead of 1/0. It also asserts that
 * unrecognized values raise a clear error rather than silently coercing to
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

  /**
   * Tests that a canonical word or a configured label round-trips.
   *
   * Site builders often customize the labels, such as 'Published' and 'Draft'
   * on a workflow field, so a scenario must be able to use them.
   *
   * @param array<string, string> $field_settings
   *   The field settings, carrying the configured labels.
   * @param string $value
   *   The word the scenario passes.
   */
  #[DataProvider('dataProviderWordRoundTrip')]
  public function testWordRoundTrip(array $field_settings, string $value): void {
    $this->attachField('field_flag', 'boolean', [], $field_settings);

    $this->assertFieldRoundTripViaBackend('field_flag', [$value]);
  }

  public static function dataProviderWordRoundTrip(): array {
    $labels = ['on_label' => 'Published', 'off_label' => 'Draft'];

    return [
      'canonical yes' => [[], 'Yes'],
      'canonical no' => [[], 'no'],
      'configured on label' => [$labels, 'Published'],
      'configured off label' => [$labels, 'Draft'],
    ];
  }

  public function testUnrecognizedValueThrows(): void {
    $this->attachField('field_flag', 'boolean');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Cannot convert "maybe" to a boolean/');

    $this->assertFieldRoundTripViaBackend('field_flag', ['maybe']);
  }

}
