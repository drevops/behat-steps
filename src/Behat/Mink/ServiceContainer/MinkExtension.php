<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\ServiceContainer;

use Behat\MinkExtension\ServiceContainer\Driver\DriverFactory;
use Behat\MinkExtension\ServiceContainer\MinkExtension as UpstreamMinkExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Mink extension that drives 'browserkit_http' through Drupal's test browser.
 *
 * Mink builds the driver from a factory keyed by driver name, and the client
 * that factory wires in is not configurable, so a suite cannot ask for
 * 'browserkit_http' over Drupal's Guzzle-backed browser through configuration
 * alone. Registering a replacement factory under the same name is what makes
 * that available, and it happens here so the swap is in place before Mink's
 * configuration tree is built.
 *
 * Mink's extension is wrapped rather than extended: it is declared 'final' from
 * Mink 3, the release that carries Behat 4 support, so a subclass cannot load
 * at all there.
 *
 * @see \DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory
 */
class MinkExtension implements ExtensionInterface {

  /**
   * Container parameter carrying an 'ajax_timeout' read from this tree.
   */
  public const DEPRECATED_AJAX_TIMEOUT_PARAMETER = 'behat_steps.mink_ajax_timeout';

  /**
   * The wrapped Mink extension every call is delegated to.
   */
  protected UpstreamMinkExtension $inner;

  public function __construct() {
    $this->inner = new UpstreamMinkExtension();

    $this->inner->registerDriverFactory(new BrowserKitFactory());
  }

  /**
   * Registers a driver factory with the wrapped extension.
   *
   * Other extensions add their driver this way after resolving the extension
   * registered under the 'mink' key, so the method has to stay reachable here.
   *
   * @param \Behat\MinkExtension\ServiceContainer\Driver\DriverFactory $driver_factory
   *   The factory to register.
   */
  public function registerDriverFactory(DriverFactory $driver_factory): void {
    $this->inner->registerDriverFactory($driver_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigKey(): string {
    return $this->inner->getConfigKey();
  }

  /**
   * {@inheritdoc}
   */
  public function initialize(ExtensionManager $extensionManager): void {
    $this->inner->initialize($extensionManager);
  }

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {
    $this->inner->configure($builder);

    // No default, so an absent key stays absent from the processed config and
    // the value can be told apart from one this tree supplied.
    // @formatter:off
    // phpcs:disable
    $builder
      ->children()
        ->integerNode('ajax_timeout')
          ->min(0)
          ->info('Maximum time (in seconds) to wait for AJAX calls to complete.')
          ->setDeprecated('drevops/behat-steps', '4.0.0', 'Setting "%node%" at path "%path%" is deprecated. Set "ajax_timeout" on the "BehatStepsExtension" configuration instead.')
        ->end()
      ->end();
    // phpcs:enable
    // @formatter:on
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container to load services and parameters into.
   * @param array<string, mixed> $config
   *   The processed configuration.
   */
  public function load(ContainerBuilder $container, array $config): void {
    $this->inner->load($container, $config);

    if (!isset($config['ajax_timeout'])) {
      return;
    }

    $container->setParameter(self::DEPRECATED_AJAX_TIMEOUT_PARAMETER, $config['ajax_timeout']);
  }

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $this->inner->process($container);
  }

}
