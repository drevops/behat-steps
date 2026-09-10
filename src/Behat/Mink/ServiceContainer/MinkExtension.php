<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\ServiceContainer;

use Behat\MinkExtension\ServiceContainer\MinkExtension as UpstreamMinkExtension;
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
 * @see \DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory
 */
class MinkExtension extends UpstreamMinkExtension {

  /**
   * Container parameter carrying an 'ajax_timeout' read from this tree.
   */
  public const DEPRECATED_AJAX_TIMEOUT_PARAMETER = 'behat_steps.mink_ajax_timeout';

  public function __construct() {
    parent::__construct();

    $this->registerDriverFactory(new BrowserKitFactory());
  }

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {
    parent::configure($builder);

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
    parent::load($container, $config);

    if (!isset($config['ajax_timeout'])) {
      return;
    }

    $container->setParameter(self::DEPRECATED_AJAX_TIMEOUT_PARAMETER, $config['ajax_timeout']);
  }

}
