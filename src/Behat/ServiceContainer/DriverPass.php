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
   * Tag a driver service carries to be registered with the manager.
   */
  public const DRIVER_TAG = 'behat_steps.driver';

  /**
   * Registers drivers.
   */
  public function process(ContainerBuilder $container): void {
    if (!$container->hasDefinition('behat_steps.driver_manager')) {
      return;
    }

    $manager_definition = $container->getDefinition('behat_steps.driver_manager');

    foreach ($container->findTaggedServiceIds(self::DRIVER_TAG) as $id => $attributes) {
      foreach ($attributes as $attribute) {
        if (isset($attribute['alias']) && $name = $attribute['alias']) {
          $manager_definition->addMethodCall('registerDriver', [$name, new Reference($id)]);
        }
      }

      // The Drupal driver takes a single Core, so only the first service
      // tagged 'behat_steps.core' is injected.
      if ($id !== 'behat_steps.driver.drupal') {
        continue;
      }

      $core_ids = array_keys($container->findTaggedServiceIds('behat_steps.core'));

      if ($core_ids === []) {
        continue;
      }

      $container->getDefinition($id)->addMethodCall('setCore', [new Reference($core_ids[0])]);
    }
  }

  /**
   * Collects the names the tagged drivers are registered under.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   *
   * @return array<int, string>
   *   The lowercased registered driver names, in registration order.
   */
  public static function registeredNames(ContainerBuilder $container): array {
    $names = [];

    foreach ($container->findTaggedServiceIds(self::DRIVER_TAG) as $attributes) {
      foreach ($attributes as $attribute) {
        if (isset($attribute['alias']) && $attribute['alias']) {
          $names[] = strtolower((string) $attribute['alias']);
        }
      }
    }

    return array_values(array_unique($names));
  }

}
