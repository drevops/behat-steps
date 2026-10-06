<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Core\Field\DefaultHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests AbstractHandler guards that need no Drupal kernel.
 *
 * 'DefaultHandler' is the simplest concrete subclass and is used here to
 * exercise the base class error branches.
 */
#[CoversClass(AbstractHandler::class)]
#[Group('fields')]
class AbstractHandlerErrorPathsTest extends UnitTestCase {

  /**
   * Tests that the constructor rejects an empty entity type.
   *
   * The throw precedes every 'Drupal::service()' call, so no kernel bootstrap
   * is required.
   */
  public function testConstructorRejectsEmptyEntityType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/You must specify an entity type/');

    new DefaultHandler(new EntityStub(''), '', 'field_any');
  }

  public function testNormalizeRejectsMissingMainProperty(): void {
    $handler = (new \ReflectionClass(DefaultHandler::class))->newInstanceWithoutConstructor();

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, NULL);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Handler ".+DefaultHandler" has no main property/');

    $handler->expand('value');
  }

}
