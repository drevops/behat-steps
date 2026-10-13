<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\RedirectSourceHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the RedirectSourceHandler field handler.
 */
#[CoversClass(RedirectSourceHandler::class)]
#[Group('fields')]
class RedirectSourceHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    $reflection = new \ReflectionClass(RedirectSourceHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $property->setValue($handler, 'path');

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'bare path loses its leading slash and gains an empty query' => [
      '/old/about',
      [['path' => 'old/about', 'query' => []]],
      NULL,
      NULL,
    ];
    yield 'path without a leading slash stays as it is' => [
      'old/about',
      [['path' => 'old/about', 'query' => []]],
      NULL,
      NULL,
    ];
    yield 'record with a query map relays the map' => [
      ['path' => '/search', 'query' => ['q' => 'behat', 'page' => '2']],
      [['path' => 'search', 'query' => ['q' => 'behat', 'page' => '2']]],
      NULL,
      NULL,
    ];
    yield 'record with a query string parses it into a map' => [
      ['path' => '/search', 'query' => '?q=behat&page=2'],
      [['path' => 'search', 'query' => ['q' => 'behat', 'page' => '2']]],
      NULL,
      NULL,
    ];
    yield 'list of paths is multiple deltas' => [
      ['/first', '/second'],
      [['path' => 'first', 'query' => []], ['path' => 'second', 'query' => []]],
      NULL,
      NULL,
    ];

    yield 'record missing main property rejected' => [
      ['query' => ['q' => 'behat']],
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "path"',
    ];
  }

}
