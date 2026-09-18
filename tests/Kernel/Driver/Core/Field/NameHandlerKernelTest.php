<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Driver\Core\Field;

use DrevOps\BehatSteps\Driver\Core\Field\NameHandler;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use Drupal\entity_test\Entity\EntityTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel round-trip test for NameHandler via the Core driver.
 *
 * Name is a multi-property field provided by the 'drupal/name' contrib
 * module. The handler accepts three input shapes (shorthand string,
 * numeric array, associative array) and normalises them into the same
 * per-component keyed structure. It also honours the field's
 * 'components' setting: positional input skips disabled components and
 * named input throws if it targets one.
 *
 * @group fields
 */
#[CoversClass(NameHandler::class)]
#[Group('fields')]
class NameHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'name',
  ];

  /**
   * Tests round-trip for a name field with associative input.
   */
  public function testNameAssociativeRoundTrip(): void {
    $this->attachField('field_author', 'name');

    $this->assertFieldRoundTripViaDriver('field_author', [
      [
        'given' => 'Jane',
        'family' => 'Doe',
      ],
    ]);
  }

  /**
   * Tests round-trip for a name field with "Family, Given" shorthand.
   */
  public function testNameShorthandStringRoundTrip(): void {
    $this->attachField('field_author', 'name');

    $this->assertFieldRoundTripViaDriver('field_author', ['Doe, Jane']);

    // Pin the component split explicitly: the mutated-stub round-trip would
    // still pass if the handler swapped the two components.
    $stub = new EntityStub('entity_test', 'entity_test', [
      'name' => 'pinned',
      'field_author' => ['Doe, Jane'],
    ]);
    $this->core->entityCreate($stub);
    $values = EntityTest::load($stub->getValue('id'))->get('field_author')->getValue();
    $this->assertSame('Jane', $values[0]['given']);
    $this->assertSame('Doe', $values[0]['family']);
  }

  /**
   * Tests positional input maps into enabled components only.
   */
  public function testNamePositionalSkipsDisabledComponents(): void {
    $this->attachField('field_author', 'name', [], [
      'components' => [
        NameHandler::COMPONENT_TITLE => TRUE,
        NameHandler::COMPONENT_GIVEN => TRUE,
        NameHandler::COMPONENT_MIDDLE => FALSE,
        NameHandler::COMPONENT_FAMILY => TRUE,
        NameHandler::COMPONENT_GENERATIONAL => FALSE,
        NameHandler::COMPONENT_CREDENTIALS => FALSE,
      ],
    ]);

    $this->assertFieldRoundTripViaDriver('field_author', [
      ['Dr', 'Jane', 'Doe'],
    ]);

    // Pin the positional mapping: the values must land on the three enabled
    // components in canonical order, leaving the disabled ones empty.
    $stub = new EntityStub('entity_test', 'entity_test', [
      'name' => 'pinned',
      'field_author' => [['Dr', 'Jane', 'Doe']],
    ]);
    $this->core->entityCreate($stub);
    $values = EntityTest::load($stub->getValue('id'))->get('field_author')->getValue();
    $this->assertSame('Dr', $values[0]['title']);
    $this->assertSame('Jane', $values[0]['given']);
    $this->assertSame('Doe', $values[0]['family']);
    $this->assertEmpty($values[0]['middle']);
    $this->assertEmpty($values[0]['generational']);
    $this->assertEmpty($values[0]['credentials']);
  }

}
