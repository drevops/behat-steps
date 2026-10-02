<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\ServiceContainer;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the tagged backends with the backend registry.
 */
class BackendPass implements CompilerPassInterface {

  /**
   * Tag a backend service carries to be registered with the registry.
   */
  public const BACKEND_TAG = 'behat_steps.backend';

  /**
   * Registers backends.
   */
  public function process(ContainerBuilder $container): void {
    if (!$container->hasDefinition('behat_steps.backend_registry')) {
      return;
    }

    $registry_definition = $container->getDefinition('behat_steps.backend_registry');

    foreach ($container->findTaggedServiceIds(self::BACKEND_TAG) as $id => $attributes) {
      foreach ($attributes as $attribute) {
        if (isset($attribute['alias']) && $name = $attribute['alias']) {
          $registry_definition->addMethodCall('registerBackend', [$name, new Reference($id)]);
        }
      }

      // The Drupal backend takes a single Core, so only the first service
      // tagged 'behat_steps.core' is injected.
      if ($id !== 'behat_steps.backend.drupal') {
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
   * Collects the names the tagged backends are registered under.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   *
   * @return array<int, string>
   *   The lowercased registered backend names, in registration order.
   */
  public static function registeredNames(ContainerBuilder $container): array {
    $names = [];

    foreach ($container->findTaggedServiceIds(self::BACKEND_TAG) as $attributes) {
      foreach ($attributes as $attribute) {
        if (isset($attribute['alias']) && $attribute['alias']) {
          $names[] = strtolower((string) $attribute['alias']);
        }
      }
    }

    return array_values(array_unique($names));
  }

}
