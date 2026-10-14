<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\RequestEnvironmentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\ConfigOverrideTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests that the config override signal reaches the backends of a scenario.
 */
#[CoversTrait(ConfigOverrideTrait::class)]
class ConfigOverrideTraitTest extends UnitTestCase {

  public function testBeforeStepSetsTheSignalOnEveryBackendThatPassesHeadersOn(): void {
    $drupal = new RecordingRequestEnvironmentBackend();
    $drush = new RecordingRequestEnvironmentBackend();
    $context = $this->createContext(['drupal' => $drupal, 'drush' => $drush, 'blackbox' => $this->createStub(BackendInterface::class)]);

    $context->configOverrideBeforeScenario($this->createBeforeScenarioScope(['disable-config-override:system.site', 'disable-config-override:user.settings']));
    $context->configOverrideBeforeStep($this->createBeforeStepScope());

    $this->assertSame(['X-Config-No-Override' => 'system.site,user.settings'], $drupal->headers);
    $this->assertSame(['X-Config-No-Override' => 'system.site,user.settings'], $drush->headers);
    $this->assertSame('system.site,user.settings', $context->testGetRequestHeaders()['X-Config-No-Override'] ?? NULL, 'The trait\'s own requests carry the header too.');
    $this->assertFalse($drupal->isBootstrapped() || $drush->isBootstrapped(), 'Handing the signal over bootstraps no backend.');
  }

  public function testBeforeScenarioClearsTheSignalOfThePreviousScenario(): void {
    $drupal = new RecordingRequestEnvironmentBackend();
    $context = $this->createContext(['drupal' => $drupal]);
    $context->configOverrideBeforeScenario($this->createBeforeScenarioScope(['disable-config-override:system.site']));
    $context->configOverrideBeforeStep($this->createBeforeStepScope());

    $context->configOverrideBeforeScenario($this->createBeforeScenarioScope());

    $this->assertSame([], $drupal->headers);
    $this->assertArrayNotHasKey('X-Config-No-Override', $context->testGetRequestHeaders());
  }

  public function testBeforeStepClearsTheSignalWithoutTags(): void {
    $drupal = new RecordingRequestEnvironmentBackend();
    $context = $this->createContext(['drupal' => $drupal]);
    $context->configOverrideBeforeScenario($this->createBeforeScenarioScope());
    $drupal->headers = ['X-Config-No-Override' => 'system.site'];

    $context->configOverrideBeforeStep($this->createBeforeStepScope());

    $this->assertSame([], $drupal->headers);
  }

  public function testSkipTagKeepsTheSignalOff(): void {
    $drupal = new RecordingRequestEnvironmentBackend();
    $context = $this->createContext(['drupal' => $drupal]);

    $context->configOverrideBeforeScenario($this->createBeforeScenarioScope(['disable-config-override:system.site', 'behat-steps-skip:ConfigOverrideTrait']));
    $context->configOverrideBeforeStep($this->createBeforeStepScope());

    $this->assertSame([], $drupal->headers);
  }

  public function testBackendOutsideTheScenarioOrderIsLeftAlone(): void {
    $listed = new RecordingRequestEnvironmentBackend();
    $unlisted = new RecordingRequestEnvironmentBackend();
    $context = $this->createContext(['drupal' => $listed, 'drush' => $unlisted], ['drupal' => 'drupal']);

    $context->configOverrideBeforeScenario($this->createBeforeScenarioScope(['disable-config-override:system.site']));
    $context->configOverrideBeforeStep($this->createBeforeStepScope());

    $this->assertSame(['X-Config-No-Override' => 'system.site'], $listed->headers);
    $this->assertSame([], $unlisted->headers);
  }

  /**
   * Creates a context over the backends, on a browser driver with no headers.
   *
   * @param array<string, \DrevOps\BehatSteps\Backend\BackendInterface> $backends
   *   The backends to register, keyed by name.
   * @param array<string, string>|null $order
   *   The scenario's backend order, or NULL for every backend registered.
   */
  protected function createContext(array $backends, ?array $order = NULL): ConfigOverrideTraitTestImplementation {
    $registry = new BackendRegistry($backends);
    $names = array_keys($backends);
    $registry->setScenarioBackends($order ?? array_combine($names, $names));

    $session = $this->createStub(Session::class);
    $session->method('getDriver')->willReturn($this->createStub(DriverInterface::class));
    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $context = new ConfigOverrideTraitTestImplementation();
    $context->setBackendRegistry($registry);
    $context->setMink($mink);

    return $context;
  }

  /**
   * Creates the scope of a BeforeStep hook.
   */
  protected function createBeforeStepScope(): BeforeStepScope {
    [$feature] = $this->createScenarioNodes([], []);

    return new BeforeStepScope($this->createStub(Environment::class), $feature, new StepNode('Given', 'a step', [], 1));
  }

}

/**
 * Test implementation of ConfigOverrideTrait.
 */
class ConfigOverrideTraitTestImplementation extends WebRawContext {

  use ConfigOverrideTrait;

  /**
   * Returns the headers the trait's own requests carry.
   *
   * @return array<string, string>
   *   The headers, keyed by name.
   */
  public function testGetRequestHeaders(): array {
    return $this->requestHeadersAll();
  }

}

/**
 * Backend that records the request headers handed to it.
 */
class RecordingRequestEnvironmentBackend implements BackendInterface, RequestEnvironmentCapabilityInterface {

  /**
   * The request headers set, keyed by name.
   *
   * @var array<string, string>
   */
  public array $headers = [];

  /**
   * Whether the backend has been bootstrapped.
   */
  protected bool $bootstrapped = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getRandom(): RandomGeneratorInterface {
    return new RandomGenerator();
  }

  /**
   * {@inheritdoc}
   */
  public function bootstrap(): void {
    $this->bootstrapped = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isBootstrapped(): bool {
    return $this->bootstrapped;
  }

  /**
   * {@inheritdoc}
   */
  public function requestHeaderSet(string $name, string $value): void {
    $this->headers[$name] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function requestHeaderUnset(string $name): void {
    unset($this->headers[$name]);
  }

}
