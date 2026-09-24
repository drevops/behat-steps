<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use DrevOps\BehatSteps\Attribute\Steps;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Transformation\Transform;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Replace `{{ Key }}` tokens in step arguments and table cells.
 *
 * - Resolve a token against the `mapping.groups` option.
 * - Fail the step when a key is not mapped.
 *
 * Whitespace inside the braces is ignored, so `{{ Key }}` and `{{Key}}`
 * resolve identically. Keys are unique across groups, so the group a key was
 * declared in does not take part in the lookup.
 *
 * The transform matches the token's braces rather than a placeholder name, so
 * one map covers every string argument without the step opting in.
 *
 * Operates on Gherkin text alone: no Mink session and no driver, so the trait
 * works in any suite.
 *
 * Skip processing with tag: `@behat-steps-skip:MappingTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
#[Steps]
trait MappingTrait {

  /**
   * Matches one `{{ Key }}` token, capturing the still-untrimmed key.
   */
  protected const MAPPING_TOKEN_REGEX = '#\{\{(.+?)\}\}#';

  /**
   * Whether token replacement is active for the current scenario.
   */
  protected bool $mappingEnabled = TRUE;

  /**
   * Resolves whether the current scenario replaces tokens.
   *
   * A transform receives no scope, so the resolution happens here and the
   * transforms read the result.
   */
  #[BeforeScenario]
  public function mappingBeforeScenario(BeforeScenarioScope $scope): void {
    $this->mappingEnabled = !$this->skipTag('MappingTrait', $scope);
  }

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
    if (!$this->mappingEnabled) {
      return $argument;
    }

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
    if (!$this->mappingEnabled) {
      return $table;
    }

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
  public function mappingSubstitute(string $value): string {
    $result = preg_replace_callback(self::MAPPING_TOKEN_REGEX, fn(array $match): string => $this->mappingGetValue(trim($match[1])), $value);

    return $result ?? $value;
  }

  /**
   * Returns a mapped value by its key.
   *
   * @param string $name
   *   The mapping key.
   *
   * @return string
   *   The mapped value.
   *
   * @throws \RuntimeException
   *   When the key is not mapped.
   */
  public function mappingGetValue(string $name): string {
    $mappings = $this->mappingGetFlattened();

    if (!isset($mappings[$name])) {
      throw new \RuntimeException(sprintf('No such mapping: %s', $name));
    }

    return $mappings[$name];
  }

  /**
   * Flattens the configured groups into a single key to value map.
   *
   * A group is a way to organise the configuration and takes no part in the
   * lookup, so a key appearing in two groups would make its bare-key token
   * ambiguous.
   *
   * @return array<string, string>
   *   Mapped values keyed by mapping key.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the same key appears in more than one group.
   */
  protected function mappingGetFlattened(): array {
    $groups = $this->getOption('mapping', 'groups');
    $flat = [];
    $origins = [];

    foreach (is_array($groups) ? $groups : [] as $group => $entries) {
      foreach (is_array($entries) ? $entries : [] as $key => $value) {
        if (isset($origins[$key])) {
          throw new InvalidConfigurationException(sprintf('Duplicate mapping key "%s" found in groups "%s" and "%s" under "mapping.groups". Mapping keys must be unique across all groups.', $key, $origins[$key], $group));
        }

        $origins[$key] = $group;
        $flat[$key] = (string) $value;
      }
    }

    return $flat;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function mappingConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Replace `{{ Key }}` tokens in step arguments and table cells. Turn it off to pass a token through to a step untouched.',
      ],
      'groups' => [
        'default' => [],
        'description' => 'Named value mappings grouped for organisation. Group names take no part in the lookup, so a key must be unique across all groups.',
      ],
    ];
  }

}
