<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

/**
 * Holds the tags the running scenario carries.
 *
 * @see \DrevOps\BehatSteps\Behat\Listener\BackendListener
 */
class ScenarioTagRegistry implements ScenarioTagRegistryInterface {

  /**
   * Tags of the running scenario, each without a leading '@'.
   *
   * @var array<int, string>
   */
  protected array $tags = [];

  /**
   * {@inheritdoc}
   */
  public function setTags(array $tags): void {
    $this->tags = array_values($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return $this->tags;
  }

}
