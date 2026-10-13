<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Random;

use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Random\Engine;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Tests the package random generator.
 *
 * The length and charset tests draw real random values. The uniqueness and
 * validator tests script every draw through 'ScriptedRandomEngine', so the
 * retry loop runs without chance.
 */
#[CoversClass(RandomGenerator::class)]
#[Group('backends')]
class RandomGeneratorTest extends UnitTestCase {

  public function testImplementsInterface(): void {
    $this->assertInstanceOf(RandomGeneratorInterface::class, new RandomGenerator());
  }

  /**
   * Tests the length and the charset of each generator.
   *
   * @param string $method
   *   The generator method to call.
   * @param int $length
   *   The length to ask for.
   * @param int $expected_length
   *   The length the value has.
   * @param string $pattern
   *   The pattern the value matches.
   */
  #[DataProvider('dataProviderGeneratorsHoldLengthAndCharset')]
  public function testGeneratorsHoldLengthAndCharset(string $method, int $length, int $expected_length, string $pattern): void {
    $value = (new RandomGenerator())->{$method}($length);

    $this->assertSame($expected_length, strlen($value));
    $this->assertMatchesRegularExpression($pattern, $value);
  }

  public static function dataProviderGeneratorsHoldLengthAndCharset(): \Iterator {
    yield 'string of 0' => ['string', 0, 0, '/^$/'];
    yield 'string of 1' => ['string', 1, 1, '/^[\x20-\x7E]$/'];
    yield 'string of 64' => ['string', 64, 64, '/^[\x20-\x7E]{64}$/'];
    yield 'name of 0 keeps its leading letter' => ['name', 0, 1, '/^[a-z]$/'];
    yield 'name of 1' => ['name', 1, 1, '/^[a-z]$/'];
    yield 'name of 32' => ['name', 32, 32, '/^[a-z][A-Za-z0-9]{31}$/'];
    yield 'machine name of 0 keeps its leading letter' => ['machineName', 0, 1, '/^[a-z]$/'];
    yield 'machine name of 1' => ['machineName', 1, 1, '/^[a-z]$/'];
    yield 'machine name of 32' => ['machineName', 32, 32, '/^[a-z][a-z0-9]{31}$/'];
    yield 'word of 0' => ['word', 0, 0, '/^$/'];
    yield 'word of 1' => ['word', 1, 1, '/^[a-z]$/'];
    yield 'word of 9' => ['word', 9, 9, '/^[a-z]{9}$/'];
  }

  /**
   * Tests that a generator called without a length yields 8 characters.
   *
   * @param string $method
   *   The generator method to call.
   */
  #[DataProvider('dataProviderLengthDefaultsToEight')]
  public function testLengthDefaultsToEight(string $method): void {
    $this->assertSame(8, strlen((new RandomGenerator())->{$method}()));
  }

  public static function dataProviderLengthDefaultsToEight(): \Iterator {
    yield 'string' => ['string'];
    yield 'name' => ['name'];
    yield 'machineName' => ['machineName'];
  }

  public function testWordAlternatesConsonantsAndVowels(): void {
    $word = (new RandomGenerator())->word(40);

    $this->assertMatchesRegularExpression('/^(?:[b-df-hj-np-tv-z]{1,2}[aeiou])*[b-df-hj-np-tv-z]{0,2}$/', $word);
  }

  public function testSeededRandomizerReproducesTheDraws(): void {
    $first = new RandomGenerator(new Randomizer(new Mt19937(42)));
    $second = new RandomGenerator(new Randomizer(new Mt19937(42)));

    $this->assertSame($first->name(16), $second->name(16));
    $this->assertSame($first->string(16), $second->string(16));
    $this->assertSame($first->word(16), $second->word(16));
  }

  public function testUniqueNamesDoNotRepeatWithinOneGenerator(): void {
    $generator = $this->createGenerator([0, 0, 1]);

    $this->assertSame('a', $generator->name(1, TRUE));
    $this->assertSame('b', $generator->name(1, TRUE));
  }

  public function testNonUniqueNamesMayRepeat(): void {
    $generator = $this->createGenerator([]);

    $this->assertSame('a', $generator->name(1));
    $this->assertSame('a', $generator->name(1));
  }

  public function testNonUniqueDrawsAreNotRecorded(): void {
    $generator = $this->createGenerator([]);

    $this->assertSame('a', $generator->name(1));
    $this->assertSame('a', $generator->name(1, TRUE));
  }

  public function testUniquenessIsTrackedPerMethod(): void {
    $generator = $this->createGenerator([]);

    $this->assertSame('a', $generator->name(1, TRUE));
    $this->assertSame('a', $generator->machineName(1, TRUE));
    $this->assertSame(' ', $generator->string(1, TRUE));
  }

  public function testUniquenessIsTrackedPerInstance(): void {
    $this->assertSame('a', $this->createGenerator([])->name(1, TRUE));
    $this->assertSame('a', $this->createGenerator([])->name(1, TRUE));
  }

  /**
   * Tests that a generator gives up once every draw repeats a unique value.
   *
   * @param string $method
   *   The generator method to call.
   * @param string $what
   *   How the failure message names the value.
   */
  #[DataProvider('dataProviderUniqueValueExhaustionThrows')]
  public function testUniqueValueExhaustionThrows(string $method, string $what): void {
    $generator = $this->createGenerator([]);
    $generator->{$method}(1, TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('Unable to generate a random %s in %d attempts.', $what, RandomGenerator::MAXIMUM_TRIES));

    $generator->{$method}(1, TRUE);
  }

  public static function dataProviderUniqueValueExhaustionThrows(): \Iterator {
    yield 'string' => ['string', 'string'];
    yield 'name' => ['name', 'name'];
    yield 'machineName' => ['machineName', 'machine name'];
  }

  public function testValidatorRejectsCandidates(): void {
    $generator = $this->createGenerator([0, 1, 2]);

    $this->assertSame('"', $generator->string(1, FALSE, static fn(string $candidate): bool => $candidate !== ' ' && $candidate !== '!'));
  }

  public function testUniquenessAndValidatorCombine(): void {
    $generator = $this->createGenerator([0, 0, 1, 2]);
    $generator->string(1, TRUE);

    $this->assertSame('"', $generator->string(1, TRUE, static fn(string $candidate): bool => $candidate !== '!'));
  }

  public function testValidatorNeverSatisfiedThrows(): void {
    $generator = $this->createGenerator([]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to generate a random string in 100 attempts.');

    $generator->string(1, FALSE, static fn(): bool => FALSE);
  }

  /**
   * Builds a generator whose draws land on scripted indexes.
   *
   * @param array<int, int> $indexes
   *   The index each draw lands on, in order. An exhausted script lands on 0.
   */
  protected function createGenerator(array $indexes): RandomGenerator {
    return new RandomGenerator(new Randomizer(new ScriptedRandomEngine($indexes)));
  }

}

/**
 * An engine whose output follows a scripted list of values.
 *
 * Each draw hands the randomizer the next value as 4 little-endian bytes. A
 * value below the size of the list a draw selects from is the index it
 * lands on, so a test decides every pick. An exhausted script yields 0.
 */
class ScriptedRandomEngine implements Engine {

  /**
   * Constructs the engine.
   *
   * @param array<int, int> $values
   *   The values the draws yield, in order.
   */
  public function __construct(protected array $values) {}

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    return pack('V', array_shift($this->values) ?? 0);
  }

}
