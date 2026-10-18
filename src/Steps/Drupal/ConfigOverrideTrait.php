<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use DrevOps\BehatSteps\Backend\Capability\RequestEnvironmentCapabilityInterface;
use DrevOps\BehatSteps\Extension\Browser\Capability\RequestHeaderCapabilityInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Tag;
use DrevOps\BehatSteps\Steps\Helper\Web\RequestHeadersTrait;

/**
 * Disable Drupal config overrides from settings.php during a scenario.
 *
 * Config overrides set in `settings.php` replace the stored configuration at
 * runtime. They cannot be disabled from the Behat process because tests run
 * in a separate process from the system under test (SUT).
 *
 * This trait signals the SUT that specific config objects should be read
 * from their original (unoverridden) values. The signal is the
 * `X-Config-No-Override` request header. The browser sends it, and every
 * backend providing `RequestEnvironmentCapabilityInterface` passes it to the
 * SUT code it runs. The in-process backend passes it as the
 * `HTTP_X_CONFIG_NO_OVERRIDE` `$_SERVER` entry, and the Drush backend as the
 * environment variable of the same name for each command.
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
 *   headers, so a scenario running on Selenium reaches only the SUT code the
 *   backends run.
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
 * same context composes both traits.
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
 * @title Config overrides
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
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

    $this->configOverrideSetSignal($value);
  }

  /**
   * Set the X-Config-No-Override signal on the requests and the backends.
   *
   * @param string $value
   *   The config names, as a comma-separated list.
   */
  protected function configOverrideSetSignal(string $value): void {
    $this->requestHeadersSet('X-Config-No-Override', $value);

    foreach ($this->configOverrideGetBackends() as $backend) {
      $backend->requestHeaderSet('X-Config-No-Override', $value);
    }
  }

  /**
   * Clear the X-Config-No-Override signal from the requests and the backends.
   */
  protected function configOverrideClearSignal(): void {
    $this->requestHeadersUnset('X-Config-No-Override');

    foreach ($this->configOverrideGetBackends() as $backend) {
      $backend->requestHeaderUnset('X-Config-No-Override');
    }
  }

  /**
   * Get every backend of the scenario that passes a request header on.
   *
   * None is bootstrapped, so a scenario that reaches no backend starts none,
   * and a backend a later step reaches already holds the signal.
   *
   * @return array<int, \DrevOps\BehatSteps\Backend\Capability\RequestEnvironmentCapabilityInterface>
   *   The backends, in the scenario's order.
   */
  protected function configOverrideGetBackends(): array {
    $registry = $this->getBackendRegistry();
    $registered = $registry->getBackends();
    $backends = [];

    foreach (array_unique($registry->getScenarioBackends()) as $name) {
      $backend = $registered[$name] ?? NULL;

      if ($backend instanceof RequestEnvironmentCapabilityInterface) {
        $backends[] = $backend;
      }
    }

    return $backends;
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
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function configOverrideConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Apply the `@disable-config-override:` tags of a scenario and restore the overrides afterwards.'),
    ];
  }

}
