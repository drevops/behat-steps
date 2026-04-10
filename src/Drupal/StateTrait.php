<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;

/**
 * Manage and assert Drupal State API values with automatic revert.
 *
 * Provides set, delete, and assertion steps for keys stored through
 * `\Drupal::state()`. Touched keys are snapshotted on first access and
 * reverted after the scenario finishes.
 *
 * Skip processing with tags: `@behat-steps-skip:stateBeforeScenario` and
 * `@behat-steps-skip:stateAfterScenario`, or skip both at once with the
 * convenience tag `@behat-steps-skip:StateTrait`.
 */
trait StateTrait {

  /**
   * Original state values captured before the scenario touched them.
   *
   * Keys absent from Drupal state are stored with a sentinel marker so they
   * can be deleted on revert rather than reset to NULL.
   *
   * @var array<string, array{exists: bool, value: mixed}>
   */
  protected array $stateOriginalValues = [];

  /**
   * Reset the snapshot registry before each scenario.
   */
  #[BeforeScenario]
  public function stateBeforeScenario(BeforeScenarioScope $scope): void {
    if (
      $scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)
      || $scope->getScenario()->hasTag('behat-steps-skip:StateTrait')
    ) {
      return;
    }

    $this->stateOriginalValues = [];
  }

  /**
   * Revert every touched state key after the scenario finishes.
   */
  #[AfterScenario]
  public function stateAfterScenario(AfterScenarioScope $scope): void {
    if (
      $scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)
      || $scope->getScenario()->hasTag('behat-steps-skip:StateTrait')
    ) {
      return;
    }

    $state = \Drupal::state();
    foreach ($this->stateOriginalValues as $name => $snapshot) {
      if ($snapshot['exists']) {
        $state->set($name, $snapshot['value']);
      }
      else {
        $state->delete($name);
      }
    }

    $this->stateOriginalValues = [];
  }

  /**
   * Set a Drupal state value.
   *
   * @code
   * Given the state "my_module.launched" has the value "1"
   * @endcode
   */
  #[Given('the state :name has the value :value')]
  public function stateSet(string $name, string $value): void {
    $this->stateStoreOriginalValue($name);
    \Drupal::state()->set($name, $this->stateNormaliseValue($value));
  }

  /**
   * Delete a Drupal state value.
   *
   * @code
   * Given the state "my_module.launched" does not exist
   * @endcode
   */
  #[Given('the state :name does not exist')]
  public function stateDelete(string $name): void {
    $this->stateStoreOriginalValue($name);
    \Drupal::state()->delete($name);
  }

  /**
   * Set multiple Drupal state values from a table.
   *
   * @code
   * Given the following state values:
   *   | name                   | value |
   *   | my_module.launched     | 1     |
   *   | my_module.feature_flag | 0     |
   * @endcode
   */
  #[Given('the following state values:')]
  public function stateSetMultiple(TableNode $table): void {
    $state = \Drupal::state();
    foreach ($table->getHash() as $row) {
      if (!isset($row['name']) || !array_key_exists('value', $row)) {
        throw new \RuntimeException('The state values table must contain "name" and "value" columns.');
      }
      $name = $row['name'];
      $this->stateStoreOriginalValue($name);
      $state->set($name, $this->stateNormaliseValue($row['value']));
    }
  }

  /**
   * Assert that a Drupal state value equals an expected value.
   *
   * @code
   * Then the state "my_module.launched" should have the value "1"
   * @endcode
   */
  #[Then('the state :name should have the value :value')]
  public function stateAssertHasValue(string $name, string $value): void {
    $actual = \Drupal::state()->get($name);
    if ($actual === NULL) {
      throw new \Exception(sprintf('The state "%s" does not exist, but it should have the value "%s".', $name, $value));
    }

    $expected = $this->stateNormaliseValue($value);
    $actual_stringified = $this->stateStringifyValue($actual);
    $expected_stringified = $this->stateStringifyValue($expected);
    if ($actual_stringified !== $expected_stringified) {
      throw new \Exception(sprintf('The state "%s" has the value "%s", but it should have the value "%s".', $name, $actual_stringified, $expected_stringified));
    }
  }

  /**
   * Assert that a Drupal state key does not exist.
   *
   * @code
   * Then the state "my_module.launched" should not exist
   * @endcode
   */
  #[Then('the state :name should not exist')]
  public function stateAssertNotExists(string $name): void {
    $actual = \Drupal::state()->get($name);
    if ($actual !== NULL) {
      throw new \Exception(sprintf('The state "%s" exists with the value "%s", but it should not exist.', $name, $this->stateStringifyValue($actual)));
    }
  }

  /**
   * Store the original state value for a key on first access.
   *
   * @param string $name
   *   The state key name.
   */
  protected function stateStoreOriginalValue(string $name): void {
    if (array_key_exists($name, $this->stateOriginalValues)) {
      return;
    }

    $sentinel = new \stdClass();
    $current = \Drupal::state()->get($name, $sentinel);
    $this->stateOriginalValues[$name] = $current === $sentinel
      ? ['exists' => FALSE, 'value' => NULL]
      : ['exists' => TRUE, 'value' => $current];
  }

  /**
   * Normalise a string value from a step into the shape actually stored.
   *
   * @param string $value
   *   The raw value captured from the step or table cell.
   *
   * @return mixed
   *   The normalised value: decoded JSON for array/object input, integer or
   *   float for numeric input, boolean for "true"/"false", NULL for "null",
   *   or the original string otherwise.
   */
  protected function stateNormaliseValue(string $value): mixed {
    $trimmed = trim($value);

    if ($trimmed === '') {
      return $value;
    }

    $lower = strtolower($trimmed);
    if ($lower === 'true') {
      return TRUE;
    }
    if ($lower === 'false') {
      return FALSE;
    }
    if ($lower === 'null') {
      return NULL;
    }

    if ($trimmed[0] === '{' || $trimmed[0] === '[') {
      $decoded = json_decode($trimmed, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
      }
    }

    if (is_numeric($trimmed)) {
      return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
    }

    return $value;
  }

  /**
   * Stringify a state value for comparison and error messages.
   *
   * @param mixed $value
   *   The value to stringify.
   *
   * @return string
   *   The stringified value.
   */
  protected function stateStringifyValue(mixed $value): string {
    if ($value === NULL) {
      return 'NULL';
    }
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if (is_scalar($value)) {
      return (string) $value;
    }
    return (string) json_encode($value);
  }

}
