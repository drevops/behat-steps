<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\ColorFieldTypeHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ColorFieldTypeHandler field handler.
 */
#[CoversClass(ColorFieldTypeHandler::class)]
#[Group('fields')]
class ColorFieldTypeHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    return $this->createHandlerWithSettings(['opacity' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'NULL leaves the field unset' => [
      NULL,
      [],
      NULL,
      NULL,
    ];
    yield 'empty string leaves the field unset' => [
      '',
      [],
      NULL,
      NULL,
    ];
    yield 'empty array leaves the field unset' => [
      [],
      [],
      NULL,
      NULL,
    ];
    yield 'bare hex string' => [
      '#502001',
      [['color' => '#502001', 'opacity' => NULL]],
      NULL,
      NULL,
    ];
    yield 'bare hex string without a hash' => [
      '502001',
      [['color' => '502001', 'opacity' => NULL]],
      NULL,
      NULL,
    ];
    yield 'keyed record with opacity' => [
      ['color' => '#502001', 'opacity' => '0.5'],
      [['color' => '#502001', 'opacity' => '0.5']],
      NULL,
      NULL,
    ];
    yield 'keyed record with numeric opacity' => [
      ['color' => '#502001', 'opacity' => 0.5],
      [['color' => '#502001', 'opacity' => 0.5]],
      NULL,
      NULL,
    ];
    yield 'keyed record without opacity' => [
      ['color' => '#502001'],
      [['color' => '#502001', 'opacity' => NULL]],
      NULL,
      NULL,
    ];
    yield 'keyed record with empty opacity' => [
      ['color' => '#502001', 'opacity' => ''],
      [['color' => '#502001', 'opacity' => NULL]],
      NULL,
      NULL,
    ];
    yield 'keyed record with a zero opacity' => [
      ['color' => '#502001', 'opacity' => '0'],
      [['color' => '#502001', 'opacity' => '0']],
      NULL,
      NULL,
    ];
    yield 'keyed record with both values empty leaves the field unset' => [
      ['color' => '', 'opacity' => ''],
      [],
      NULL,
      NULL,
    ];
    yield 'keyed record with both values NULL leaves the field unset' => [
      ['color' => NULL, 'opacity' => NULL],
      [],
      NULL,
      NULL,
    ];
    yield 'list of hex strings' => [
      ['#502001', '#FFFFFF'],
      [
        ['color' => '#502001', 'opacity' => NULL],
        ['color' => '#FFFFFF', 'opacity' => NULL],
      ],
      NULL,
      NULL,
    ];
    yield 'list of keyed records' => [
      [
        ['color' => '#502001', 'opacity' => '0.5'],
        ['color' => '#FFFFFF', 'opacity' => '1'],
      ],
      [
        ['color' => '#502001', 'opacity' => '0.5'],
        ['color' => '#FFFFFF', 'opacity' => '1'],
      ],
      NULL,
      NULL,
    ];
    yield 'list mixing hex strings and keyed records' => [
      ['#502001', ['color' => '#FFFFFF', 'opacity' => '0.25']],
      [
        ['color' => '#502001', 'opacity' => NULL],
        ['color' => '#FFFFFF', 'opacity' => '0.25'],
      ],
      NULL,
      NULL,
    ];
    yield 'list skips empty deltas' => [
      ['#502001', '', NULL, [], ['color' => '', 'opacity' => ''], '#FFFFFF'],
      [
        ['color' => '#502001', 'opacity' => NULL],
        ['color' => '#FFFFFF', 'opacity' => NULL],
      ],
      NULL,
      NULL,
    ];
    yield 'list of only empty deltas leaves the field unset' => [
      ['', NULL],
      [],
      NULL,
      NULL,
    ];

    yield 'opacity without a color rejected' => [
      ['opacity' => '0.5'],
      NULL,
      \RuntimeException::class,
      'The color field record sets the opacity "0.5" but no color.',
    ];
    yield 'opacity with an empty color rejected' => [
      ['color' => '', 'opacity' => '0.5'],
      NULL,
      \RuntimeException::class,
      'The color field record sets the opacity "0.5" but no color.',
    ];
    yield 'unknown sub-field key rejected' => [
      ['color' => '#502001', 'alpha' => '0.5'],
      NULL,
      \RuntimeException::class,
      'Invalid color sub-field key "alpha". Use "color" and "opacity".',
    ];
    yield 'array color rejected' => [
      ['color' => ['#502001'], 'opacity' => NULL],
      NULL,
      \RuntimeException::class,
      'The color sub-field "color" must be a string, a number or NULL. Got array.',
    ];
    yield 'array opacity rejected' => [
      ['color' => '#502001', 'opacity' => ['0.5']],
      NULL,
      \RuntimeException::class,
      'The color sub-field "opacity" must be a string, a number or NULL. Got array.',
    ];
    yield 'positional record in a list rejected' => [
      [['#502001', '0.5']],
      NULL,
      \RuntimeException::class,
      'Invalid color sub-field key "0". Use "color" and "opacity".',
    ];
    yield 'non-string non-array value rejected' => [
      42,
      NULL,
      \RuntimeException::class,
      'Color field value must be a string or an array. Got int.',
    ];
    yield 'non-string non-array delta rejected' => [
      ['#502001', TRUE],
      NULL,
      \RuntimeException::class,
      'Color field value must be a string or an array. Got bool.',
    ];
  }

  /**
   * Tests how the field's opacity setting governs a given opacity.
   *
   * @param array<string, mixed> $settings
   *   The field settings.
   * @param mixed $input
   *   The loose input fed to 'expand()'.
   * @param array<int, array<string, mixed>>|null $expected
   *   The expected storage-shape output, or NULL when an exception is
   *   expected.
   * @param string|null $expected_message
   *   The expected exception message, or NULL for the happy path.
   */
  #[DataProvider('dataProviderExpandOpacitySetting')]
  public function testExpandOpacitySetting(array $settings, mixed $input, ?array $expected, ?string $expected_message): void {
    $handler = $this->createHandlerWithSettings($settings);

    if ($expected_message !== NULL) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $this->assertSame($expected, $handler->expand($input));
  }

  /**
   * Data provider for 'testExpandOpacitySetting()'.
   *
   * @return \Iterator<string, array{array<string, mixed>, mixed, array<int, array<string, mixed>>|null, string|null}>
   *   Rows of: field settings, input, expected storage shape, exception
   *   message.
   */
  public static function dataProviderExpandOpacitySetting(): \Iterator {
    yield 'opacity recorded by default' => [
      [],
      ['color' => '#502001', 'opacity' => '0.5'],
      [['color' => '#502001', 'opacity' => '0.5']],
      NULL,
    ];
    yield 'color only on a field without opacity' => [
      ['opacity' => FALSE],
      '#502001',
      [['color' => '#502001', 'opacity' => NULL]],
      NULL,
    ];
    yield 'empty opacity on a field without opacity' => [
      ['opacity' => FALSE],
      ['color' => '#502001', 'opacity' => ''],
      [['color' => '#502001', 'opacity' => NULL]],
      NULL,
    ];
    yield 'opacity on a field without opacity rejected' => [
      ['opacity' => FALSE],
      ['color' => '#502001', 'opacity' => '0.5'],
      NULL,
      'Cannot set the opacity "0.5" because opacity is not recorded on this field.',
    ];
  }

  /**
   * Creates the handler with the given field settings.
   *
   * @param array<string, mixed> $settings
   *   The field settings.
   */
  protected function createHandlerWithSettings(array $settings): ColorFieldTypeHandler {
    $field_config = $this->createMock(FieldDefinitionInterface::class);
    $field_config->method('getSettings')->willReturn($settings);

    $handler = (new \ReflectionClass(ColorFieldTypeHandler::class))->newInstanceWithoutConstructor();

    $property = new \ReflectionProperty(ColorFieldTypeHandler::class, 'fieldConfig');
    $property->setValue($handler, $field_config);

    return $handler;
  }

}
