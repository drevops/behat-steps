<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Generic;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Transformation\Transform;
use Drupal\Component\Utility\Random;

/**
 * Replace random-value tokens in step arguments and table cells.
 *
 * - Resolve `[?<name>:<type>[,<args>]]` tokens to generated values.
 * - Return one value per token for the whole scenario.
 *
 * Built-in types are `string`, `name`, `machine_name`, `int`, `email` and
 * `uuid`. The default is `string` with length `10`, so `[?title]`,
 * `[?title:string]` and `[?title:string,10]` share one value.
 *
 * Operates on Gherkin text alone: no Mink session and no driver, so the trait
 * works in any suite.
 */
trait RandomTrait {

  protected const RANDOM_BRACKET_REGEX = '#(\[\?[a-z0-9_]+(?::[^\]]+)?\])#i';

  /**
   * Token literal as it appears in the feature file -> canonical cache key.
   *
   * Parsing memo so the same literal does not get re-parsed on every
   * transform invocation.
   *
   * @var array<string, string>
   */
  protected array $randomLiterals = [];

  /**
   * Canonical cache key -> generated value.
   *
   * Canonical key is 'name:type:arg1,arg2,...' with defaults applied,
   * so '[?title]', '[?title:string]' and '[?title:string,10]' collapse
   * to the same key.
   *
   * @var array<string, string|int>
   */
  protected array $randomValues = [];

  /**
   * Random string generator (lazy).
   */
  protected ?Random $randomGenerator = NULL;

  /**
   * Pre-resolves every token literal found in the current scenario.
   *
   * Running this in a 'BeforeScenario' hook means the cache is warm by
   * the time the first step runs, which keeps repeated token literals stable
   * even when their first occurrence is inside a step argument that
   * Behat dispatches before the rest are visited.
   */
  #[BeforeScenario]
  public function randomBeforeScenario(BeforeScenarioScope $scope): void {
    $this->randomValues = [];
    $this->randomLiterals = [];

    $steps = [];

    if ($scope->getFeature()->hasBackground()) {
      $steps = $scope->getFeature()->getBackground()->getSteps();
    }

    $steps = array_merge($steps, $scope->getScenario()->getSteps());
    foreach ($steps as $step) {
      $haystack = $step->getText();
      $step_argument = $step->getArguments();

      if (!empty($step_argument) && $step_argument[0] instanceof TableNode) {
        $haystack .= "\n" . $step_argument[0]->getTableAsString();
      }

      preg_match_all(self::RANDOM_BRACKET_REGEX, $haystack, $matches);

      foreach ($matches[0] as $literal) {
        $this->randomResolveLiteral($literal);
      }
    }
  }

  /**
   * Clears the per-scenario cache.
   */
  #[AfterScenario]
  public function randomAfterScenario(AfterScenarioScope $scope): void {
    $this->randomValues = [];
    $this->randomLiterals = [];
  }

  /**
   * Substitutes '[?...]' tokens inside a step argument.
   *
   * @return string|array<int, string>|null
   *   The transformed message.
   */
  #[Transform('#(.*\[\?[a-z0-9_]+(?::[^\]]+)?\].*)#i')]
  public function randomTransformValue(string $message): string|array|null {
    return $this->randomSubstitute($message);
  }

  /**
   * Substitutes '[?...]' tokens inside table cells.
   */
  #[Transform('table:*')]
  public function randomTransformTable(TableNode $table): TableNode {
    return $this->randomSubstituteTable($table);
  }

  /**
   * Substitutes every token match in '$message' via 'randomResolveLiteral()'.
   */
  protected function randomSubstitute(string $message): string {
    preg_match_all(self::RANDOM_BRACKET_REGEX, $message, $matches);

    if ($matches[0] === []) {
      return $message;
    }

    $patterns = [];
    $replacements = [];
    foreach ($matches[0] as $literal) {
      $patterns[] = '#' . preg_quote($literal) . '#';
      $replacements[] = (string) $this->randomResolveLiteral($literal);
    }

    $result = preg_replace($patterns, $replacements, $message);

    return $result ?? $message;
  }

  /**
   * Applies 'randomSubstitute()' across every cell in '$table'.
   */
  protected function randomSubstituteTable(TableNode $table): TableNode {
    $rows = [];
    foreach ($table->getRows() as $row) {
      $rows[] = array_map($this->randomSubstitute(...), $row);
    }

    return new TableNode($rows);
  }

  /**
   * Resolves a token literal to its generated value.
   *
   * On first encounter the literal is parsed, normalized to a canonical
   * '(name, type, args)' tuple, the value is generated and stored under
   * the canonical key, and the literal is recorded in the parsing memo
   * so future lookups are O(1).
   */
  protected function randomResolveLiteral(string $literal): string|int {
    if (isset($this->randomLiterals[$literal])) {
      return $this->randomValues[$this->randomLiterals[$literal]];
    }

    [$name, $type, $args] = $this->randomParseToken($literal);
    $args = $this->randomNormalizeArgs($type, $args);
    $key = $name . ':' . $type . ':' . implode(',', $args);
    $this->randomLiterals[$literal] = $key;

    $this->randomValues[$key] ??= $this->randomGenerate($type, $args);

    return $this->randomValues[$key];
  }

  /**
   * Parses a token literal into '[name, type, args]'.
   *
   * @return array{0: string, 1: string, 2: list<string>}
   *   Tuple of name, type, and args.
   */
  protected function randomParseToken(string $literal): array {
    $body = substr($literal, 2, -1);
    $colon = strpos($body, ':');

    if ($colon === FALSE) {
      return [$body, 'string', []];
    }

    $name = substr($body, 0, $colon);
    $spec = substr($body, $colon + 1);
    $parts = array_map(trim(...), explode(',', $spec));
    $type = array_shift($parts);

    return [$name, $type === '' ? 'string' : $type, $parts];
  }

