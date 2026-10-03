<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Transformation\Transform;
use DrevOps\BehatSteps\Behat\Config\Option;

/**
 * Convert relative date expressions into timestamps or formatted dates.
 *
 * Supports values and tables.
 *
 * Possible formats:
 * - `[relative:OFFSET]`
 * - `[relative:OFFSET#FORMAT]`
 *
 * with:
 * - `OFFSET`: any format that can be parsed by `strtotime()`.
 * - `FORMAT`: `date()` format for additional processing.
 *
 * Examples:
 * - `[relative:-1 day]` converted to `1893456000`
 * - `[relative:-1 day#Y-m-d]` converted to `2017-11-05`
 *
 * `dateRelativeProcessValue()` is public API. It and its helpers are static,
 * so a token resolves without a context instance.
 *
 * Late static binding routes the resolution through a `dateGetNow()` override
 * in the composing context. That override is the supported way to hold the
 * current time constant.
 *
 * Skip processing with tag: `@behat-steps-skip:DateTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait DateTrait {

  /**
   * Whether token replacement is active for the current scenario.
   */
  protected bool $dateEnabled = TRUE;

  /**
   * Resolves whether the current scenario replaces tokens.
   *
   * A transform receives no scope, so the resolution happens here and the
   * transforms read the result.
   */
  #[BeforeScenario]
  public function dateBeforeScenario(BeforeScenarioScope $scope): void {
    $this->dateEnabled = !$this->skipTag(__TRAIT__, $scope);
  }

  /**
   * Transform a scalar value.
   */
  #[Transform(':datetime')]
  #[Transform(':value')]
  #[Transform(':partial_value')]
  #[Transform(':expected_value')]
  public function dateRelativeTransformValue(string $value): string {
    if (!$this->dateEnabled) {
      return $value;
    }

    return static::dateRelativeProcessValue($value);
  }

  /**
   * Transform a tabular value.
   */
  #[Transform('table:*')]
  public function dateRelativeTransformTable(TableNode $table): TableNode {
    if (!$this->dateEnabled || !static::dateRelativeStringHasToken($table->getTableAsString())) {
      return $table;
    }

    $rows = [];
    foreach ($table->getRows() as $hash) {
      $row = [];
      foreach ($hash as $cell) {
        $row[] = static::dateRelativeProcessValue($cell);
      }
      $rows[] = $row;
    }

    return new TableNode($rows);
  }

  /**
   * Process date values to convert relative timestamps to actual values.
   *
   * Public API: a composing context may call this directly to resolve a token
   * outside a step.
   *
   * Possible formats:
   * [relative:OFFSET]
   * [relative:OFFSET#FORMAT]
   * - OFFSET: any format that can be parsed by strtotime()
   * - FORMAT: date() format for additional processing.
   *
   * Examples:
   * [relative:-1 day] would be converted to 1893456000
   * [relative:-1 day#Y-m-d] would be converted to 2017-11-05
   *
   * @code
   * Given the following "article" content:
   *   | title        | created           |
   *   | test article | [relative:-1 day] |
   * @endcode
   *
   * @note An absent `$now` resolves to the current minute, not a fixed time of
   * day. A formatted return value whose offset crosses midnight can then fall
   * on a different day than the scenario expects.
   */
  public static function dateRelativeProcessValue(string $value, ?int $now = NULL): string {
    if (!static::dateRelativeStringHasToken($value)) {
      return $value;
    }

    // An absent `now` truncates to the current minute, so tokens resolved
    // within the same minute share a base timestamp.
    $now = $now ?: strtotime(date('Y-m-d H:i:00', static::dateGetNow()));
    $now = $now ?: NULL;

    return (string) preg_replace_callback('/\[relative:([^]\[#]+)(?:#([^]\[]+))?]/', function (array $matches) use ($now): string {
      $offset = $matches[1];

      $timestamp = strtotime($offset, $now);
      if ($timestamp === FALSE) {
        throw new \RuntimeException(sprintf('The relative date offset cannot be evaluated: "%s".', $offset));
      }

      if (!isset($matches[2])) {
        return (string) $timestamp;
      }

      $format = $matches[2];
      $formatted = date($format, $timestamp);

      if (trim($formatted) === '') {
        throw new \RuntimeException(sprintf('The relative date format produced an empty value: "%s".', $format));
      }

      return $formatted;
    }, $value);
  }

  /**
   * Check whether a string holds a relative date token.
   */
  public static function dateRelativeStringHasToken(string $string): bool {
    return str_contains($string, '[relative:');
  }

  /**
   * Get the current timestamp.
   */
  public static function dateGetNow(): int {
    // @codeCoverageIgnoreStart
    return time();
    // @codeCoverageIgnoreEnd
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function dateConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Replace `[relative:...]` tokens in step arguments and table cells. Turn it off to pass a token through to a step untouched.'),
    ];
  }

}
