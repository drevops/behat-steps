<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Attribute;

/**
 * Attribute for methods to run before a node is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BeforeNodeCreate implements DrupalHookInterface {

  public function __construct(public ?string $filterString = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterString(): ?string {
    return $this->filterString;
  }

}
