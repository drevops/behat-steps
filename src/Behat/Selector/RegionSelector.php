<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Selector;

use Behat\Mink\Selector\CssSelector;
use Behat\Mink\Selector\SelectorInterface;

/**
 * Mink selector that resolves a human-readable region name to XPath.
 *
 * Registered in Mink's selector handler under the alias 'region', so any
 * context with a Mink session can call '$page->find("region", "Header")'.
 */
class RegionSelector implements SelectorInterface {

  /**
   * Constructs a RegionSelector.
   *
   * @param \Behat\Mink\Selector\CssSelector $cssSelector
   *   The CSS selector that performs the actual CSS-to-XPath translation.
   * @param array<string, string> $regions
   *   Map of region names to CSS selectors, sourced from the extension's
   *   'regions' configuration.
   */
  public function __construct(
    protected readonly CssSelector $cssSelector,
    protected array $regions,
  ) {
  }

  /**
   * Translates a region name into XPath.
   *
   * @param string|array<int|string, mixed> $locator
   *   The region name to translate.
   *
   * @return string
   *   The XPath for the region.
   *
   * @throws \InvalidArgumentException
   *   When the name matches no configured region.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
  public function translateToXPath($locator): string {
    if (!is_string($locator) || !isset($this->regions[$locator])) {
      throw new \InvalidArgumentException(sprintf('The "%s" region isn\'t configured!', is_string($locator) ? $locator : gettype($locator)));
    }

    return $this->cssSelector->translateToXPath($this->regions[$locator]);
  }

}
