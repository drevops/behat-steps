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
 * Assert and set stored Drupal configuration values with automatic revert.
 *
 * Set a configuration value for test setup and assert that a configuration
 * object's key holds, or contains, an expected value. Nested keys are
 * addressable with dotted notation (for example `page.front`).
 *
 * Two families of assertions read the value differently:
 * - The default steps read the STORED value via editable configuration,
 *   ignoring `settings.php` overrides. This is symmetric with the set steps
 *   and is what most setup-and-assert scenarios need.
 * - The `effective` steps read the value through the config factory with
 *   module and `settings.php` overrides applied - the value the running site
 *   actually uses.
 *
 * Values are compared by their stringified form, so `true`, `42` and JSON
 * arrays written in a step match their typed configuration counterparts. The
 * `contain` steps match a substring for string values and membership for
 * array values, searched recursively.
 *
 * Configuration objects touched by the set steps are snapshotted on first
 * write and restored after the scenario: an existing object is reset to its
 * original data and an object that did not exist is deleted. Skip the revert
 * with `@behat-steps-skip:configAfterScenario` or `@behat-steps-skip:ConfigTrait`.
 *
 * @code
 * @api
 * Scenario: Assert configured values
 *   Given the config "mymodule.settings" key "api.endpoint" has the value "https://api.example.com"
 *   Then the config "mymodule.settings" key "api.endpoint" should have the value "https://api.example.com"
 *   And the config "system.site" key "name" should have the effective value "My overridden site"
 * @endcode
 */
trait ConfigTrait {

  /**
   * Original raw data of configuration objects touched during the scenario.
   *
   * Keyed by configuration name. Each entry records whether the object existed
   * before the first write so that revert can delete objects created by the
   * scenario rather than leaving empty shells behind.
   *
   * @var array<string, array{existed: bool, data: array<int|string, mixed>}>
   */
  protected array $configOriginalData = [];

  /**
   * Reset the snapshot registry before each scenario.
   */
  #[BeforeScenario('@api')]
  public function configBeforeScenario(BeforeScenarioScope $scope): void {
    $this->configOriginalData = [];
  }

  /**
   * Revert every touched configuration object after the scenario finishes.
   */
  #[AfterScenario('@api')]
  public function configAfterScenario(AfterScenarioScope $scope): void {
    if (
      $scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)
      || $scope->getScenario()->hasTag('behat-steps-skip:ConfigTrait')
    ) {
      $this->configOriginalData = [];
      return;
    }

    foreach ($this->configOriginalData as $name => $snapshot) {
      $config = \Drupal::configFactory()->getEditable($name);
      if ($snapshot['existed']) {
        $config->setData($snapshot['data'])->save();
      }
      else {
        $config->delete();
      }
    }

