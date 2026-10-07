<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Mink\Capability\RequestHeaderCapabilityInterface;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Helper\Web\RequestHeadersTrait;

/**
 * Disable Drupal config overrides from settings.php during a scenario.
 *
 * Config overrides set in `settings.php` replace the stored configuration at
 * runtime. They cannot be disabled from the Behat process because tests run
 * in a separate process from the system under test (SUT).
 *
 * This trait signals the SUT that specific config objects should be read
 * from their original (unoverridden) values. The signal is a request header,
 * a `$_SERVER` entry and an environment variable.
 *
 * The SUT is responsible for reading that signal and calling
 * `ImmutableConfig::getOriginal()` instead of `ImmutableConfig::get()` for
 * the listed config names.
 *
 * Activated by adding `@disable-config-override:CONFIG_NAME` tags to a
 * feature or scenario. Multiple tags are combined into a comma-separated
 * list.
 *
 * The signal is applied before every step because some steps reset headers
 * set earlier in the scenario.
 *
 * Limitations:
 * - The request header reaches the SUT only on a browser driver providing
 *   `RequestHeaderCapabilityInterface`. A WebDriver session carries no request
 *   headers, so a scenario running on Selenium falls back to the `$_SERVER`
 *   entry and the environment variable alone.
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
 * The signal is also written to the request-header bag, so a trait that
 * issues its own HTTP requests - `RestTrait` - carries it too. The bag is a
 * property of the context object, so the signal reaches `RestTrait` when the
 * same context composes both traits, as the shipped `DrupalContext` does.
 *
 * Example:
 * @code
 * @disable-config-override:system.site @disable-config-override:myconfig.settings
 * Scenario: Render the page with original config values
 *   When I visit "/"
 *   Then the response should contain "Original site name"
 * @endcode
 *
 * Skip processing with tag: `@behat-steps-skip:ConfigOverrideTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait ConfigOverrideTrait {

  use RequestHeadersTrait;

  /**
   * The tag that disables the overrides of the config object it names.
   */
  protected const string CONFIG_OVERRIDE_DISABLE_TAG = 'disable-config-override';

  /**
   * Config names parsed from `@disable-config-override:*` tags.
   *
   * @var array<int, string>
   */
  protected array $configOverrideDisabledNames = [];

  /**
   * Collect `@disable-config-override:*` tags for the current scenario.
   *
   * The signal propagated by a previous scenario is cleared before the
   * skip-tag check, so no signal persists across scenarios.
   * `@behat-steps-skip:ConfigOverrideTrait` bypasses tag collection, not the
   * clearing.
   */
  #[BeforeScenario]
  public function configOverrideBeforeScenario(BeforeScenarioScope $scope): void {
    $this->configOverrideDisabledNames = [];
    $this->configOverrideClearSignal();

    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    $this->configOverrideDisabledNames = array_values(array_unique(Tag::values($scope, self::CONFIG_OVERRIDE_DISABLE_TAG)));
  }

  /**
   * Apply the `X-Config-No-Override` signal before every step.
   *
   * Some steps reset the request headers set earlier in the scenario, so the
   * signal is applied again before each one.
   */
  #[BeforeStep]
  public function configOverrideBeforeStep(BeforeStepScope $scope): void {
    if ($this->configOverrideDisabledNames === []) {
      // The process-level signal persists beyond the scenario that set it, so
      // it is cleared here along with the browser driver's header.
      $this->configOverrideClearSignal();
      $this->configOverrideClearBrowserDriverHeader();

      return;
    }

    $value = implode(',', $this->configOverrideDisabledNames);

    // A hook must not fail a scenario when the browser driver carries no
    // request headers, so the capability is checked for rather than required.
    if ($this->browserDriverHas(RequestHeaderCapabilityInterface::class)) {
      $this->browserDriverFor(RequestHeaderCapabilityInterface::class)->requestHeaderSet('X-Config-No-Override', $value);
    }

    $this->requestHeadersSet('X-Config-No-Override', $value);

    // A SUT invoked directly within the same process reads '$_SERVER'.
    $_SERVER['HTTP_X_CONFIG_NO_OVERRIDE'] = $value;

    // A SUT accessed through a Drush subprocess inherits the environment.
    putenv('HTTP_X_CONFIG_NO_OVERRIDE=' . $value);
  }

  /**
   * Clear the process-level and REST-level X-Config-No-Override signal.
   */
  protected function configOverrideClearSignal(): void {
    unset($_SERVER['HTTP_X_CONFIG_NO_OVERRIDE']);
    putenv('HTTP_X_CONFIG_NO_OVERRIDE');

    $this->requestHeadersUnset('X-Config-No-Override');
  }

  /**
   * Clear the browser driver's X-Config-No-Override request header.
   */
  protected function configOverrideClearBrowserDriverHeader(): void {
    try {
      $has_capability = $this->browserDriverHas(RequestHeaderCapabilityInterface::class);
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
      return;
    }
    // @codeCoverageIgnoreEnd
    if ($has_capability) {
      $this->browserDriverFor(RequestHeaderCapabilityInterface::class)->requestHeaderSet('X-Config-No-Override', '');
    }
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function configOverrideConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Apply the `@disable-config-override:` tags of a scenario and restore the overrides afterwards.'),
    ];
  }

}
