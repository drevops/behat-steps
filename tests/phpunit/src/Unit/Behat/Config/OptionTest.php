<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Config;

use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Tests the option value object.
 */
#[CoversClass(Option::class)]
class OptionTest extends UnitTestCase {

  public function testDeclarationIsReadable(): void {
    $option = new Option('fail_on_errors', default: TRUE, description: 'Fail the scenario.', tags: ['error' => FALSE]);

    $this->assertSame('fail_on_errors', $option->name);
    $this->assertTrue($option->default);
    $this->assertSame('Fail the scenario.', $option->description);
    $this->assertSame(['error' => FALSE], $option->tags);
  }

  public function testTagsDefaultToNone(): void {
    $this->assertSame([], (new Option('label', default: 'a default', description: 'A string option.'))->tags);
  }

  /**
   * Tests that a declaration missing what it needs is rejected.
   *
   * @param string $name
   *   The option name.
   * @param string $description
   *   The option description.
   * @param array<array-key, mixed> $tags
   *   Map of tag name to the value it sets.
   * @param string $expected_message
   *   The message the construction is expected to throw with.
   */
  #[DataProvider('dataProviderMalformedDeclarationIsRejected')]
  public function testMalformedDeclarationIsRejected(string $name, string $description, array $tags, string $expected_message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_message);

    new Option($name, default: TRUE, description: $description, tags: $tags);
  }

  public static function dataProviderMalformedDeclarationIsRejected(): \Iterator {
    yield 'no name' => ['', 'A description.', [], 'An option declares a name.'];
    yield 'a blank name' => ['  ', 'A description.', [], 'An option declares a name.'];
    yield 'no description' => ['label', '', [], 'The "label" option declares a description.'];
    yield 'a blank description' => ['label', "\n", [], 'The "label" option declares a description.'];

    yield 'tags listed rather than mapped' => [
      'enabled',
      'A description.',
      ['sample-off'],
      'The "enabled" option lists its tags as a map of tag name to the value it sets.',
    ];

    yield 'a blank tag name' => [
      'enabled',
      'A description.',
      [' ' => FALSE],
      'The "enabled" option lists its tags as a map of tag name to the value it sets.',
    ];
  }

  /**
   * Tests that a value is read as the type the declaration defaults to.
   *
   * @param mixed $default
   *   The declared default.
   * @param mixed $value
   *   The configured value.
   * @param mixed $expected
   *   The value the cast is expected to produce.
   */
  #[DataProvider('dataProviderValuesAreCast')]
  public function testValuesAreCast(mixed $default, mixed $value, mixed $expected): void {
    $option = new Option('sample', default: $default, description: 'An option.');

    $this->assertSame($expected, $option->cast($value, 'group.sample'));
  }

  public static function dataProviderValuesAreCast(): \Iterator {
    yield 'a boolean reads as a boolean' => [TRUE, FALSE, FALSE];
    yield 'an integer reads as an integer' => [7, 12, 12];
    yield 'a numeric string reads as an integer' => [7, '12', 12];
    yield 'a negative numeric string reads as an integer' => [7, '-3', -3];
    yield 'a float reads as a float' => [0.5, 1.25, 1.25];
    yield 'a numeric string reads as a float' => [0.5, '1.25', 1.25];
    yield 'an integer reads as a float' => [0.5, 2, 2.0];
    yield 'a string reads as a string' => ['a default', 'given', 'given'];
    yield 'an integer reads as a string' => ['a default', 42, '42'];
    yield 'a float reads as a string' => ['a default', 1.5, '1.5'];
    yield 'an array reads as an array' => [['.sample'], ['.acme'], ['.acme']];
    yield 'an untyped declaration takes an array' => [NULL, ['a', 'b'], ['a', 'b']];
    yield 'an untyped declaration takes a string' => [NULL, 'anything', 'anything'];
  }

  /**
   * Tests that a value of the wrong type names both types.
   *
   * @param mixed $default
   *   The declared default.
   * @param mixed $value
   *   The configured value.
   * @param string $expected_message
   *   The message the cast is expected to throw with.
   */
  #[DataProvider('dataProviderValuesOfTheWrongTypeAreRejected')]
  public function testValuesOfTheWrongTypeAreRejected(mixed $default, mixed $value, string $expected_message): void {
    $option = new Option('sample', default: $default, description: 'An option.');

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    $option->cast($value, 'group.sample');
  }

  public static function dataProviderValuesOfTheWrongTypeAreRejected(): \Iterator {
    yield 'a string where a boolean is declared' => [TRUE, 'yes', 'The "group.sample" option expects a boolean, but a string was given.'];
    yield 'a non-numeric string where an integer is declared' => [7, 'many', 'The "group.sample" option expects an integer, but a string was given.'];
    yield 'null where a float is declared' => [0.5, NULL, 'The "group.sample" option expects a float, but null was given.'];
    yield 'a map where a string is declared' => ['a default', ['.one'], 'The "group.sample" option expects a string, but a map was given.'];
    yield 'a string where a map is declared' => [['.one'], '.one', 'The "group.sample" option expects a map, but a string was given.'];
    yield 'an object where a boolean is declared' => [TRUE, new \stdClass(), 'The "group.sample" option expects a boolean, but a stdClass was given.'];
  }

}