    $this->configOriginalData = [];
  }

  /**
   * Set a stored Drupal configuration value.
   *
   * @code
   * Given the config "system.site" key "page.front" has the value "/node"
   * @endcode
   */
  #[Given('the config :name key :key has the value :value')]
  public function configSet(string $name, string $key, string $value): void {
    $this->configSnapshot($name);
    \Drupal::configFactory()->getEditable($name)->set($key, $this->configCastValue($value))->save();
  }

  /**
   * Set multiple stored Drupal configuration values from a table.
   *
   * @code
   * Given the following config values:
   *   | name              | key          | value                   |
   *   | system.site       | name         | My site                 |
   *   | mymodule.settings | api.endpoint | https://api.example.com |
   *   | mymodule.settings | roles        | ["editor","reviewer"]   |
   * @endcode
   */
  #[Given('the following config values:')]
  public function configSetMultiple(TableNode $table): void {
    foreach ($table->getHash() as $row) {
      if (!isset($row['name'], $row['key']) || !array_key_exists('value', $row)) {
        throw new \RuntimeException('The config values table must contain "name", "key" and "value" columns.');
      }

      $this->configSnapshot($row['name']);
      \Drupal::configFactory()->getEditable($row['name'])->set($row['key'], $this->configCastValue($row['value']))->save();
    }
  }

  /**
   * Assert that a stored configuration value equals an expected value.
   *
   * @code
   * Then the config "system.site" key "name" should have the value "My site"
   * @endcode
   */
  #[Then('the config :name key :key should have the value :value')]
  public function configAssertValueEquals(string $name, string $key, string $value): void {
    $this->configCompareEquals($this->configReadStored($name, $key), $value, TRUE, $name, $key, 'value');
  }

  /**
   * Assert that a stored configuration value does not equal a value.
   *
   * @code
   * Then the config "system.site" key "name" should not have the value "Wrong"
   * @endcode
   */
  #[Then('the config :name key :key should not have the value :value')]
  public function configAssertValueNotEquals(string $name, string $key, string $value): void {
    $this->configCompareEquals($this->configReadStored($name, $key), $value, FALSE, $name, $key, 'value');
  }

  /**
   * Assert that a stored configuration value contains an expected value.
   *
   * @code
   * Then the config "system.site" key "name" should contain the value "site"
   * @endcode
   */
  #[Then('the config :name key :key should contain the value :value')]
  public function configAssertValueContains(string $name, string $key, string $value): void {
    $this->configCompareContains($this->configReadStored($name, $key), $value, TRUE, $name, $key, 'value');
  }

  /**
   * Assert that a stored configuration value does not contain a value.
   *
   * @code
   * Then the config "system.site" key "name" should not contain the value "xyz"
   * @endcode
   */
  #[Then('the config :name key :key should not contain the value :value')]
  public function configAssertValueNotContains(string $name, string $key, string $value): void {
    $this->configCompareContains($this->configReadStored($name, $key), $value, FALSE, $name, $key, 'value');
  }

  /**
   * Assert that an effective configuration value equals an expected value.
   *
   * The effective value has module and `settings.php` overrides applied.
   *
   * @code
   * Then the config "system.site" key "name" should have the effective value "Overridden"
   * @endcode
   */
  #[Then('the config :name key :key should have the effective value :value')]
  public function configAssertEffectiveValueEquals(string $name, string $key, string $value): void {
    $this->configCompareEquals($this->configReadEffective($name, $key), $value, TRUE, $name, $key, 'effective value');
  }

  /**
   * Assert that an effective configuration value does not equal a value.
   *
   * The effective value has module and `settings.php` overrides applied.
   *
   * @code
   * Then the config "system.site" key "name" should not have the effective value "Wrong"
   * @endcode
   */
  #[Then('the config :name key :key should not have the effective value :value')]
  public function configAssertEffectiveValueNotEquals(string $name, string $key, string $value): void {
    $this->configCompareEquals($this->configReadEffective($name, $key), $value, FALSE, $name, $key, 'effective value');
  }

  /**
   * Assert that an effective configuration value contains an expected value.
   *
   * The effective value has module and `settings.php` overrides applied.
   *
   * @code
   * Then the config "system.site" key "name" should contain the effective value "Over"
   * @endcode
   */
  #[Then('the config :name key :key should contain the effective value :value')]
  public function configAssertEffectiveValueContains(string $name, string $key, string $value): void {
    $this->configCompareContains($this->configReadEffective($name, $key), $value, TRUE, $name, $key, 'effective value');
  }

  /**
   * Assert that an effective configuration value does not contain a value.
   *
   * The effective value has module and `settings.php` overrides applied.
   *
   * @code
   * Then the config "system.site" key "name" should not contain the effective value "xyz"
   * @endcode
   */
  #[Then('the config :name key :key should not contain the effective value :value')]
  public function configAssertEffectiveValueNotContains(string $name, string $key, string $value): void {
    $this->configCompareContains($this->configReadEffective($name, $key), $value, FALSE, $name, $key, 'effective value');
  }

  /**
   * Read a stored configuration value, ignoring runtime overrides.
   *
   * Editable configuration objects never carry module or `settings.php`
   * overrides, so reading through one yields the value as saved.
   *
   * @param string $name
   *   The configuration object name.
   * @param string $key
   *   The configuration key, using dotted notation for nested keys.
   *
   * @return mixed
   *   The stored value, or NULL when the object or key does not exist.
   */
  protected function configReadStored(string $name, string $key): mixed {
    return \Drupal::configFactory()->getEditable($name)->get($key);
  }

  /**
   * Read an effective configuration value, with overrides applied.
   *
   * @param string $name
   *   The configuration object name.
   * @param string $key
   *   The configuration key, using dotted notation for nested keys.
   *
   * @return mixed
   *   The effective value, or NULL when the object or key does not exist.
   */
  protected function configReadEffective(string $name, string $key): mixed {
    return \Drupal::config($name)->get($key);
  }

  /**
   * Snapshot a configuration object's original data on first write.
   *
   * @param string $name
   *   The configuration object name.
   */
  protected function configSnapshot(string $name): void {
    if (array_key_exists($name, $this->configOriginalData)) {
      return;
    }

    $config = \Drupal::configFactory()->getEditable($name);
    $this->configOriginalData[$name] = [
      'existed' => !$config->isNew(),
      'data' => $config->getRawData(),
    ];
  }

  /**
   * Assert equality between an actual configuration value and an expected one.
   *
   * @param mixed $actual
   *   The value read from configuration.
   * @param string $expected
   *   The expected value as written in the step.
   * @param bool $should_match
   *   TRUE to require equality, FALSE to require inequality.
   * @param string $name
   *   The configuration object name, for error messages.
   * @param string $key
   *   The configuration key, for error messages.
   * @param string $descriptor
   *   How the value is described in error messages ("value" or
   *   "effective value").
   */
  protected function configCompareEquals(mixed $actual, string $expected, bool $should_match, string $name, string $key, string $descriptor): void {
    $is_set = $actual !== NULL;
    $actual_string = $this->configStringifyValue($actual);
    $matches = $is_set && $actual_string === $expected;

    if ($should_match) {
      if (!$is_set) {
        throw new \Exception(sprintf('The config "%s" key "%s" is not set, but it should have the %s "%s".', $name, $key, $descriptor, $expected));
      }

      if (!$matches) {
        throw new \Exception(sprintf('The config "%s" key "%s" has the %s "%s", but it should have the %s "%s".', $name, $key, $descriptor, $actual_string, $descriptor, $expected));
      }

      return;
    }

    if ($matches) {
      throw new \Exception(sprintf('The config "%s" key "%s" has the %s "%s", but it should not have the %s "%s".', $name, $key, $descriptor, $actual_string, $descriptor, $expected));
    }
  }

  /**
   * Assert containment between an actual configuration value and an expected one.
   *
   * @param mixed $actual
   *   The value read from configuration.
   * @param string $expected
   *   The expected value as written in the step.
   * @param bool $should_contain
   *   TRUE to require containment, FALSE to require the absence of it.
   * @param string $name
   *   The configuration object name, for error messages.
   * @param string $key
   *   The configuration key, for error messages.
   * @param string $descriptor
   *   How the value is described in error messages ("value" or
   *   "effective value").
   */
  protected function configCompareContains(mixed $actual, string $expected, bool $should_contain, string $name, string $key, string $descriptor): void {
    $is_set = $actual !== NULL;
    $contains = $is_set && $this->configValueContains($actual, $expected);
    $actual_string = $this->configStringifyValue($actual);

    if ($should_contain) {
      if (!$is_set) {
        throw new \Exception(sprintf('The config "%s" key "%s" is not set, but its %s should contain "%s".', $name, $key, $descriptor, $expected));
      }

      if (!$contains) {
        throw new \Exception(sprintf('The config "%s" key "%s" has the %s "%s", which does not contain "%s".', $name, $key, $descriptor, $actual_string, $expected));
      }

      return;
    }

    if ($contains) {
      throw new \Exception(sprintf('The config "%s" key "%s" has the %s "%s", which contains "%s" but should not.', $name, $key, $descriptor, $actual_string, $expected));
    }
  }

  /**
   * Determine whether a configuration value contains an expected value.
   *
   * A string or scalar value is matched by substring; an array value is
   * matched by membership, comparing the stringified form of each scalar leaf
   * and recursing into nested arrays.
   *
   * @param mixed $actual
   *   The value read from configuration.
   * @param string $expected
   *   The expected value as written in the step.
   *
   * @return bool
   *   TRUE when the value contains the expected value.
   */
  protected function configValueContains(mixed $actual, string $expected): bool {
    if (is_array($actual)) {
      return $this->configArrayContainsValue($actual, $expected);
    }

    return str_contains($this->configStringifyValue($actual), $expected);
  }

  /**
   * Recursively determine whether an array holds an expected scalar value.
   *
   * @param array<int|string, mixed> $data
   *   The array to search.
   * @param string $expected
   *   The expected value as written in the step.
   *
   * @return bool
   *   TRUE when a scalar leaf stringifies to the expected value.
   */
  protected function configArrayContainsValue(array $data, string $expected): bool {
    foreach ($data as $item) {
      if (is_array($item)) {
        if ($this->configArrayContainsValue($item, $expected)) {
          return TRUE;
        }
      }
      elseif ($this->configStringifyValue($item) === $expected) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Cast a string value from a step into the shape stored in configuration.
   *
   * @param string $value
   *   The raw value captured from the step or table cell.
   *
   * @return mixed
   *   The cast value: decoded JSON for array/object input, integer or float
   *   for numeric input, boolean for "true"/"false", NULL for "null", or the
   *   original string otherwise.
   */
  protected function configCastValue(string $value): mixed {
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
   * Stringify a configuration value for comparison and error messages.
   *
   * @param mixed $value
   *   The value to stringify.
   *
   * @return string
   *   The stringified value.
   */
  protected function configStringifyValue(mixed $value): string {
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
