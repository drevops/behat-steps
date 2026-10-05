<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test: a contrib module's custom field types through the real backend.
 *
 * The 'backend_field_test' fixture module ships 2 field types the backend has
 * no handler for, standing in for any contrib module that introduces its own
 * field type. Both cases run against the real 'Core' with every built-in
 * handler registered, so the classifier gate is exercised end to end:
 *
 *  - 'backend_test_scalar' (plain-scalar columns) is handled by
 *    'DefaultHandler' and round-trips through real storage intact.
 *  - 'backend_test_reference' (an entity-reference target column) is refused
 *    at handler resolution with the "register a dedicated handler" exception
 *    instead of persisting an invalid id.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class CustomModuleFieldKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'backend_field_test',
  ];

  /**
   * Tests a custom plain-scalar field with no handler uses the fallback.
   */
  public function testScalarFieldWithoutHandlerRoundTrips(): void {
    $this->attachField('field_scalar', 'backend_test_scalar');

    $this->assertFieldRoundTripViaBackend('field_scalar', [
      ['value' => 'a plain value', 'weight' => 5],
    ]);
  }

  public function testReferenceFieldWithoutHandlerIsRejected(): void {
    $this->attachField('field_ref', 'backend_test_reference');

    $stub = new EntityStub(self::ENTITY_TYPE, self::BUNDLE, [
      'name' => 'test entity',
      'field_ref' => [['target_id' => 1]],
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/No dedicated handler is registered.*entity-reference/s');

    $this->core->createEntity($stub);
  }

}
