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
use DrevOps\BehatSteps\Backend\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Exception\AssertionException;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Assert and set stored Drupal configuration values with automatic revert.
 *
 * Set a configuration value for test setup and assert that a configuration
 * object's key holds, or contains, an expected value. Nested keys are
 * addressable with dotted notation (for example `page.front`).
 *
 * 2 families of assertions read the value differently:
 * - The default steps read the stored value, with module and `settings.php`
 *   overrides left unapplied. This is symmetric with the set steps and is
 *   what most setup-and-assert scenarios need.
 * - The `effective` steps read the value with module and `settings.php`
 *   overrides applied: the value the running site uses.
 *
 * Values are compared by their stringified form, so `true`, `42` and JSON
 * arrays written in a step match their typed configuration counterparts. The
 * `contain` steps match a substring for string values and membership for
 * array values, searched recursively.
 *
 * Configuration objects touched by the set steps are snapshotted on first
 * write and restored after the scenario. An existing object is reset to its
 * original data, and an object that did not exist is deleted. Skip the revert
 * with `@behat-steps-skip:ConfigTrait`.
 *
 * @code
 * Scenario: Assert configured values
 *   Given the config "mymodule.settings" with the key "api.endpoint" has the value "https://api.example.com"
 *   Then the config "mymodule.settings" with the key "api.endpoint" should have the value "https://api.example.com"
 *   And the config "system.site" with the key "name" should have the effective value "My overridden site"
 * @endcode
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait ConfigTrait {

  use StringTrait;

  /**
   * Original raw data of configuration objects touched during the scenario.
   *
   * Keyed by configuration name. Each entry records whether the object
   * existed before the first write, so the revert deletes objects the
   * scenario created instead of leaving them empty.
   *
   * @var array<string, array{exists: bool, value: array<int|string, mixed>}>
   */
  protected array $configOriginalData = [];

  /**
   * Reset the snapshot registry before each scenario.
   */
  #[BeforeScenario]
  public function configBeforeScenario(BeforeScenarioScope $scope): void {
    $this->configOriginalData = [];
  }

  /**
   * Revert every touched configuration object after the scenario finishes.
   */
  #[AfterScenario]
  public function configAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      $this->configOriginalData = [];
      return;
    }

    // A scenario that recorded no snapshot has nothing to revert, and
    // resolving a backend would fail a suite that lists none reaching Drupal.
    if ($this->configOriginalData === []) {
      return;
    }

    $backend = $this->backendFor(ConfigCapabilityInterface::class);

    foreach ($this->configOriginalData as $name => $snapshot) {
      if ($snapshot['exists']) {
        $backend->configSetData($name, $snapshot['value']);
      }
      else {
        $backend->configDelete($name);
      }
    }

    $this->configOriginalData = [];
  }

  /**
   * Set a stored Drupal configuration value.
   *
   * @code
   * Given the config "system.site" with the key "page.front" has the value "/node"
   * @endcode
   */
  #[Given('the config :name with the key :key has the value :value')]
  public function configSet(string $name, string $key, string $value): void {
    $this->configSetValue($name, $key, $this->stringNormalizeValue($value));
  }

  /**
   * Set multiple stored Drupal configuration values from a table.
   *
   * @code
   * Given the following config values exist:
   *   | name              | key          | value                   |
   *   | system.site       | name         | My site                 |
   *   | mymodule.settings | api.endpoint | https://api.example.com |
   *   | mymodule.settings | roles        | ["editor","reviewer"]   |
   * @endcode
   */
  #[Given('the following config values exist:')]
  public function configSetMultiple(TableNode $table): void {
    $this->backendFor(ConfigCapabilityInterface::class);

    foreach ($table->getHash() as $row) {
      if (!isset($row['name'], $row['key']) || !array_key_exists('value', $row)) {
        throw new \RuntimeException('The config values table must contain "name", "key" and "value" columns.');
      }

      $this->configSetValue($row['name'], $row['key'], $this->stringNormalizeValue($row['value']));
    }
  }

  /**
   * Assert that a stored configuration value equals an expected value.
   *
   * @code
   * Then the config "system.site" with the key "name" should have the value "My site"
   * @endcode
   */
  #[Then('the config :name with the key :key should have the value :value')]
  public function configAssertValueEquals(string $name, string $key, string $value): void {
    $this->configAssertEquals($this->configFindStoredValue($name, $key), $value, TRUE, $name, $key, 'value');
  }

  /**
   * Assert that a stored configuration value does not equal a value.
   *
   * @code
   * Then the config "system.site" with the key "name" should not have the value "Wrong"
   * @endcode
   */
  #[Then('the config :name with the key :key should not have the value :value')]
  public function configAssertValueNotEquals(string $name, string $key, string $value): void {
    $this->configAssertEquals($this->configFindStoredValue($name, $key), $value, FALSE, $name, $key, 'value');
  }

  /**
   * Assert that a stored configuration value contains an expected value.
   *
   * @code
   * Then the config "system.site" with the key "name" should contain the value "site"
   * @endcode
   */
  #[Then('the config :name with the key :key should contain the value :value')]
  public function configAssertValueContains(string $name, string $key, string $value): void {
    $this->configAssertContains($this->configFindStoredValue($name, $key), $value, TRUE, $name, $key, 'value');
  }

  /**
   * Assert that a stored configuration value does not contain a value.
   *
   * @code
   * Then the config "system.site" with the key "name" should not contain the value "xyz"
   * @endcode
   */
  #[Then('the config :name with the key :key should not contain the value :value')]
  public function configAssertValueNotContains(string $name, string $key, string $value): void {
    $this->configAssertContains($this->configFindStoredValue($name, $key), $value, FALSE, $name, $key, 'value');
  }

  /**
   * Assert that an effective configuration value equals an expected value.
   *
   * The effective value has module and `settings.php` overrides applied.
   *
   * @code
   * Then the config "system.site" with the key "name" should have the effective value "Overridden"
   * @endcode
   */
  #[Then('the config :name with the key :key should have the effective value :value')]
  public function configAssertEffectiveValueEquals(string $name, string $key, string $value): void {
    $this->configAssertEquals($this->configFindEffectiveValue($name, $key), $value, TRUE, $name, $key, 'effective value');
  }

  /**
   * Assert that an effective configuration value does not equal a value.
   *
   * The effective value has module and `settings.php` overrides applied.
   *
   * @code
   * Then the config "system.site" with the key "name" should not have the effective value "Wrong"
   * @endcode
   */
  #[Then('the config :name with the key :key should not have the effective value :value')]
  public function configAssertEffectiveValueNotEquals(string $name, string $key, string $value): void {
    $this->configAssertEquals($this->configFindEffectiveValue($name, $key), $value, FALSE, $name, $key, 'effective value');
  }

  /**
   * Assert that an effective configuration value contains an expected value.
   *
   * The effective value has module and `settings.php` overrides applied.
   *
   * @code
   * Then the config "system.site" with the key "name" should contain the effective value "Over"
   * @endcode
   */
  #[Then('the config :name with the key :key should contain the effective value :value')]
  public function configAssertEffectiveValueContains(string $name, string $key, string $value): void {
    $this->configAssertContains($this->configFindEffectiveValue($name, $key), $value, TRUE, $name, $key, 'effective value');
  }

  /**
   * Assert that an effective configuration value does not contain a value.
   *
   * The effective value has module and `settings.php` overrides applied.
   *
   * @code
   * Then the config "system.site" with the key "name" should not contain the effective value "xyz"
   * @endcode
   */
  #[Then('the config :name with the key :key should not contain the effective value :value')]
  public function configAssertEffectiveValueNotContains(string $name, string $key, string $value): void {
    $this->configAssertContains($this->configFindEffectiveValue($name, $key), $value, FALSE, $name, $key, 'effective value');
  }

  /**
   * Find a stored configuration value, ignoring runtime overrides.
   *
   * A key holding NULL returns NULL, as a missing one does.
   *
   * @param string $name
   *   The configuration object name.
   * @param string $key
   *   The configuration key, using dotted notation for nested keys.
   *
   * @return mixed
   *   The stored value, or NULL when the object or key does not exist.
   */
  public function configFindStoredValue(string $name, string $key): mixed {
    return $this->backendFor(ConfigCapabilityInterface::class)->configGetOriginal($name, $key);
  }

  /**
   * Find an effective configuration value, with overrides applied.
   *
   * A key holding NULL returns NULL, as a missing one does.
   *
   * @param string $name
   *   The configuration object name.
   * @param string $key
   *   The configuration key, using dotted notation for nested keys.
   *
   * @return mixed
   *   The effective value, or NULL when the object or key does not exist.
   */
  public function configFindEffectiveValue(string $name, string $key): mixed {
    return $this->backendFor(ConfigCapabilityInterface::class)->configGet($name, $key);
  }

  /**
   * Set a stored configuration value, restored after the scenario.
   *
   * @param string $name
   *   The configuration object name.
   * @param string $key
   *   The configuration key, using dotted notation for nested keys.
   * @param mixed $value
   *   The value.
   */
  public function configSetValue(string $name, string $key, mixed $value): void {
    $this->configStoreOriginalData($name);
    $this->backendFor(ConfigCapabilityInterface::class)->configSet($name, $key, $value);
  }

  /**
   * Snapshot a configuration object's original data on first write.
   *
   * @param string $name
   *   The configuration object name.
   */
  protected function configStoreOriginalData(string $name): void {
    if (array_key_exists($name, $this->configOriginalData)) {
      return;
    }

    $backend = $this->backendFor(ConfigCapabilityInterface::class);
    $this->configOriginalData[$name] = [
      'exists' => $backend->configExists($name),
      'value' => $backend->configGetData($name),
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
  protected function configAssertEquals(mixed $actual, string $expected, bool $should_match, string $name, string $key, string $descriptor): void {
    $is_set = $actual !== NULL;
    $actual_string = $this->stringFormatValue($actual);
    $is_match = $is_set && $actual_string === $expected;

    if ($should_match) {
      if (!$is_set) {
        throw new AssertionException(sprintf('The config "%s" with the key "%s" is not set, but it should have the %s "%s".', $name, $key, $descriptor, $expected));
      }

      if (!$is_match) {
        throw new AssertionException(sprintf('The config "%s" with the key "%s" has the %s "%s", but it should have the %s "%s".', $name, $key, $descriptor, $actual_string, $descriptor, $expected));
      }

      return;
    }

    if ($is_match) {
      throw new AssertionException(sprintf('The config "%s" with the key "%s" has the %s "%s", but it should not have the %s "%s".', $name, $key, $descriptor, $actual_string, $descriptor, $expected));
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
  protected function configAssertContains(mixed $actual, string $expected, bool $should_contain, string $name, string $key, string $descriptor): void {
    $is_set = $actual !== NULL;
    $is_contained = $is_set && $this->configValueContains($actual, $expected);
    $actual_string = $this->stringFormatValue($actual);

    if ($should_contain) {
      if (!$is_set) {
        throw new AssertionException(sprintf('The config "%s" with the key "%s" is not set, but its %s should contain "%s".', $name, $key, $descriptor, $expected));
      }

      if (!$is_contained) {
        throw new AssertionException(sprintf('The config "%s" with the key "%s" has the %s "%s", which does not contain "%s".', $name, $key, $descriptor, $actual_string, $expected));
      }

      return;
    }

    if ($is_contained) {
      throw new AssertionException(sprintf('The config "%s" with the key "%s" has the %s "%s", which contains "%s", but it should not.', $name, $key, $descriptor, $actual_string, $expected));
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

    return str_contains($this->stringFormatValue($actual), $expected);
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
      elseif ($this->stringFormatValue($item) === $expected) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function configConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Restore the configuration values a scenario changed once it finishes.'),
    ];
  }

}
