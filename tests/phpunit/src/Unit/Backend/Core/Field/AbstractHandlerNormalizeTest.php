<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests AbstractHandler::normalize() across every accepted input shape.
 *
 * Also tests isListOfRecords() across each array shape it classifies.
 */
#[CoversClass(AbstractHandler::class)]
#[Group('fields')]
class AbstractHandlerNormalizeTest extends UnitTestCase {

  /**
   * Tests every accepted and rejected input shape for normalize().
   *
   * @param mixed $input
   *   The loose input to feed to normalize().
   * @param string $main_property
   *   The field's main property name injected into the handler.
   * @param array<int, array<string, mixed>>|null $expected
   *   The expected canonical list of records, or NULL when an exception is
   *   expected.
   * @param class-string<\Throwable>|null $exception
   *   The expected exception class, or NULL for the happy path.
   * @param string|null $expected_message
   *   Substring the exception message must contain, or NULL.
   */
  #[DataProvider('dataProviderNormalize')]
  public function testNormalize(mixed $input, string $main_property, ?array $expected, ?string $exception, ?string $expected_message): void {
    $handler = $this->createHandler($main_property);

    if ($exception !== NULL) {
      $this->expectException($exception);

      if ($expected_message !== NULL) {
        $this->expectExceptionMessage($expected_message);
      }
    }

    $result = $this->invokeNormalize($handler, $input);

    if ($exception === NULL) {
      $this->assertSame($expected, $result);
    }
  }

  public static function dataProviderNormalize(): \Iterator {
    yield 'bare string scalar with target_id main' => [
      'foo.jpg',
      'target_id',
      [['target_id' => 'foo.jpg']],
      NULL,
      NULL,
    ];
    yield 'bare integer scalar with value main' => [
      42,
      'value',
      [['value' => 42]],
      NULL,
      NULL,
    ];
    yield 'bare NULL scalar' => [
      NULL,
      'value',
      [['value' => NULL]],
      NULL,
      NULL,
    ];
    yield 'empty array' => [
      [],
      'value',
      [],
      NULL,
      NULL,
    ];
    yield 'list of one scalar' => [
      ['foo.jpg'],
      'target_id',
      [['target_id' => 'foo.jpg']],
      NULL,
      NULL,
    ];
    yield 'list of multiple scalars' => [
      ['a.jpg', 'b.jpg'],
      'target_id',
      [['target_id' => 'a.jpg'], ['target_id' => 'b.jpg']],
      NULL,
      NULL,
    ];
    yield 'single record (assoc array)' => [
      ['target_id' => 'foo.jpg', 'alt' => 'A', 'title' => 'B'],
      'target_id',
      [['target_id' => 'foo.jpg', 'alt' => 'A', 'title' => 'B']],
      NULL,
      NULL,
    ];
    yield 'list of one record' => [
      [['target_id' => 'foo.jpg', 'alt' => 'A']],
      'target_id',
      [['target_id' => 'foo.jpg', 'alt' => 'A']],
      NULL,
      NULL,
    ];
    yield 'list of multiple records' => [
      [
        ['target_id' => 'a.jpg', 'alt' => 'A'],
        ['target_id' => 'b.jpg', 'alt' => 'B'],
      ],
      'target_id',
      [
        ['target_id' => 'a.jpg', 'alt' => 'A'],
        ['target_id' => 'b.jpg', 'alt' => 'B'],
      ],
      NULL,
      NULL,
    ];
    yield 'mixed list of scalars and records' => [
      ['plain', ['value' => 'rich', 'format' => 'basic_html']],
      'value',
      [['value' => 'plain'], ['value' => 'rich', 'format' => 'basic_html']],
      NULL,
      NULL,
    ];
    yield 'uri main property (link)' => [
      'https://example.com',
      'uri',
      [['uri' => 'https://example.com']],
      NULL,
      NULL,
    ];
    yield 'list with NULL scalar' => [
      [NULL, 'something'],
      'value',
      [['value' => NULL], ['value' => 'something']],
      NULL,
      NULL,
    ];
    yield 'rejects numeric 0 followed by named extras' => [
      ['/path/foo.jpg', 'alt' => 'A'],
      'target_id',
      NULL,
      \RuntimeException::class,
      'Got keys: 0, alt.',
    ];
    yield 'rejects numeric 0 with multiple named extras' => [
      ['/path/foo.jpg', 'alt' => 'A', 'title' => 'B'],
      'target_id',
      NULL,
      \RuntimeException::class,
      'Got keys: 0, alt, title.',
    ];
    yield 'rejects named keys followed by numeric' => [
      ['alt' => 'A', 0 => '/path/foo.jpg'],
      'target_id',
      NULL,
      \RuntimeException::class,
      'Got keys: alt, 0.',
    ];
    yield 'rejects gappy numeric mixed with named' => [
      [2 => 'a', 'alt' => 'A'],
      'value',
      NULL,
      \RuntimeException::class,
      'Got keys: 2, alt.',
    ];
    yield 'rejects single record missing main property' => [
      ['alt' => 'A', 'title' => 'B'],
      'target_id',
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "target_id". Got keys: alt, title.',
    ];
    yield 'rejects record in list missing main property' => [
      [['target_id' => 'a.jpg'], ['alt' => 'orphan']],
      'target_id',
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "target_id". Got keys: alt.',
    ];
    yield 'rejects empty record' => [
      [[]],
      'value',
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "value". Got keys: (none).',
    ];
  }

  /**
   * Tests how isListOfRecords() classifies each array shape.
   *
   * @param array<int|string, mixed> $values
   *   The array to classify.
   * @param bool $expected
   *   Whether the array holds a delta per element.
   */
  #[DataProvider('dataProviderIsListOfRecords')]
  public function testIsListOfRecords(array $values, bool $expected): void {
    $handler = $this->createHandler('value');

    $method = new \ReflectionMethod(AbstractHandler::class, 'isListOfRecords');

    $this->assertSame($expected, $method->invoke($handler, $values));
  }

  /**
   * Data provider for testIsListOfRecords().
   *
   * @return \Iterator<string, array{array<int|string, mixed>, bool}>
   *   Each case pairs an array shape with the expected classification.
   */
  public static function dataProviderIsListOfRecords(): \Iterator {
    yield 'list of arrays is a list of records' => [[['value' => 1], ['value' => 2]], TRUE];
    yield 'positional pair is a single record' => [['start', 'end'], FALSE];
    yield 'keyed array is a single record' => [['value' => 'start'], FALSE];
    yield 'mixed list leading with a scalar is a single record' => [['start', ['value' => 1]], FALSE];
    yield 'empty array is a single record' => [[], FALSE];
  }

  /**
   * Invokes the protected normalize() method on the given handler.
   *
   * @return array<int, array<string, mixed>>
   *   The canonical list of records returned by normalize().
   */
  protected function invokeNormalize(AbstractHandler $handler, mixed $input): array {
    $method = new \ReflectionMethod(AbstractHandler::class, 'normalize');
    return $method->invoke($handler, $input);
  }

  /**
   * Creates an AbstractHandler subclass with the main property injected.
   *
   * The constructor requires a full Drupal entity bootstrap, so it is
   * bypassed. 'normalize()' needs only 'mainProperty', so only that value is
   * set directly via reflection.
   */
  protected function createHandler(string $main_property): AbstractHandler {
    $handler = (new \ReflectionClass(PassThroughHandler::class))->newInstanceWithoutConstructor();

    $property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $property->setValue($handler, $main_property);

    return $handler;
  }

}

/**
 * Concrete AbstractHandler subclass used only by the tests in this file.
 */
final class PassThroughHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    return $records;
  }

}
