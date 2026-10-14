<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\CreatedHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the CreatedHandler field handler.
 */
#[CoversClass(CreatedHandler::class)]
#[Group('fields')]
class CreatedHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    $reflection = new \ReflectionClass(CreatedHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $property->setValue($handler, 'value');

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield from TimestampHandlerTest::dataProviderExpand();
  }

}
