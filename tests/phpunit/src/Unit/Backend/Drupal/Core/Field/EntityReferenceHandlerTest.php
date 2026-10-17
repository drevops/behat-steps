<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\EntityReferenceHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the EntityReferenceHandler field handler.
 */
#[CoversClass(EntityReferenceHandler::class)]
#[Group('fields')]
class EntityReferenceHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * Label-to-id index the entity query stub returns matches from.
   *
   * @var array<string, int>
   */
  protected const KNOWN_LABELS = [
    'alice' => 7,
    'bob' => 8,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->createEntityTypeManager(static::KNOWN_LABELS));
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
   *
   * @param string $target_type
   *   The entity type the field references.
   */
  protected function createHandler(string $target_type = 'user'): FieldHandlerInterface {
    $field_info = $this->createMock(FieldStorageDefinitionInterface::class);
    $field_info->method('getSetting')
      ->with('target_type')
      ->willReturn($target_type);

    $field_config = $this->createMock(FieldDefinitionInterface::class);
    $field_config->method('getSettings')->willReturn(['handler_settings' => []]);

    $reflection = new \ReflectionClass(EntityReferenceHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $info_property = new \ReflectionProperty(EntityReferenceHandler::class, 'fieldInfo');
    $info_property->setValue($handler, $field_info);

    $config_property = new \ReflectionProperty(EntityReferenceHandler::class, 'fieldConfig');
    $config_property->setValue($handler, $field_config);

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, 'target_id');

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'bare label resolves to id' => [
      'alice',
      [['target_id' => 7]],
      NULL,
      NULL,
    ];
    yield 'list of one label' => [
      ['alice'],
      [['target_id' => 7]],
      NULL,
      NULL,
    ];
    yield 'list of multiple labels' => [
      ['alice', 'bob'],
      [['target_id' => 7], ['target_id' => 8]],
      NULL,
      NULL,
    ];
    yield 'record with target_id label' => [
      [['target_id' => 'alice']],
      [['target_id' => 7]],
      NULL,
      NULL,
    ];
    yield 'record preserves extras' => [
      [['target_id' => 'alice', 'display' => 1]],
      [['target_id' => 7, 'display' => 1]],
      NULL,
      NULL,
    ];
    yield 'integer id bypasses validation query' => [
      [42],
      [['target_id' => 42]],
      NULL,
      NULL,
    ];

    yield 'unknown label throws' => [
      ['nobody'],
      NULL,
      \RuntimeException::class,
      'No entity "nobody" of type "user" exists.',
    ];
    yield 'mixed positional and named keys rejected' => [
      ['alice', 'extra' => 'oops'],
      NULL,
      \RuntimeException::class,
      'Field value cannot mix positional and named keys',
    ];
    yield 'record missing main property rejected' => [
      ['display' => 1],
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "target_id"',
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
    $this->expectExceptionMessage('Entity reference record is missing the main property "target_id".');

    static::reflect($handler)->getMethod('doExpand')->invoke($handler, [['display' => 1]]);
  }

  public function testReferenceToAnEntityTypeWithoutAnIdKeyIsRejected(): void {
    $conditions = [];
    \Drupal::getContainer()->set('entity_type.manager', $this->createRecordingEntityTypeManager(['label' => 'name'], [], $conditions));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot resolve a reference to "user" because it declares no id key.');

    $this->createHandler()->expand('alice');
  }

  public function testDigitLookupMatchesAnIdOrALabel(): void {
    $conditions = [];
    \Drupal::getContainer()->set('entity_type.manager', $this->createRecordingEntityTypeManager(['id' => 'uid', 'label' => 'name'], [8], $conditions));

    $this->assertSame([['target_id' => 8]], $this->createHandler()->expand('8'));
    $this->assertSame([['uid', 8], ['name', '8']], $conditions);
  }

  public function testLookupOnAnEntityTypeWithoutALabelMatchesTheId(): void {
    $conditions = [];
    \Drupal::getContainer()->set('entity_type.manager', $this->createRecordingEntityTypeManager(['id' => 'id'], [5], $conditions));

    $this->assertSame([['target_id' => 5]], $this->createHandler('entity_test_no_label')->expand('5'));
    $this->assertSame([['id', '5']], $conditions);
  }

  /**
   * Builds an entity_type.manager stub whose entity query records conditions.
   *
   * @param array<string, string> $keys
   *   The entity keys the definition declares; any other key reads FALSE.
   * @param array<int, int> $matches
   *   The ids the entity query returns.
   * @param array<int, array{0: string, 1: mixed}> $conditions
   *   Receives each field condition the query is given, as field and value.
   */
  protected function createRecordingEntityTypeManager(array $keys, array $matches, array &$conditions): object {
    $entity_type = $this->createStub(EntityTypeInterface::class);
    $entity_type->method('getKey')->willReturnCallback(static fn(string $key): string|false => $keys[$key] ?? FALSE);

    $query = $this->createStub(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('orConditionGroup')->willReturnSelf();
    $query->method('execute')->willReturn($matches);
    $query->method('condition')
      ->willReturnCallback(static function (mixed $field, mixed $value = NULL) use ($query, &$conditions): QueryInterface {
        // The OR group is the query itself, so adding the group passes an
        // object as the field.
        if (is_string($field)) {
          $conditions[] = [$field, $value];
        }

        return $query;
      });

    $entity_type_manager = $this->createStub(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')->willReturn($entity_type);
    $entity_type_manager->method('getStorage')->willReturn($this->createStorageWithQuery($query));

    return $entity_type_manager;
  }

  /**
   * Builds an entity_type.manager + entity-query stub keyed by label.
   *
   * @param array<string, int> $known_labels
   *   Label-to-id index the entity query returns matches from.
   */
  protected function createEntityTypeManager(array $known_labels): object {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getKey')->willReturnMap([
      ['id', 'uid'],
      ['label', 'name'],
      ['bundle', FALSE],
    ]);

    $query = $this->createQueryStub($known_labels);
    $storage = $this->createStorageWithQuery($query);

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
      ->willReturnCallback(static function (mixed $field, mixed $value = NULL) use ($query, &$captured_label): MockObject {
        if (is_string($field) && in_array($field, ['name', 'title', 'label'], TRUE) && $value !== NULL) {
          $captured_label = (string) $value;
        }

        return $query;
      });

    $query->method('execute')
      ->willReturnCallback(static function () use (&$captured_label, $known_labels): array {
        return $captured_label !== NULL && isset($known_labels[$captured_label])
          ? [$known_labels[$captured_label]]
          : [];
      });

    return $query;
  }

  /**
   * Builds an entity storage stub whose 'getQuery()' returns the given query.
   */
  protected function createStorageWithQuery(QueryInterface $query): object {
    return new readonly class($query) {

      public function __construct(protected QueryInterface $query) {}

      /**
       * Returns the injected entity query.
       */
      public function getQuery(): QueryInterface {
        return $this->query;
      }

    };
  }

}
