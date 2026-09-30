<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Config;

/**
 * Interface for classes that build a context's option resolver.
 *
 * A resolver depends on the context class that declared the options and on the
 * 'config' argument that context was given, so it cannot be a container
 * service. Registering another implementation of this interface under
 * 'behat_steps.config.resolver_factory' replaces option resolution for every
 * context at once.
 */
interface TraitOptionResolverFactoryInterface {

  /**
   * Builds the resolver of one context.
   *
   * @param string $context_class
   *   The context whose traits declare the options.
   * @param array<array-key, mixed> $config
   *   The context's 'config' argument, read strictly.
   * @param array<array-key, mixed> $steps
   *   The extension's 'steps' section, read permissively.
   *
   * @return \DrevOps\BehatSteps\Behat\Config\TraitOptionResolverInterface
   *   The resolver.
   *
   * @throws \RuntimeException
   *   When a trait of the context declares its options malformed.
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the 'config' argument names something no trait declares, or gives a
   *   value of the wrong type.
   */
  public function create(string $context_class, array $config, array $steps): TraitOptionResolverInterface;

}
