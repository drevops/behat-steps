<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use DrevOps\BehatSteps\Attribute\Steps;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use Drupal\testmode\Testmode;

/**
 * Configure Drupal Testmode module for controlled testing scenarios.
 *
 * Skip processing with tags: `@behat-steps-skip:testmodeBeforeScenario` and
 * `@behat-steps-skip:testmodeAfterScenario`.
 *
 * Special tags:
 * - `@testmode` - enable for scenario
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 * @phpstan-require-implements \DrevOps\BehatSteps\Behat\Context\DrupalApiInterface
 */
#[Steps]
trait TestmodeTrait {

  /**
   * Enable test mode before a scenario tagged with @testmode.
   */
  #[BeforeScenario]
  public function testmodeBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__FUNCTION__, $scope) || !Tag::has($scope->getScenario(), 'testmode')) {
      return;
    }

    $this->driverFor(CoreCapabilityInterface::class);

    $this->assertModuleEnabled('testmode', 'drupal/testmode');

    static::testmodeEnableTestMode();
  }

  /**
   * Disable test mode after a scenario tagged with @testmode.
   */
  #[AfterScenario]
  public function testmodeAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__FUNCTION__, $scope) || !Tag::has($scope->getScenario(), 'testmode')) {
      return;
    }

    $this->driverFor(CoreCapabilityInterface::class);

    $this->assertModuleEnabled('testmode', 'drupal/testmode');

    static::testmodeDisableTestMode();
  }

  /**
   * Enable test mode.
   */
  public static function testmodeEnableTestMode(): void {
    Testmode::getInstance()->enableTestMode();
  }

  /**
   * Disable test mode.
   */
  public static function testmodeDisableTestMode(): void {
    Testmode::getInstance()->disableTestMode();
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function testmodeConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Enable the Testmode module for a `@testmode` scenario and disable it afterwards.',
      ],
    ];
  }

}
