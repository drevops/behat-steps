<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Core\Field\DefaultHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for AbstractHandler's field-not-found guard.
 *
 * 'Core::getFieldHandler()' validates field existence before instantiating a
 * handler, so the guard is reachable only when a caller constructs a handler
 * directly (e.g. via custom Core subclasses). This test exercises that
 * direct-construction path against a real entity_field.manager service.
 */
#[CoversClass(AbstractHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class AbstractHandlerFieldNotFoundKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = self::BASE_MODULES;

  public function testConstructorThrowsOnUnknownField(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/does not exist on entity type "entity_test"/');

    new DefaultHandler(new EntityStub(static::ENTITY_TYPE), static::ENTITY_TYPE, 'field_does_not_exist');
  }

}
