<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use Behat\MinkExtension\ServiceContainer\Driver\DriverFactory;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Extension under the 'mink' key that is not Mink's own.
 *
 * It accepts driver factories the way Mink's extension does and records each
 * one.
 */
class ForeignMinkExtension implements Extension {

  /**
   * The driver factories registered with this extension, in order.
   *
   * @var array<int, \Behat\MinkExtension\ServiceContainer\Driver\DriverFactory>
   */
  public array $driverFactories = [];

  /**
   * Records a driver factory.
   */
  public function registerDriverFactory(DriverFactory $driver_factory): void {
    $this->driverFactories[] = $driver_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigKey(): string {
    return 'mink';
  }

  /**
   * {@inheritdoc}
   */
  public function initialize(ExtensionManager $extensionManager): void {}

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {}

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container to load services and parameters into.
   * @param array<string, mixed> $config
   *   The processed configuration.
   */
  public function load(ContainerBuilder $container, array $config): void {}

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {}

}
