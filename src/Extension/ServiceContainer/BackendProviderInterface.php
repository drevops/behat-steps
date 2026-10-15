<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\ServiceContainer;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Contributes the backends that reach 1 kind of site to the extension.
 *
 * The extension's own tree, services and passes name no site technology.
 *
 * A provider adds the settings node of each backend it ships, and the login
 * text and selector defaults for the sites those backends reach. It also adds
 * the service definitions of each backend whose settings are present, and the
 * wiring those definitions need once every service is loaded.
 */
interface BackendProviderInterface {

  /**
   * Adds the settings node of each backend the provider ships.
   *
   * @param \Symfony\Component\Config\Definition\Builder\NodeBuilder $children
   *   The children of the extension's configuration node.
   */
  public function configure(NodeBuilder $children): void;

  /**
   * Returns the defaults of the 'text' and 'selectors' settings.
   *
   * @return array{text: array<string, string>, selectors: array<string, string>}
   *   Values keyed by setting name.
   */
  public function getParameterDefaults(): array;

  /**
   * Loads the service definitions of each backend whose settings are present.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param array<string, mixed> $config
   *   The extension configuration.
   */
  public function load(ContainerBuilder $container, array $config): void;

  /**
   * Completes the backend definitions once every service is loaded.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   */
  public function process(ContainerBuilder $container): void;

}
