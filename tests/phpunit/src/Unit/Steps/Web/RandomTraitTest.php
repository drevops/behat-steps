<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use DrevOps\BehatSteps\Steps\Web\RandomTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the random token transform and the values each token type generates.
 */
#[CoversTrait(RandomTrait::class)]
class RandomTraitTest extends UnitTestCase {

  /**
   * A test implementation of RandomTrait.
   */
  protected RandomTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new RandomTraitTestImplementation();
  }

  public function testSkippedScenarioLeavesTokensUntouched(): void {
    $this->testObject->randomBeforeScenario($this->createBeforeScenarioScope(['behat-steps-skip:RandomTrait']));

    $this->assertSame('[?title]', $this->testObject->randomTransformValue('[?title]'));

    $table = new TableNode([['title'], ['[?title]']]);
    $this->assertSame([['title'], ['[?title]']], $this->testObject->randomTransformTable($table)->getRows());
  }

  public function testUnskippedScenarioResolvesTokens(): void {
    $this->testObject->randomBeforeScenario($this->createBeforeScenarioScope());

    $resolved = $this->testObject->randomTransformValue('[?title]');

    $this->assertIsString($resolved);
    $this->assertNotSame('[?title]', $resolved);
    $this->assertSame(10, strlen($resolved));

    // 1 value per token for the whole scenario, so the table cell holds the
    // same string the scalar argument resolved to.
    $table = new TableNode([['title'], ['[?title]']]);
    $this->assertSame([['title'], [$resolved]], $this->testObject->randomTransformTable($table)->getRows());
  }

  public function testTheConfiguredSwitchTurnsTheTransformOff(): void {
    $context = new RandomTraitTestImplementation(['random' => ['enabled' => FALSE]]);
    $context->randomBeforeScenario($this->createBeforeScenarioScope());

    $this->assertSame('[?title]', $context->randomTransformValue('[?title]'));
  }

  /**
   * Tests the value each token type generates.
   *
   * @param string $literal
   *   The token literal.
   * @param string $pattern
   *   A regular expression the generated value matches.
   */
  #[DataProvider('dataProviderResolveLiteralGeneratesTypedValue')]
  public function testResolveLiteralGeneratesTypedValue(string $literal, string $pattern): void {
    $this->assertMatchesRegularExpression($pattern, (string) $this->testObject->randomResolveLiteral($literal));
  }

  public static function dataProviderResolveLiteralGeneratesTypedValue(): \Iterator {
    yield 'string with the default length' => ['[?t]', '/^[a-z][a-z0-9]{9}$/'];
    yield 'string with a length' => ['[?t:string,4]', '/^[a-z][a-z0-9]{3}$/'];
    yield 'empty type with a length' => ['[?t:,4]', '/^[a-z][a-z0-9]{3}$/'];
    yield 'name with the default length' => ['[?t:name]', '/^[a-z][A-Za-z0-9]{9}$/'];
    yield 'name with a length' => ['[?t:name,5]', '/^[a-z][A-Za-z0-9]{4}$/'];
    yield 'machine name with the default length' => ['[?t:machine_name]', '/^[a-z][a-z0-9]{9}$/'];
    yield 'machine name with a length' => ['[?t:machine_name,6]', '/^[a-z][a-z0-9]{5}$/'];
    yield 'integer over the full range' => ['[?n:int]', '/^\d+$/'];
    yield 'integer in a range of 1 value' => ['[?n:int,7,7]', '/^7$/'];
    yield 'integer in a negative range' => ['[?n:int,-3,-3]', '/^-3$/'];
    yield 'email' => ['[?m:email]', '/^[a-z0-9]{8}@[a-z0-9]{6}\.test$/'];
    yield 'uuid' => ['[?u:uuid]', '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/'];
  }

  public function testStringLowercasesTheGeneratedNameAndNameKeepsItsCase(): void {
    $generator = $this->createStub(RandomGeneratorInterface::class);
    $generator->method('name')->willReturnCallback(static fn(int $length): string => substr('aBcDeFgHiJkL', 0, $length));
    $generator->method('machineName')->willReturnCallback(static fn(int $length): string => str_repeat('m', $length));
    $this->testObject->testSetGenerator($generator);

    $this->assertSame('abcdef', $this->testObject->randomResolveLiteral('[?t:string,6]'));
    $this->assertSame('aBcDeF', $this->testObject->randomResolveLiteral('[?u:name,6]'));
    $this->assertSame('mmmm', $this->testObject->randomResolveLiteral('[?m:machine_name,4]'));
    // A length of 0 still generates 1 character.
    $this->assertSame('a', $this->testObject->randomResolveLiteral('[?z:string,0]'));
  }

  public function testIntegerBoundsGivenInReverseAreSwapped(): void {
    $value = $this->testObject->randomResolveLiteral('[?n:int,9,1]');

    $this->assertIsInt($value);
    $this->assertGreaterThanOrEqual(1, $value);
    $this->assertLessThanOrEqual(9, $value);
  }

  public function testEquivalentTokensShareAValue(): void {
    $default = $this->testObject->randomResolveLiteral('[?t]');

    $this->assertSame($default, $this->testObject->randomResolveLiteral('[?t:string]'));
    $this->assertSame($default, $this->testObject->randomResolveLiteral('[?t:string,10]'));
    $this->assertNotSame($default, $this->testObject->randomResolveLiteral('[?t:string,11]'));
    $this->assertSame($this->testObject->randomResolveLiteral('[?n:int]'), $this->testObject->randomResolveLiteral(sprintf('[?n:int,0,%d]', PHP_INT_MAX)));
  }

  /**
   * Tests that a malformed token fails instead of generating a value.
   *
   * @param string $literal
   *   The token literal.
   * @param string $expected_message
   *   The message the resolution is expected to throw with.
   */
  #[DataProvider('dataProviderResolveLiteralRejectsMalformedToken')]
  public function testResolveLiteralRejectsMalformedToken(string $literal, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->randomResolveLiteral($literal);
  }

  public static function dataProviderResolveLiteralRejectsMalformedToken(): \Iterator {
    yield 'string with 2 arguments' => ['[?t:string,1,2]', 'Type "string" accepts at most one argument (length); got 2.'];
    yield 'name with a length that is not a number' => ['[?t:name,abc]', 'Type "name" length must be a non-negative integer; got "abc".'];
    yield 'machine name with a negative length' => ['[?t:machine_name,-1]', 'Type "machine_name" length must be a non-negative integer; got "-1".'];
    yield 'integer with 1 bound' => ['[?n:int,5]', 'Type "int" accepts no args (full range) or two args (min, max); got 1.'];
    yield 'integer with 3 bounds' => ['[?n:int,1,2,3]', 'Type "int" accepts no args (full range) or two args (min, max); got 3.'];
    yield 'integer with a bound that is not an integer' => ['[?n:int,1,b]', 'Type "int" args must be integers; got "b".'];
    yield 'email with an argument' => ['[?m:email,5]', 'Type "email" does not accept arguments; got 1.'];
    yield 'uuid with an argument' => ['[?u:uuid,v4]', 'Type "uuid" does not accept arguments; got 1.'];
    yield 'unknown type' => ['[?c:color]', 'Unknown randomGenerator token type "color".'];
  }

}

/**
 * Test implementation of RandomTrait.
 */
class RandomTraitTestImplementation extends WebRawContext {

  use RandomTrait;

  public function testSetGenerator(RandomGeneratorInterface $generator): void {
    $this->randomGenerator = $generator;
  }

}