  /**
   * Validates and fills defaults so equivalent tokens share a cache key.
   *
   * Each branch returns the canonical args list for the given type, or
   * throws 'InvalidArgumentException' when the literal supplies malformed
   * input (non-integer length, wrong arg count, extra args on argless
   * types). Failing fast prevents typos like '[?title:string,abc]' from
   * silently producing a 1-character string.
   *
   * @param string $type
   *   The generator type extracted from the token.
   * @param list<string> $args
   *   Raw args parsed from the token literal.
   *
   * @return list<string>
   *   Args with type-specific defaults applied.
   */
  protected function randomNormalizeArgs(string $type, array $args): array {
    return match ($type) {
      'string', 'name', 'machine_name' => $this->randomNormalizeLengthArgs($type, $args),
      'int' => $this->randomNormalizeIntArgs($args),
      'email', 'uuid' => $this->randomNormalizeArglessArgs($type, $args),
      default => $args,
    };
  }

  /**
   * Validates length-style args (one optional non-negative integer).
   *
   * @param string $type
   *   The generator type, used for error messages.
   * @param list<string> $args
   *   Raw args parsed from the token literal.
   *
   * @return list<string>
   *   Args with the default length filled in if absent.
   */
  protected function randomNormalizeLengthArgs(string $type, array $args): array {
    if (count($args) > 1) {
      throw new \RuntimeException(sprintf('Type "%s" accepts at most one argument (length); got %d.', $type, count($args)));
    }

    if (!isset($args[0])) {
      return ['10'];
    }

    if (!ctype_digit($args[0])) {
      throw new \RuntimeException(sprintf('Type "%s" length must be a non-negative integer; got "%s".', $type, $args[0]));
    }

    return [$args[0]];
  }

  /**
   * Validates 'int' args (zero args for full range, or two integer bounds).
   *
   * @param list<string> $args
   *   Raw args parsed from the token literal.
   *
   * @return list<string>
   *   Args with default range filled in if absent.
   */
  protected function randomNormalizeIntArgs(array $args): array {
    if ($args === []) {
      return ['0', (string) PHP_INT_MAX];
    }

    if (count($args) !== 2) {
      throw new \RuntimeException(sprintf('Type "int" accepts no args (full range) or two args (min, max); got %d.', count($args)));
    }

    foreach ($args as $arg) {
      if (preg_match('/^-?\d+$/', $arg) !== 1) {
        throw new \RuntimeException(sprintf('Type "int" args must be integers; got "%s".', $arg));
      }
    }

    return [$args[0], $args[1]];
  }

  /**
   * Validates argless types ('email', 'uuid'): refuses any positional args.
   *
   * @param string $type
   *   The generator type, used for error messages.
   * @param list<string> $args
   *   Raw args parsed from the token literal.
   *
   * @return list<string>
   *   Always empty.
   */
  protected function randomNormalizeArglessArgs(string $type, array $args): array {
    if ($args !== []) {
      throw new \RuntimeException(sprintf('Type "%s" does not accept arguments; got %d.', $type, count($args)));
    }

    return [];
  }

  /**
   * Dispatches to the type-specific generator.
   *
   * Args are validated by 'randomNormalizeArgs()' before reaching this method,
   * so the casts are safe and not a fallback.
   *
   * @param string $type
   *   The generator type extracted from the token.
   * @param list<string> $args
   *   Already normalized args from 'randomNormalizeArgs()'.
   *
   * @return string|int
   *   The generated value.
   */
  protected function randomGenerate(string $type, array $args): string|int {
    return match ($type) {
      'string' => $this->randomGenerateString((int) $args[0]),
      'name' => $this->randomGenerateName((int) $args[0]),
      'machine_name' => $this->randomGenerateMachineName((int) $args[0]),
      'int' => $this->randomGenerateInt((int) $args[0], (int) $args[1]),
      'email' => $this->randomGenerateEmail(),
      'uuid' => $this->randomGenerateUuid(),
      default => throw new \RuntimeException(sprintf('Unknown randomGenerator token type "%s".', $type)),
    };
  }

  /**
   * Generates a lowercase string - the default for unknown shape requests.
   */
  protected function randomGenerateString(int $length): string {
    return strtolower((string) $this->randomGetGenerator()->name(max(1, $length)));
  }

  /**
   * Generates a 'Random::name()' string with original case preserved.
   */
  protected function randomGenerateName(int $length): string {
    return (string) $this->randomGetGenerator()->name(max(1, $length));
  }

  /**
   * Generates a Drupal-shaped machine name (lowercase + underscores).
   */
  protected function randomGenerateMachineName(int $length): string {
    return $this->randomGetGenerator()->machineName(max(1, $length));
  }

  /**
   * Generates an integer in '[min, max]' inclusive.
   */
  protected function randomGenerateInt(int $min, int $max): int {
    if ($min > $max) {
      [$min, $max] = [$max, $min];
    }

    return random_int($min, $max);
  }

  /**
   * Generates a syntactically valid email at the reserved '.test' TLD.
   *
   * RFC 6761 reserves '.test' for testing, so generated addresses can
   * never collide with real domains.
   */
  protected function randomGenerateEmail(): string {
    return strtolower(sprintf(
      '%s@%s.test',
      (string) $this->randomGetGenerator()->name(8),
      (string) $this->randomGetGenerator()->name(6),
    ));
  }

  /**
   * Generates a UUID v4 string.
   */
  protected function randomGenerateUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

  /**
   * Lazily resolves the string generator.
   */
  protected function randomGetGenerator(): Random {
    return $this->randomGenerator ??= new Random();
  }

}
