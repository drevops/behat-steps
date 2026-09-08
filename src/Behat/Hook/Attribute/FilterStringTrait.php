<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Attribute;

/**
 * Carries the filter string an entity hook attribute is declared with.
 */
trait FilterStringTrait {

  /**
   * Constructs the attribute.
   *
   * @param string|null $filterString
   *   The filter the hook is limited to, or NULL to run for every entity.
   */
  public function __construct(public ?string $filterString = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterString(): ?string {
    return $this->filterString;
  }

}
