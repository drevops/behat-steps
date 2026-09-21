<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Core\Field;

use DrevOps\BehatSteps\Driver\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Driver\Core\Field\EntityReferenceRevisionsHandler;
use DrevOps\BehatSteps\Driver\Core\Field\FieldHandlerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the EntityReferenceRevisionsHandler field handler.
 *
 * @group fields
 */
#[CoversClass(EntityReferenceRevisionsHandler::class)]
#[Group('fields')]
class EntityReferenceRevisionsHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * Label -> id index for the entity query stub.
   *
   * @var array<string, int>
   */
  protected const KNOWN_LABELS = [
    'Paragraph A' => 42,
  ];

  /**
   * Revision id every loaded target reports.
   */
  protected const REVISION_ID = 7;

  /**
   * Bundle every loaded target reports.
   */
  protected const TARGET_BUNDLE = 'text';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installContainer($this->createTarget(self::REVISION_ID, self::TARGET_BUNDLE));
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
    return $this->createHandlerWithSettings([]);
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'bare label resolves to id and revision id' => [
      'Paragraph A',
      [['target_id' => 42, 'target_revision_id' => self::REVISION_ID]],
      NULL,
      NULL,
    ];
    yield 'record preserves extras and resolves target' => [
      [['target_id' => 'Paragraph A', 'extra' => 'keep-me']],
      [['target_id' => 42, 'extra' => 'keep-me', 'target_revision_id' => self::REVISION_ID]],
      NULL,
      NULL,
    ];
    yield 'integer id bypasses validation query' => [
      [99],
      [['target_id' => 99, 'target_revision_id' => self::REVISION_ID]],
      NULL,
      NULL,
    ];

    yield 'unknown label throws' => [
      ['Paragraph X'],
      NULL,
      \RuntimeException::class,
      "No entity 'Paragraph X' of type 'paragraph' exists.",
    ];
  }

  /**
   * Tests that 'doExpand()' rejects a record without the main property.
   *
   * The base 'normalize()' rejects such a record before 'doExpand()' runs,
   * so the test feeds 'doExpand()' directly.
   */
  public function testDoExpandRejectsRecordMissingMainProperty(): void {
    $handler = $this->createHandler();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Entity reference revisions record is missing the main property "target_id".');

    (new \ReflectionMethod($handler, 'doExpand'))->invoke($handler, [['extra' => 'keep-me']]);
  }

  /**
   * Tests that a resolved id whose entity no longer loads is rejected.
   */
  public function testExpandRejectsDeletedTarget(): void {
    $this->installContainer(NULL);
    $handler = $this->createHandler();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Entity '99' of type 'paragraph' no longer exists.");

    $handler->expand([99]);
  }

  /**
   * Tests that a loaded target outside the field's bundles is rejected.
   */
  public function testExpandRejectsTargetOfUnacceptedBundle(): void {
    $handler = $this->createHandlerWithSettings(['handler_settings' => ['target_bundles' => ['image' => 'image']]]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf("Entity '99' of type 'paragraph' is of bundle '%s', which the field does not accept. Allowed: image.", self::TARGET_BUNDLE));

    $handler->expand([99]);
  }

  /**
   * Installs a container whose entity storage loads the given target.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface|null $target
   *   The entity 'load()' returns, or NULL when the target no longer exists.
   */
  protected function installContainer(?RevisionableInterface $target): void {
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->createEntityTypeManager(self::KNOWN_LABELS, $target));
    \Drupal::setContainer($container);
  }

  /**
   * Creates an EntityReferenceRevisionsHandler with the given field settings.
   *
   * @param array<string, mixed> $settings
   *   Field settings the 'fieldConfig' mock returns.
   */
  protected function createHandlerWithSettings(array $settings): EntityReferenceRevisionsHandler {
    $field_info = $this->createMock(FieldStorageDefinitionInterface::class);
    $field_info->method('getSetting')
      ->with('target_type')
      ->willReturn('paragraph');

    $field_config = $this->createMock(FieldDefinitionInterface::class);
    $field_config->method('getSettings')->willReturn($settings);

    $reflection = new \ReflectionClass(EntityReferenceRevisionsHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $info_property = new \ReflectionProperty(EntityReferenceRevisionsHandler::class, 'fieldInfo');
    $info_property->setValue($handler, $field_info);

    $config_property = new \ReflectionProperty(EntityReferenceRevisionsHandler::class, 'fieldConfig');
    $config_property->setValue($handler, $field_config);

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, 'target_id');

    return $handler;
  }

  /**
   * Builds a target entity mock with the given revision id and bundle.
   *
   * @param int $revision_id
   *   Revision id the target reports.
   * @param string $bundle
   *   Bundle the target reports.
   */
  protected function createTarget(int $revision_id, string $bundle): RevisionableInterface {
    $target = $this->createMock(RevisionableInterface::class);
    $target->method('getRevisionId')->willReturn($revision_id);
    $target->method('bundle')->willReturn($bundle);

    return $target;
  }

  /**
   * Builds the entity_type.manager + query + storage stubs.
   *
   * @param array<string, int> $known_labels
   *   Label-to-id index.
   * @param \Drupal\Core\Entity\RevisionableInterface|null $target
   *   The entity every 'load()' returns, or NULL when nothing loads.
   */
  protected function createEntityTypeManager(array $known_labels, ?RevisionableInterface $target): object {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getKey')->willReturnMap([
      ['id', 'id'],
      ['label', 'label'],
      ['bundle', 'type'],
    ]);

    $query = $this->createQueryStub($known_labels);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('load')->willReturn($target);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')->willReturn($entity_type);
    $entity_type_manager->method('getStorage')->willReturn($storage);

    return $entity_type_manager;
  }

  /**
   * Builds an entity query mock backed by the label-to-id index.
   *
   * @param array<string, int> $known_labels
   *   Label-to-id index.
   */
  protected function createQueryStub(array $known_labels): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('orConditionGroup')->willReturnSelf();

    $captured_label = NULL;
    $query->method('condition')
      ->willReturnCallback(function (mixed $field, mixed $value = NULL) use ($query, &$captured_label): MockObject {
        if (is_string($field) && in_array($field, ['name', 'title', 'label'], TRUE) && $value !== NULL) {
          $captured_label = (string) $value;
        }

        return $query;
      });

    $query->method('execute')
      ->willReturnCallback(function () use (&$captured_label, $known_labels): array {
        return $captured_label !== NULL && isset($known_labels[$captured_label])
          ? [$known_labels[$captured_label]]
          : [];
      });

    return $query;
  }

}
