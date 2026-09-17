<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Mink\Driver\Selenium2Driver;
use DrevOps\BehatSteps\Behat\Tag;

/**
 * Disable Drupal config overrides from settings.php during a scenario.
 *
 * Config overrides set in `settings.php` replace the stored configuration at
 * runtime. They cannot be disabled from the Behat process because tests run
 * in a separate process from the system under test (SUT).
 *
 * This trait signals the SUT - through a request header, a `$_SERVER` entry
 * and an environment variable - that specific config objects should be read
 * from their original (unoverridden) values. The SUT is responsible for
 * reading that signal and calling `ImmutableConfig::getOriginal()` instead of
 * `ImmutableConfig::get()` for the listed config names.
 *
 * Activated by adding `@disable-config-override:CONFIG_NAME` tags to a
 * feature or scenario. Multiple tags are combined into a comma-separated
 * list. Runs on every step because some steps reset headers set earlier in
 * the scenario.
 *
 * Limitations:
 * - Cannot be used with Selenium/JavaScript drivers (the underlying driver
 *   does not expose request headers).
 * - The SUT must implement support for the `X-Config-No-Override` header,
 *   the `HTTP_X_CONFIG_NO_OVERRIDE` `$_SERVER` entry or the matching
 *   environment variable. An example implementation:
 *   @code
 *   public function getConfigValue(string $name, string $key): mixed {
 *     $config = $this->configFactory->get($name);
 *     $header = $_SERVER['HTTP_X_CONFIG_NO_OVERRIDE'] ?? getenv('HTTP_X_CONFIG_NO_OVERRIDE') ?: '';
 *     if (in_array($name, array_map('trim', explode(',', $header)), TRUE)) {
 *       return $config->getOriginal($key, FALSE);
 *     }
 *     return $config->get($key);
 *   }
 *   @endcode
 *
 * The signal is also written to the shared request-header bag, so a trait
 * that issues its own HTTP requests - `RestTrait` - carries it too.
 *
 * Example:
 * @code
 * @api @disable-config-override:system.site @disable-config-override:myconfig.settings
 * Scenario: Render the page with original config values
 *   When I visit "/"
 *   Then the response should contain "Original site name"
 * @endcode
 *
 * Skip processing with tags: `@behat-steps-skip:configOverrideBeforeScenario`
 * and `@behat-steps-skip:configOverrideBeforeStep`.
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
trait ConfigOverrideTrait {

  use HelperTrait;

  /**
   * Config names parsed from `@disable-config-override:*` tags.
   *
   * @var array<int, string>
   */
  protected array $configOverrideDisabledNames = [];

  /**
   * Whether the `BeforeStep` hook should be skipped for this scenario.
   */
  protected bool $configOverrideSkipBeforeStep = FALSE;

  /**
   * Collect `@disable-config-override:*` tags for the current scenario.
   *
   * The signal propagated by a previous scenario is cleared before the
   * skip-tag check, so no signal persists across scenarios.
   * `@behat-steps-skip:configOverrideBeforeScenario` bypasses tag collection,
   * not the clearing.
   */
  #[BeforeScenario('@api')]
  public function configOverrideBeforeScenario(BeforeScenarioScope $scope): void {
    $this->configOverrideDisabledNames = [];
    $this->configOverrideSkipBeforeStep = FALSE;
    $this->configOverrideClearSignal();

    if ($this->skipTag(__FUNCTION__, $scope)) {
      return;
    }

    // BeforeStep scope does not have access to scenario tags, so resolve the
    // skip flag here.
    if ($this->skipTag('configOverrideBeforeStep', $scope)) {
      $this->configOverrideSkipBeforeStep = TRUE;
    }

    $tags = array_unique(Tag::all($scope));
    $prefix = 'disable-config-override:';
    foreach ($tags as $tag) {
      if (str_starts_with($tag, $prefix)) {
        $name = substr($tag, strlen($prefix));
        if ($name !== '' && !in_array($name, $this->configOverrideDisabledNames, TRUE)) {
          $this->configOverrideDisabledNames[] = $name;
        }
      }
    }
  }

  /**
   * Apply the `X-Config-No-Override` signal before every step.
   *
   * This runs on every step because some steps reset headers set earlier in
   * the scenario (for example, Drupal Extension login steps).
   */
  #[BeforeStep]
  public function configOverrideBeforeStep(BeforeStepScope $scope): void {
    if ($this->configOverrideSkipBeforeStep || $this->configOverrideDisabledNames === []) {
      // Nothing to propagate. The process-level signal outlives the scenario
      // that set it and 'configOverrideBeforeScenario()' only runs for '@api',
      // so it is cleared here as well as the driver-level header.
      $this->configOverrideClearSignal();
      $this->configOverrideClearDriverHeader();

      return;
    }

    $value = implode(',', $this->configOverrideDisabledNames);

    // Set request header on the Mink driver for BrowserKit-based sessions.
    // Selenium-based drivers cannot set request headers - skip silently.
    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof Selenium2Driver) {
      $driver->setRequestHeader('X-Config-No-Override', $value);
    }

    $this->helperSetRequestHeader('X-Config-No-Override', $value);

    // For SUTs accessed via direct code invocation within the same process.
    $_SERVER['HTTP_X_CONFIG_NO_OVERRIDE'] = $value;

    // For SUTs accessed via Drush subprocesses.
    putenv('HTTP_X_CONFIG_NO_OVERRIDE=' . $value);
  }

  /**
   * Clear the process-level and REST-level X-Config-No-Override signal.
   *
   * Driver-level request headers are cleared separately in the BeforeStep
   * hook because the session is not guaranteed to be started at the point
   * BeforeScenario runs.
   */
  protected function configOverrideClearSignal(): void {
    unset($_SERVER['HTTP_X_CONFIG_NO_OVERRIDE']);
    putenv('HTTP_X_CONFIG_NO_OVERRIDE');

    $this->helperUnsetRequestHeader('X-Config-No-Override');
  }

  /**
   * Clear the driver-level X-Config-No-Override request header.
   */
  protected function configOverrideClearDriverHeader(): void {
    try {
      $driver = $this->getSession()->getDriver();
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
      return;
    }
    // @codeCoverageIgnoreEnd
    if (!$driver instanceof Selenium2Driver) {
      $driver->setRequestHeader('X-Config-No-Override', '');
    }
  }

}
