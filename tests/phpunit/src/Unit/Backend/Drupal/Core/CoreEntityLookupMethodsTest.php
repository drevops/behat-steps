<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the parts of the entity lookup on 'Core' that need no Drupal kernel.
 *
 * A condition is split into its operator and operand before any query is
 * built, and the entity type and bundle are checked before any storage call.
 */
#[CoversClass(Core::class)]
#[Group('core')]
class CoreEntityLookupMethodsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Tests that a condition value resolves to its operator and operand.
   *
   * @param mixed $value
   *   The condition value.
   * @param array{string, mixed} $expected
   *   The operator and operand the value resolves to.
   */
  #[DataProvider('dataProviderResolveLookupCondition')]
  public function testResolveLookupCondition(mixed $value, array $expected): void {
    $this->assertSame($expected, (new \ReflectionMethod(Core::class, 'resolveLookupCondition'))->invoke($this->createCore(), 'title', $value));
  }

  public static function dataProviderResolveLookupCondition(): \Iterator {
    yield 'string' => ['Shared', ['=', 'Shared']];
    yield 'integer' => [1, ['=', 1]];
    yield 'boolean' => [TRUE, ['=', TRUE]];
    yield 'equals pair' => [['=', 'Shared'], ['=', 'Shared']];
    yield 'IN pair' => [['IN', ['1', '2']], ['IN', ['1', '2']]];
    yield 'CONTAINS pair' => [['CONTAINS', 'Shar'], ['CONTAINS', 'Shar']];
    yield 'LIKE pair' => [['LIKE', 'Sha%'], ['LIKE', 'Sha%']];
    yield 'operator in lower case' => [['contains', 'Shar'], ['CONTAINS', 'Shar']];
  }

  /**
   * Tests that a malformed condition value is rejected with a clear message.
   *
   * @param mixed $value
   *   The condition value.
   * @param string $expected_message
   *   The message the rejection carries.
   */
  #[DataProvider('dataProviderResolveLookupConditionRejectsMalformedValue')]
  public function testResolveLookupConditionRejectsMalformedValue(mixed $value, string $expected_message): void {
    $core = $this->createCore();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    (new \ReflectionMethod(Core::class, 'resolveLookupCondition'))->invoke($core, 'title', $value);
  }

  public static function dataProviderResolveLookupConditionRejectsMalformedValue(): \Iterator {
    $pair_message = 'The condition on "title" is a value or an [operator, value] pair.';

    yield 'empty array' => [[], $pair_message];
    yield 'bare list of values' => [['1', '2', '3'], $pair_message];
    yield 'keyed array' => [['operator' => 'IN', 'value' => ['1']], $pair_message];
    yield 'pair whose operator is not a string' => [[1, '2'], $pair_message];
    yield 'unknown operator' => [['>', '1'], 'The condition on "title" names the operator ">", which is not one of =, IN, CONTAINS, LIKE.'];
    yield 'IN with a scalar' => [['IN', '1'], 'The "IN" condition on "title" takes a non-empty list of values.'];
    yield 'IN with an empty list' => [['IN', []], 'The "IN" condition on "title" takes a non-empty list of values.'];
    yield 'equals with a list' => [['=', ['1']], 'The "=" condition on "title" takes a single value.'];
    yield 'CONTAINS with a list' => [['CONTAINS', ['Shar']], 'The "CONTAINS" condition on "title" takes a single value.'];
  }

  public function testFindEntityIdsRejectsUnknownEntityType(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')->willThrowException(new PluginNotFoundException('widget'));
    $this->setUpEntityTypeManager($entity_type_manager);

    $core = $this->createCore();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unknown entity type "widget".');

    $core->findEntityIds('widget');
  }

  public function testFindEntityIdsRejectsBundleForEntityTypeWithoutBundles(): void {
    $definition = $this->createMock(EntityTypeInterface::class);
    $definition->method('getKey')->with('bundle')->willReturn(FALSE);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')->with('widget')->willReturn($definition);
    $this->setUpEntityTypeManager($entity_type_manager);

    $core = $this->createCore();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Entity type "widget" declares no bundle key, so it cannot be looked up by bundle.');

    $core->findEntityIds('widget', [], 'gadget');
  }

  /**
   * Builds a 'Core' instance pointed at a valid path.
   *
   * Nothing under test reaches the filesystem, so any existing directory
   * serves as the root.
   */
  protected function createCore(): Core {
    return new Core(__DIR__);
  }

  /**
   * Installs a container serving the given entity type manager.
   */
  protected function setUpEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): void {
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);
  }

}
