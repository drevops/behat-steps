<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\Core\Entity\ContentEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test asserting a consumer-registered handler wins end-to-end.
 *
 * This test proves that a class registered via 'Core::registerFieldHandler()'
 * is the one instantiated when 'entityCreate()' expands a field. The stored
 * value is observed to differ from what the fallback handler would produce.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class FieldHandlerRegistryKernelTest extends FieldHandlerKernelTestBase {

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
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['filter']);

    // On Drupal 12 the 'text_with_summary' field type is provided by its own
    // module rather than by 'text'.
    if (\Drupal::service('extension.list.module')->exists('text_with_summary')) {
      $this->enableModules(['text_with_summary']);
    }
  }

  /**
   * Tests that a consumer-registered handler replaces the fallback.
   *
   * The input value differs from the handler's marker, so the assertion
   * passes only when the consumer handler ran. 'DefaultHandler', which serves
   * 'text_with_summary' when nothing is registered, would leave the raw input
   * in storage and fail the comparison against 'MARKER'.
   */
  public function testConsumerRegisteredHandlerWinsOverFallback(): void {
    $this->core->registerFieldHandler('text_with_summary', MarkerTextWithSummaryHandler::class);
    $this->attachField('field_body', 'text_with_summary');

    $stub = new EntityStub(self::ENTITY_TYPE, self::BUNDLE, [
      'name' => 'test entity',
      'field_body' => [
        ['value' => 'raw input', 'format' => 'plain_text'],
      ],
    ]);

    $this->core->entityCreate($stub);

    $field_body = $stub->getValue('field_body');
    $this->assertSame(MarkerTextWithSummaryHandler::MARKER, $field_body[0]['value'], 'Consumer handler did not transform the field value during expand().');

    $reloaded = \Drupal::entityTypeManager()->getStorage(self::ENTITY_TYPE)->loadUnchanged($stub->getValue('id'));
    $this->assertInstanceOf(ContentEntityInterface::class, $reloaded);
    $this->assertSame(MarkerTextWithSummaryHandler::MARKER, $reloaded->get('field_body')->getValue()[0]['value'], 'Storage did not receive the consumer handler output.');
  }

}

/**
 * Test-only handler that emits a deterministic marker value.
 *
 * Extends 'AbstractHandler' directly so its lineage excludes 'DefaultHandler'.
 * A resolution to this class can then only come from the registry, not from a
 * class-name convention.
 */
class MarkerTextWithSummaryHandler extends AbstractHandler {

  public const MARKER = 'consumer handler took precedence';

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $emitted = [];

    foreach ($records as $record) {
      $record['value'] = self::MARKER;
      $emitted[] = $record;
    }

    return $emitted;
  }

}
