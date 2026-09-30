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
 * - The request header reaches the SUT only on a driver providing
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
 * issues its own HTTP requests - `RestTrait` - carries it too. The bag is
 * per context, so that reaches `RestTrait` only where one context composes
 * both; the shipped `WebContext` and `DrupalContext` are separate objects.
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
   * Some steps reset the request headers set earlier in the scenario, so the
   * signal is applied again before each one.
   */
  #[BeforeStep]
  public function configOverrideBeforeStep(BeforeStepScope $scope): void {
    if ($this->configOverrideDisabledNames === []) {
      // Nothing to propagate. The process-level signal persists beyond the
      // scenario that set it, so it is cleared here along with the
      // driver-level header.
      $this->configOverrideClearSignal();
      $this->configOverrideClearDriverHeader();

      return;
    }

    $value = implode(',', $this->configOverrideDisabledNames);

    // A hook cannot fail a scenario over a driver that carries no request
    // headers, so the capability is asked for rather than required.
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
   *
   * Driver-level request headers are cleared separately in the BeforeStep
   * hook because the session is not guaranteed to be started at the point
   * BeforeScenario runs.
   */
  protected function configOverrideClearSignal(): void {
    unset($_SERVER['HTTP_X_CONFIG_NO_OVERRIDE']);
    putenv('HTTP_X_CONFIG_NO_OVERRIDE');

    $this->requestHeadersUnset('X-Config-No-Override');
  }

  /**
   * Clear the driver-level X-Config-No-Override request header.
   */
  protected function configOverrideClearDriverHeader(): void {
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
