<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\ServiceContainer;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the tagged drivers with the driver manager.
 */
class DriverPass implements CompilerPassInterface {

  /**
   * Registers drivers.
   */
  public function process(ContainerBuilder $container): void {
    if (!$container->hasDefinition('behat_steps.driver_manager')) {
      return;
    }

    $manager_definition = $container->getDefinition('behat_steps.driver_manager');

    foreach ($container->findTaggedServiceIds('behat_steps.driver') as $id => $attributes) {
      foreach ($attributes as $attribute) {
        if (isset($attribute['alias']) && $name = $attribute['alias']) {
          $manager_definition->addMethodCall('registerDriver', [$name, new Reference($id)]);
        }
      }

      // The Drupal driver takes a single Core via setCore(). Resolve the
      // first service tagged 'behat_steps.core' and inject it.
      if ($id !== 'behat_steps.driver.drupal') {
        continue;
      }

      $core_ids = array_keys($container->findTaggedServiceIds('behat_steps.core'));

      if ($core_ids === []) {
        continue;
      }

      $container->getDefinition($id)->addMethodCall('setCore', [new Reference($core_ids[0])]);
    }

    $manager_definition->addMethodCall('setDefaultDriverName', [$container->getParameter('behat_steps.default_driver')]);
  }

}
