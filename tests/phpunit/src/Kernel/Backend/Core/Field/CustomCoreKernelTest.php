<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use ConsumerProject\Backend\ConsumerCore;
use ConsumerProject\Backend\Field\StringLongHandler as ConsumerStringLongHandler;
use ConsumerProject\Backend\Field\TextLongHandler as ConsumerTextLongHandler;
use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\Core\Entity\ContentEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for consumer-supplied Core and its bundled field handlers.
 *
 * Exercises the 2 extension seams documented in the README against a full
 * Drupal kernel. The test replaces 'Core' with 'ConsumerCore', a fixture
 * outside the 'DrevOps\BehatSteps\Backend' namespace, and proves that its
 * 'Field/' directory scan contributes handlers that run during
 * 'createEntity':
 *
 *  - 'ConsumerProject\Backend\Field\TextLongHandler' takes 'text_long' over
 *    from 'DefaultHandler', which serves the type when nothing is registered.
 *  - 'ConsumerProject\Backend\Field\StringLongHandler' does the same for
 *    'string_long'.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class CustomCoreKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'text',
    'filter',
  ];

  /**
   * {@inheritdoc}
   *
   * Replaces the library's 'Core' with 'ConsumerCore' so the handlers under
   * test come from the fixture's directory scan, not the library's.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['filter']);
    $this->core = new ConsumerCore($this->root);
  }

  /**
   * Tests that the consumer override replaces the library's 'text_long'.
   *
   * The input differs from the handler's marker, so the assertion passes only
   * when the consumer handler ran. A pass-through handler would leave the raw
   * input in storage and fail the comparison.
   */
  public function testConsumerCoreOverridesLibraryHandler(): void {
    $this->attachField('field_body', 'text_long');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'test entity',
      'field_body' => [
        ['value' => 'raw input', 'format' => 'plain_text'],
      ],
    ]);

    $this->core->createEntity($stub);

    $field_body = $stub->getValue('field_body');
    $this->assertSame(ConsumerTextLongHandler::MARKER, $field_body[0]['value'], 'Consumer handler did not transform the field value during expand().');

    $reloaded = \Drupal::entityTypeManager()->getStorage(static::ENTITY_TYPE)->loadUnchanged($stub->getValue('id'));
    $this->assertInstanceOf(ContentEntityInterface::class, $reloaded);
    $this->assertSame(ConsumerTextLongHandler::MARKER, $reloaded->get('field_body')->getValue()[0]['value'], 'Storage did not receive the consumer handler output.');
  }

  /**
   * Tests that the consumer Core registers handlers for new field types.
   *
   * 'string_long' is a Drupal-core field type; the library does not ship a
   * dedicated handler for it. Without 'ConsumerCore', the lookup falls through
   * to 'DefaultHandler' and stores the raw input verbatim.
   *
   * The fixture adds a handler that rewrites 'value', and this test proves the
   * rewritten value reaches storage.
   */
  public function testConsumerCoreAddsHandlerForNewFieldType(): void {
    $this->attachField('field_summary', 'string_long');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'test entity',
      'field_summary' => [['value' => 'raw input']],
    ]);

    $this->core->createEntity($stub);

    $field_summary = $stub->getValue('field_summary');
    $this->assertSame(ConsumerStringLongHandler::MARKER, $field_summary[0]['value'], 'Consumer handler did not transform the field value during expand().');

    $reloaded = \Drupal::entityTypeManager()->getStorage(static::ENTITY_TYPE)->loadUnchanged($stub->getValue('id'));
    $this->assertInstanceOf(ContentEntityInterface::class, $reloaded);
    $this->assertSame(ConsumerStringLongHandler::MARKER, $reloaded->get('field_summary')->getValue()[0]['value'], 'Storage did not receive the consumer handler output.');
  }

}
