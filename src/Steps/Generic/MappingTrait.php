<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Generic;

use Behat\Gherkin\Node\TableNode;
use Behat\Transformation\Transform;

/**
 * Replace `{{ Key }}` tokens in step arguments and table cells.
 *
 * - Resolve a token against the `mappings:` groups in the configuration.
 * - Fail the step when a key is not mapped.
 *
 * Whitespace inside the braces is ignored, so `{{ Key }}` and `{{Key}}`
 * resolve identically. Keys are unique across groups, so the group a key was
 * declared in does not take part in the lookup.
 *
 * Resolution keys off the token's own braces rather than the placeholder name,
 * so one map covers every step taking a string without the step opting in.
 *
 * Operates on Gherkin text alone: no Mink session and no driver, so the trait
 * works in any suite.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait MappingTrait {

  /**
   * Matches one `{{ Key }}` token, capturing the still-untrimmed key.
   */
  protected const MAPPING_TOKEN_REGEX = '#\{\{(.+?)\}\}#';

  /**
   * Replaces every mapping token inside a scalar step argument.
   *
   * @param string $argument
   *   The raw step argument.
   *
   * @return string
   *   The argument with every mapping token resolved.
   */
  #[Transform('#(.*\{\{.+?\}\}.*)#')]
  public function mappingTransformValue(string $argument): string {
    return $this->mappingSubstitute($argument);
  }

  /**
   * Replaces every mapping token inside a table's cells.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The raw table argument.
   *
   * @return \Behat\Gherkin\Node\TableNode
   *   A new table with every cell's mapping tokens resolved.
   */
  #[Transform('table:*')]
  public function mappingTransformTable(TableNode $table): TableNode {
    $rows = [];

    foreach ($table->getRows() as $row) {
      $rows[] = array_map($this->mappingSubstitute(...), $row);
    }

    return new TableNode($rows);
  }

  /**
   * Substitutes every mapping token found in a single string.
   *
   * @param string $value
   *   The string to resolve tokens in.
   *
   * @return string
   *   The string with every token replaced by its mapped value.
   */
  protected function mappingSubstitute(string $value): string {
    $result = preg_replace_callback(self::MAPPING_TOKEN_REGEX, fn(array $match): string => $this->getMapping(trim($match[1])), $value);

    return $result ?? $value;
  }

}
