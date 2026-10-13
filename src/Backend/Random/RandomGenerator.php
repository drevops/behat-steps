<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Random;

use Random\Randomizer;

/**
 * Generates random strings through a PHP randomizer.
 *
 * Uniqueness is tracked per instance and per method, so 2 generators can
 * return the same value, and a unique name can equal an earlier unique
 * machine name.
 */
final class RandomGenerator implements RandomGeneratorInterface {

  /**
   * The number of attempts before a unique or valid value is given up on.
   *
   * A short length leaves few distinct values, so the search is bounded.
   */
  public const int MAXIMUM_TRIES = 100;

  /**
   * The lower-case letters.
   */
  protected const string LOWER = 'abcdefghijklmnopqrstuvwxyz';

  /**
   * The upper-case letters.
   */
  protected const string UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

  /**
   * The digits.
   */
  protected const string DIGITS = '0123456789';

  /**
   * The vowels a word is built from.
   */
  protected const array VOWELS = ['a', 'e', 'i', 'o', 'u'];

  /**
   * The consonants and consonant pairs a word is built from.
   */
  protected const array CONSONANTS = [
    'b', 'c', 'd', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w',
    'tr', 'cr', 'br', 'fr', 'th', 'dr', 'ch', 'ph', 'wr', 'st', 'sp', 'sw', 'pr', 'sl', 'cl', 'sh',
  ];

  /**
   * The source of every draw.
   */
  protected readonly Randomizer $randomizer;

  /**
   * Strings 'string()' returned with uniqueness requested.
   *
   * @var array<string, true>
   */
  protected array $strings = [];

  /**
   * Names 'name()' returned with uniqueness requested.
   *
   * @var array<string, true>
   */
  protected array $names = [];

  /**
   * Machine names 'machineName()' returned with uniqueness requested.
   *
   * @var array<string, true>
   */
  protected array $machineNames = [];

  /**
   * Constructs the generator.
   *
   * @param \Random\Randomizer|null $randomizer
   *   The source of the draws, or NULL for a cryptographically secure one. A
   *   randomizer over a seeded engine makes every draw reproducible.
   */
  public function __construct(?Randomizer $randomizer = NULL) {
    $this->randomizer = $randomizer ?? new Randomizer();
  }

  /**
   * {@inheritdoc}
   */
  public function string(int $length = 8, bool $unique = FALSE, ?callable $validator = NULL): string {
    $printable = array_map(chr(...), range(32, 126));
    $generate = fn(): string => $this->generateFromAlphabet($printable, $length);

    return $this->generateUnique($generate, $this->strings, $unique, $validator, 'string');
  }

  /**
   * {@inheritdoc}
   */
  public function name(int $length = 8, bool $unique = FALSE): string {
    $alphanumeric = str_split(self::UPPER . self::LOWER . self::DIGITS);
    $generate = fn(): string => $this->pickOne(str_split(self::LOWER)) . $this->generateFromAlphabet($alphanumeric, $length - 1);

    return $this->generateUnique($generate, $this->names, $unique, NULL, 'name');
  }

  /**
   * {@inheritdoc}
   */
  public function machineName(int $length = 8, bool $unique = FALSE): string {
    $alphanumeric = str_split(self::LOWER . self::DIGITS);
    $generate = fn(): string => $this->pickOne(str_split(self::LOWER)) . $this->generateFromAlphabet($alphanumeric, $length - 1);

    return $this->generateUnique($generate, $this->machineNames, $unique, NULL, 'machine name');
  }

  /**
   * {@inheritdoc}
   */
  public function word(int $length): string {
    $word = '';

    while (strlen($word) < $length) {
      $word .= $this->pickOne(self::CONSONANTS) . $this->pickOne(self::VOWELS);
    }

    return substr($word, 0, $length);
  }

  /**
   * Builds a string of characters drawn from 1 alphabet.
   *
   * @param array<int, string> $alphabet
   *   The characters to draw from.
   * @param int $length
   *   The number of characters. 0 or less yields an empty string.
   *
   * @return string
   *   The built string.
   */
  protected function generateFromAlphabet(array $alphabet, int $length): string {
    $string = '';

    for ($i = 0; $i < $length; $i++) {
      $string .= $this->pickOne($alphabet);
    }

    return $string;
  }

  /**
   * Draws 1 entry of a list.
   *
   * @param array<int, string> $values
   *   The list to draw from.
   *
   * @return string
   *   The drawn entry.
   */
  protected function pickOne(array $values): string {
    return $values[$this->randomizer->getInt(0, count($values) - 1)];
  }

  /**
   * Runs a generator until its value is unique and valid, or gives up.
   *
   * @param callable(): string $generate
   *   Produces 1 candidate.
   * @param array<string, true> $seen
   *   The values already returned with uniqueness requested.
   * @param bool $unique
   *   Whether the value must be absent from '$seen'.
   * @param (callable(string): bool)|null $validator
   *   Accepts or rejects a candidate, or NULL to accept any.
   * @param string $what
   *   What is generated, for the failure message.
   *
   * @return string
   *   The accepted value.
   *
   * @throws \RuntimeException
   *   When no acceptable value is found within 'MAXIMUM_TRIES' attempts.
   */
  protected function generateUnique(callable $generate, array &$seen, bool $unique, ?callable $validator, string $what): string {
    for ($attempt = 0; $attempt < self::MAXIMUM_TRIES; $attempt++) {
      $candidate = $generate();

      if ($unique && isset($seen[$candidate])) {
        continue;
      }

      if ($validator !== NULL && !$validator($candidate)) {
        continue;
      }

      if ($unique) {
        $seen[$candidate] = TRUE;
      }

      return $candidate;
    }

    throw new \RuntimeException(sprintf('Unable to generate a random %s in %d attempts.', $what, self::MAXIMUM_TRIES));
  }

}
