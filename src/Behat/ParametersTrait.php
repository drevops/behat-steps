<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat;

/**
 * Provides access to the extension parameters.
 *
 * These parameters are placed in behat.yml under the extension's config key
 * and define commonly customized aspects of the Drupal installation such as
 * CSS selectors, interface text or region maps.
 *
 * This is the consumption point for parameter, text, and selector access from
 * any context, regardless of whether it inherits from 'RawContext'. A context
 * only needs to implement 'ParametersAwareInterface' and 'use' this trait;
 * 'DriverAwareInitializer' injects the parameter array via 'setParameters()'
 * before any scenario runs. No driver bootstrap is required.
 *
 * @see \DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension
 */
trait ParametersTrait {

  /**
   * Extension parameters.
   *
   * @var array<string, mixed>
   */
  protected array $parameters = [];

  /**
   * Sets parameters provided by the extension.
   *
   * @param array<string, mixed> $parameters
   *   The parameters to set.
   */
  public function setParameters(array $parameters): void {
    $this->parameters = $parameters;
  }

  /**
   * Returns a specific extension parameter.
   *
   * @param string $name
   *   Parameter name.
   *
   * @return mixed
   *   The value, or NULL if the parameter does not exist.
   */
  public function getParameter(string $name): mixed {
    return $this->parameters[$name] ?? NULL;
  }

  /**
   * Returns a specific Drupal text value.
   *
   * @param string $name
   *   Text value name, such as 'log_out', which corresponds to the default
   *   'Log out' link text.
   *
   * @return string
   *   The text value.
   *
   * @throws \RuntimeException
   *   Thrown when the text is not present in the list of parameters.
   */
  public function getDrupalText(string $name): string {
    $text = $this->getParameter('text');
    if (!isset($text[$name])) {
      throw new \RuntimeException(sprintf('No such Drupal string: %s', $name));
    }

    return $text[$name];
  }

  /**
   * Returns a specific CSS selector.
   *
   * @param string $name
   *   The name of the CSS selector.
   *
   * @return string
   *   The CSS selector.
   *
   * @throws \RuntimeException
   *   Thrown when the selector is not present in the list of parameters.
   */
  public function getDrupalSelector(string $name): string {
    $selectors = $this->getParameter('selectors');
    if (!isset($selectors[$name])) {
      throw new \RuntimeException(sprintf('No such selector configured: %s', $name));
    }

    return $selectors[$name];
  }

  /**
   * Returns a mapped value by its key.
   *
   * Keys are unique across every configured 'mappings' group, so the group
   * a key lives in is irrelevant to the lookup.
   *
   * @param string $name
   *   The mapping key.
   *
   * @return string
   *   The mapped value.
   *
   * @throws \RuntimeException
   *   Thrown when the key is not present in the configured mappings.
   */
  public function getMapping(string $name): string {
    $mappings = $this->getParameter('mappings');
    if (!isset($mappings[$name])) {
      throw new \RuntimeException(sprintf('No such mapping: %s', $name));
    }

    return $mappings[$name];
  }

}
