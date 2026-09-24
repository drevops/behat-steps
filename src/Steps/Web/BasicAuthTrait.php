<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use DrevOps\BehatSteps\Attribute\Steps;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthInterface;

/**
 * Keep HTTP basic authentication applied across session resets.
 *
 * - Re-apply the configured credentials before every scenario and step.
 *
 * Mink resets the session before every scenario and on every fast logout,
 * which clears request headers and drops the credentials. A site behind
 * webserver-level basic auth would start answering 401 mid-scenario without
 * this. The hooks are a no-op when no credentials are configured.
 *
 * Skip with tag: `@behat-steps-skip:BasicAuthTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
#[Steps]
trait BasicAuthTrait {

  /**
   * Whether the scenario opted out of re-applying the credentials.
   */
  protected bool $basicAuthSkip = FALSE;

  /**
   * Apply basic auth after Mink has reset the session for the scenario.
   */
  #[BeforeScenario]
  public function basicAuthBeforeScenario(BeforeScenarioScope $scope): void {
    $this->basicAuthSkip = $this->skipTag('BasicAuthTrait', $scope) || $this->skipTag(__FUNCTION__, $scope);

    if ($this->basicAuthSkip) {
      return;
    }

    $this->basicAuthApply();
  }

  /**
   * Re-apply basic auth in case a step reset the session.
   */
  #[BeforeStep]
  public function basicAuthBeforeStep(BeforeStepScope $scope): void {
    if ($this->basicAuthSkip) {
      return;
    }

    $this->basicAuthApply();
  }

  /**
   * Apply the resolved credentials to the session.
   */
  public function basicAuthApply(): void {
    $manager = $this->getAuthenticationManager();

    if ($manager instanceof BasicAuthInterface) {
      $manager->applyBasicAuth();
    }
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function basicAuthConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Apply the configured basic authentication credentials to every request.',
      ],
    ];
  }

}
