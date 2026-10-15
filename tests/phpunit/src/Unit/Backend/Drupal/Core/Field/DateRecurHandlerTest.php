<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DateRecurHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DateRecurHandler field handler.
 *
 * The site timezone is 'America/New_York', so a value read in the site
 * timezone differs from the same value read in UTC.
 */
#[CoversClass(DateRecurHandler::class)]
#[Group('fields')]
class DateRecurHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!$this->loadDatetimeModuleClasses()) {
      $this->markTestSkipped('drupal/core datetime module classes are not available.');
    }

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('timezone.default')->willReturn('America/New_York');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('system.date')->willReturn($config);

    // DrupalDateTime reads the current language when it parses a date.
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn($language);

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('language_manager', $language_manager);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    return $this->createDateRecurHandler('datetime');
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'bare scalar is read in the site timezone, which the record takes' => [
      '2025-01-01T09:00:00',
      [['value' => '2025-01-01T14:00:00', 'timezone' => 'America/New_York']],
      NULL,
      NULL,
    ];
    yield 'list of scalars is multiple value deltas' => [
      ['2025-01-01T09:00:00', '2025-06-15T14:30:00'],
      [
        ['value' => '2025-01-01T14:00:00', 'timezone' => 'America/New_York'],
        ['value' => '2025-06-15T18:30:00', 'timezone' => 'America/New_York'],
      ],
      NULL,
      NULL,
    ];
    yield 'full record is read in its own timezone' => [
      [
        'value' => '2025-01-01T09:00:00',
        'end_value' => '2025-01-01T17:00:00',
        'rrule' => 'FREQ=DAILY;COUNT=5',
        'timezone' => 'Australia/Sydney',
        'infinite' => 0,
      ],
      [
        [
          'value' => '2024-12-31T22:00:00',
          'end_value' => '2025-01-01T06:00:00',
          'rrule' => 'FREQ=DAILY;COUNT=5',
          'timezone' => 'Australia/Sydney',
          'infinite' => 0,
        ],
      ],
      NULL,
      NULL,
    ];
    yield 'record in UTC keeps its times' => [
      [
        'value' => '2025-01-01T09:00:00',
        'end_value' => '2025-01-01T17:00:00',
        'timezone' => 'UTC',
      ],
      [
        [
          'value' => '2025-01-01T09:00:00',
          'end_value' => '2025-01-01T17:00:00',
          'timezone' => 'UTC',
        ],
      ],
      NULL,
      NULL,
    ];
    yield 'list of records is read in each record timezone' => [
      [
        ['value' => '2025-01-01T09:00:00', 'timezone' => 'UTC'],
        ['value' => '2025-06-15T14:30:00', 'timezone' => 'Europe/London'],
      ],
      [
        ['value' => '2025-01-01T09:00:00', 'timezone' => 'UTC'],
        ['value' => '2025-06-15T13:30:00', 'timezone' => 'Europe/London'],
      ],
      NULL,
      NULL,
    ];
    yield 'empty timezone takes the site timezone' => [
      ['value' => '2025-01-01T09:00:00', 'timezone' => ''],
      [['value' => '2025-01-01T14:00:00', 'timezone' => 'America/New_York']],
      NULL,
      NULL,
    ];
    yield 'value with its own offset keeps it' => [
      ['value' => '2025-01-01T09:00:00+02:00', 'timezone' => 'Australia/Sydney'],
      [['value' => '2025-01-01T07:00:00', 'timezone' => 'Australia/Sydney']],
      NULL,
      NULL,
    ];
    yield 'value in UTC by its suffix keeps it' => [
      ['value' => '2025-01-01T09:00:00Z', 'timezone' => 'Australia/Sydney'],
      [['value' => '2025-01-01T09:00:00', 'timezone' => 'Australia/Sydney']],
      NULL,
      NULL,
    ];
    yield 'relative prefix is resolved in the record timezone' => [
      ['value' => 'relative:2025-01-02 03:04:05', 'timezone' => 'Australia/Sydney'],
      [['value' => '2025-01-01T16:04:05', 'timezone' => 'Australia/Sydney']],
      NULL,
      NULL,
    ];
    yield 'empty end value becomes NULL' => [
      ['value' => '2025-01-01T09:00:00', 'end_value' => '', 'timezone' => 'UTC'],
      [['value' => '2025-01-01T09:00:00', 'end_value' => NULL, 'timezone' => 'UTC']],
      NULL,
      NULL,
    ];
    yield 'end value alone takes the site timezone' => [
      ['value' => '', 'end_value' => '2025-01-01T17:00:00'],
      [['value' => NULL, 'end_value' => '2025-01-01T22:00:00', 'timezone' => 'America/New_York']],
      NULL,
      NULL,
    ];
    yield 'bare empty value is relayed without a timezone' => [
      '',
      [['value' => '']],
      NULL,
      NULL,
    ];
    yield 'record without dates is relayed unchanged' => [
      ['value' => NULL, 'end_value' => '', 'rrule' => 'FREQ=DAILY;COUNT=5'],
      [['value' => NULL, 'end_value' => '', 'rrule' => 'FREQ=DAILY;COUNT=5']],
      NULL,
      NULL,
    ];

    yield 'invalid timezone rejected' => [
      ['value' => '2025-01-01T09:00:00', 'timezone' => 'Mars/Olympus'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has an invalid timezone "Mars/Olympus".',
    ];
    yield 'mixed positional and named keys rejected' => [
      ['2025-01-01T09:00:00', 'timezone' => 'UTC'],
      NULL,
      \RuntimeException::class,
      'Field value cannot mix positional and named keys',
    ];
    yield 'record missing main property rejected' => [
      ['timezone' => 'UTC', 'rrule' => 'FREQ=DAILY;COUNT=5'],
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "value"',
    ];
  }

  /**
   * Tests that a date-only field stores the date as written.
   *
   * @param mixed $input
   *   The loose input fed to 'expand()'.
   * @param array<int, array<string, mixed>> $expected
   *   The expected storage-shape output.
   */
  #[DataProvider('dataProviderExpandDateOnly')]
  public function testExpandDateOnly(mixed $input, array $expected): void {
    $this->assertSame($expected, $this->createDateRecurHandler('date')->expand($input));
  }

  /**
   * Data provider for 'testExpandDateOnly()'.
   *
   * @return \Iterator<string, array{mixed, array<int, array<string, mixed>>}>
   *   Rows of: input, expected storage shape.
   */
  public static function dataProviderExpandDateOnly(): \Iterator {
    yield 'date in its own timezone' => [
      ['value' => '2025-01-01', 'timezone' => 'Australia/Sydney'],
      [['value' => '2025-01-01', 'timezone' => 'Australia/Sydney']],
    ];
    yield 'date without a timezone takes the site timezone' => [
      '2025-01-01',
      [['value' => '2025-01-01', 'timezone' => 'America/New_York']],
    ];
  }

  /**
   * Creates the handler for a field of the given datetime type.
   *
   * @param string $datetime_type
   *   The field storage's 'datetime_type' setting: 'datetime' or 'date'.
   */
  protected function createDateRecurHandler(string $datetime_type): FieldHandlerInterface {
    $field_info = $this->createMock(FieldStorageDefinitionInterface::class);
    $field_info->method('getSetting')->with('datetime_type')->willReturn($datetime_type);

    $reflection = new \ReflectionClass(DateRecurHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $info_property = new \ReflectionProperty(AbstractHandler::class, 'fieldInfo');
    $info_property->setValue($handler, $field_info);

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, 'value');

    return $handler;
  }

}
