<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Random;

/**
 * Generates random strings for test data.
 *
 * A backend hands one out through 'BackendInterface::getRandom()'. A step
 * draws a name, a machine name or a password from the same generator the
 * backend uses for its own values.
 */
interface RandomGeneratorInterface {

  /**
   * Generates a string of printable ASCII characters.
   *
   * The string draws from the characters with codes 32 to 126: letters,
   * digits, punctuation and the space.
   *
   * @param int $length
   *   The number of characters.
   * @param bool $unique
   *   Whether the string must differ from every string this generator
   *   returned from this method with uniqueness requested.
   * @param (callable(string): bool)|null $validator
   *   Takes a candidate and returns whether it is acceptable, or NULL to
   *   accept any.
   *
   * @return string
   *   The generated string.
   *
   * @throws \RuntimeException
   *   When no acceptable string is found within the generator's attempts.
   *
   * @phpstan-impure
   */
  public function string(int $length = 8, bool $unique = FALSE, ?callable $validator = NULL): string;

  /**
   * Generates a string of letters and digits that starts with a letter.
   *
   * Letters are upper or lower case. The value suits an input that rejects
   * spaces and punctuation.
   *
   * @param int $length
   *   The number of characters. The leading letter is always emitted, so 0
   *   yields 1 character.
   * @param bool $unique
   *   Whether the name must differ from every name this generator returned
   *   from this method with uniqueness requested.
   *
   * @return string
   *   The generated name.
   *
   * @throws \RuntimeException
   *   When no unique name is found within the generator's attempts.
   *
   * @phpstan-impure
   */
  public function name(int $length = 8, bool $unique = FALSE): string;

  /**
   * Generates a string of lower-case letters and digits starting with a letter.
   *
   * The value suits a machine name. Underscores, dashes and periods are left
   * out, since not every machine name accepts them.
   *
   * @param int $length
   *   The number of characters. The leading letter is always emitted, so 0
   *   yields 1 character.
   * @param bool $unique
   *   Whether the machine name must differ from every machine name this
   *   generator returned from this method with uniqueness requested.
   *
   * @return string
   *   The generated machine name.
   *
   * @throws \RuntimeException
   *   When no unique machine name is found within the generator's attempts.
   *
   * @phpstan-impure
   */
  public function machineName(int $length = 8, bool $unique = FALSE): string;

  /**
   * Generates a string that reads like a word.
   *
   * The string holds lower-case letters only, alternating consonants and
   * vowels.
   *
   * @param int $length
   *   The number of characters.
   *
   * @return string
   *   The generated word.
   *
   * @phpstan-impure
   */
  public function word(int $length): string;

}
