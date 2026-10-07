<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use DrevOps\BehatSteps\Backend\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Exception\AssertionException;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Manage and assert Drupal State API values with automatic revert.
 *
 * Provides set, delete, and assertion steps for keys stored through
 * `\Drupal::state()`. Touched keys are snapshotted on first access and
 * reverted after the scenario finishes.
 *
 * Skip the revert with `@behat-steps-skip:StateTrait`. The snapshot registry
 * is cleared unconditionally before and after the scenario, so no snapshot
 * persists across scenarios.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait StateTrait {

  use StringTrait;

  /**
   * Original state values captured before the scenario touched them.
   *
   * Keys absent from Drupal state are stored with `exists` set to FALSE so
   * they can be deleted on revert rather than reset to NULL.
   *
   * @var array<string, array{exists: bool, value: mixed}>
   */
  protected array $stateOriginalValues = [];

  /**
   * Reset the snapshot registry before each scenario.
   */
  #[BeforeScenario]
  public function stateBeforeScenario(BeforeScenarioScope $scope): void {
    $this->stateOriginalValues = [];
  }

  /**
   * Revert every touched state key after the scenario finishes.
   */
  #[AfterScenario]
  public function stateAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      $this->stateOriginalValues = [];
      return;
    }

    // A scenario with no snapshot has nothing to revert, and resolving a
    // backend would fail a suite that lists none reaching Drupal.
    if ($this->stateOriginalValues === []) {
      return;
    }

    $backend = $this->backendFor(StateCapabilityInterface::class);

    foreach ($this->stateOriginalValues as $name => $snapshot) {
      if ($snapshot['exists']) {
        $backend->stateSet($name, $snapshot['value']);
      }
      else {
        $backend->stateDelete($name);
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
    $this->stateSetValue($name, $this->stringNormalizeValue($value));
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
    $this->backendFor(StateCapabilityInterface::class)->stateDelete($name);
  }

  /**
   * Set multiple Drupal state values from a table.
   *
   * @code
   * Given the following state values exist:
   *   | name                   | value |
   *   | my_module.launched     | 1     |
   *   | my_module.feature_flag | 0     |
   * @endcode
   */
  #[Given('the following state values exist:')]
  public function stateSetMultiple(TableNode $table): void {
    $this->backendFor(StateCapabilityInterface::class);

    foreach ($table->getHash() as $row) {
      if (!isset($row['name']) || !array_key_exists('value', $row)) {
        throw new \RuntimeException('The state values table must contain "name" and "value" columns.');
      }

      $this->stateSetValue($row['name'], $this->stringNormalizeValue($row['value']));
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
  public function stateAssertValueEquals(string $name, string $value): void {
    $state_value = $this->stateReadValue($name);
    if (!$state_value['exists']) {
      throw new AssertionException(sprintf('The state "%s" does not exist, but it should have the value "%s".', $name, $value));
    }

    $expected = $this->stringNormalizeValue($value);
    $actual_stringified = $this->stringFormatValue($state_value['value']);
    $expected_stringified = $this->stringFormatValue($expected);
    if ($actual_stringified !== $expected_stringified) {
      throw new AssertionException(sprintf('The state "%s" has the value "%s", but it should have the value "%s".', $name, $actual_stringified, $expected_stringified));
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
    $state_value = $this->stateReadValue($name);
    if ($state_value['exists']) {
      throw new AssertionException(sprintf('The state "%s" exists with the value "%s", but it should not exist.', $name, $this->stringFormatValue($state_value['value'])));
    }
  }

  /**
   * Read a state value, distinguishing stored NULL from a missing key.
   *
   * Existence is read through the backend's `stateExists()`, not from a NULL
   * check on the value. A backend that can tell a stored NULL from an absent
   * key then reports it as existing.
   *
   * `\Drupal::state()->get()` cannot tell the 2 cases apart: it applies the
   * `??` operator to the loaded value and returns the default for NULL.
   *
   * @param string $name
   *   The state key name.
   *
   * @return array{exists: bool, value: mixed}
   *   An associative array with `exists` (bool) and `value` (mixed).
   */
  public function stateReadValue(string $name): array {
    $backend = $this->backendFor(StateCapabilityInterface::class);

    if (!$backend->stateExists($name)) {
      return ['exists' => FALSE, 'value' => NULL];
    }

    return ['exists' => TRUE, 'value' => $backend->stateGet($name)];
  }

  /**
   * Set a state value, restored after the scenario.
   *
   * @param string $name
   *   The state key name.
   * @param mixed $value
   *   The value.
   */
  public function stateSetValue(string $name, mixed $value): void {
    $this->stateStoreOriginalValue($name);
    $this->backendFor(StateCapabilityInterface::class)->stateSet($name, $value);
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

    $this->stateOriginalValues[$name] = $this->stateReadValue($name);
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function stateConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Restore the state values a scenario changed once it finishes.'),
    ];
  }

}
