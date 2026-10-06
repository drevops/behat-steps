<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Transformation\Transform;
use DrevOps\BehatSteps\Behat\Config\Option;
use Drupal\Component\Utility\Random;

/**
 * Replace random-value tokens in step arguments and table cells.
 *
 * - Resolve `[?<name>:<type>[,<args>]]` tokens to generated values.
 * - Return 1 value per token for the whole scenario.
 *
 * Built-in types are `string`, `name`, `machine_name`, `int`, `email` and
 * `uuid`. The default is `string` with length `10`, so `[?title]`,
 * `[?title:string]` and `[?title:string,10]` share 1 value.
 *
 * Operates on Gherkin text alone: no Mink session and no backend, so the trait
 * works in any suite.
 *
 * Skip processing with tag: `@behat-steps-skip:RandomTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait RandomTrait {

  protected const string RANDOM_BRACKET_REGEX = '#(\[\?[a-z0-9_]+(?::[^\]]+)?\])#i';

  /**
   * Maps each token literal in the feature file to its canonical cache key.
   *
   * The map caches the parsed key, so the same literal is not re-parsed on
   * every transform invocation.
   *
   * @var array<string, string>
   */
  protected array $randomLiterals = [];

  /**
   * Maps each canonical cache key to its generated value.
   *
   * The canonical key is 'name:type:arg1,arg2,...' with defaults applied,
   * so '[?title]', '[?title:string]' and '[?title:string,10]' share the
   * same key.
   *
   * @var array<string, string|int>
   */
  protected array $randomValues = [];

  /**
   * Random string generator (lazy).
   */
  protected ?Random $randomGenerator = NULL;

  /**
   * Whether token replacement is active for the current scenario.
   */
  protected bool $randomEnabled = TRUE;

  /**
   * Pre-resolves every token literal found in the current scenario.
   *
   * Every literal is cached before the first step runs, so repeated token
   * literals stay stable.
   */
  #[BeforeScenario]
  public function randomBeforeScenario(BeforeScenarioScope $scope): void {
    $this->randomValues = [];
    $this->randomLiterals = [];

    $this->randomEnabled = !$this->skipTag(__TRAIT__, $scope);

    if (!$this->randomEnabled) {
      return;
    }

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
    if (!$this->randomEnabled) {
      return $message;
    }

    return $this->randomSubstitute($message);
  }

  /**
   * Substitutes '[?...]' tokens inside table cells.
   */
  #[Transform('table:*')]
  public function randomTransformTable(TableNode $table): TableNode {
    if (!$this->randomEnabled) {
      return $table;
    }

    return $this->randomSubstituteTable($table);
  }

  /**
   * Substitutes every token match in '$message' via 'randomResolveLiteral()'.
   */
  public function randomSubstitute(string $message): string {
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
  public function randomSubstituteTable(TableNode $table): TableNode {
    $rows = [];
    foreach ($table->getRows() as $row) {
      $rows[] = array_map($this->randomSubstitute(...), $row);
    }

    return new TableNode($rows);
  }

  /**
   * Resolves a token literal to its generated value.
   */
  public function randomResolveLiteral(string $literal): string|int {
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
   * Malformed input (a non-integer length, a wrong arg count, extra args on
   * an argless type) throws 'RuntimeException'. Failing fast prevents
   * typos like '[?title:string,abc]' from silently producing a 1-character
   * string.
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
   * Validates length-style args (1 optional non-negative integer).
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
   * Validates 'int' args (0 args for full range, or 2 integer bounds).
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
   * Validates argless types ('email', 'uuid'): any positional arg is an error.
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
   * 'randomNormalizeArgs()' has already validated the args, so the casts are
   * safe.
   *
   * @param string $type
   *   The generator type extracted from the token.
   * @param list<string> $args
   *   Already normalized args from 'randomNormalizeArgs()'.
   *
   * @return string|int
   *   The generated value.
   */
  public function randomGenerate(string $type, array $args): string|int {
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
   * Generates a lowercase string, the default token type.
   */
  public function randomGenerateString(int $length): string {
    return strtolower((string) $this->randomGetGenerator()->name(max(1, $length)));
  }

  /**
   * Generates a 'Random::name()' string with original case preserved.
   */
  public function randomGenerateName(int $length): string {
    return (string) $this->randomGetGenerator()->name(max(1, $length));
  }

  /**
   * Generates a lowercase alphanumeric machine name starting with a letter.
   */
  public function randomGenerateMachineName(int $length): string {
    return $this->randomGetGenerator()->machineName(max(1, $length));
  }

  /**
   * Generates an integer in '[min, max]' inclusive.
   */
  public function randomGenerateInt(int $min, int $max): int {
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
  public function randomGenerateEmail(): string {
    return strtolower(sprintf(
      '%s@%s.test',
      (string) $this->randomGetGenerator()->name(8),
      (string) $this->randomGetGenerator()->name(6),
    ));
  }

  /**
   * Generates a UUID v4 string.
   */
  public function randomGenerateUuid(): string {
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

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function randomConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Replace `[?name:type]` tokens in step arguments and table cells. Turn it off to pass a token through to a step untouched.'),
    ];
  }

}
