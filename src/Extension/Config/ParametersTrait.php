<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Config;

/**
 * Provides access to the extension parameters.
 *
 * The Behat configuration holds these parameters under the extension's config
 * key. They define commonly customized aspects of the site under test,
 * such as CSS selectors, interface text or region maps.
 *
 * Any context reads parameters, text and selectors through this trait, whether
 * or not it extends 'WebRawContext'. A context implements
 * 'ParametersAwareInterface' and composes this trait.
 *
 * 'BackendAwareInitializer' then injects the parameter array through
 * 'setParameters()' before any scenario runs. No backend bootstrap is
 * required.
 *
 * @see \DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension
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
   *
   * @internal
   *   Injection point called by the context initializer.
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
   * Returns a specific text value.
   *
   * @param string $name
   *   Text value name, such as 'logout', which corresponds to the default
   *   'Log out' link text.
   *
   * @return string
   *   The text value.
   *
   * @throws \RuntimeException
   *   When the text is not present in the list of parameters.
   */
  public function getText(string $name): string {
    $text = $this->getParameter('text');

    if (!isset($text[$name])) {
      throw new \RuntimeException(sprintf('No such text: %s.', $name));
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
   *   When the selector is not present in the list of parameters.
   */
  public function getSelector(string $name): string {
    $selectors = $this->getParameter('selectors');

    if (!isset($selectors[$name])) {
      throw new \RuntimeException(sprintf('No such selector: %s.', $name));
    }

    return $selectors[$name];
  }

}
