<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Registry;

/**
 * Interface for classes that hold the tags the running scenario carries.
 */
interface ScenarioTagRegistryInterface {

  /**
   * Sets the tags of the scenario about to run.
   *
   * @param array<int, string> $tags
   *   Feature tags followed by scenario tags, each without a leading '@'.
   */
  public function setTags(array $tags): void;

  /**
   * Returns the tags of the running scenario.
   *
   * @return array<int, string>
   *   Feature tags followed by scenario tags, each without a leading '@'.
   *   Empty before the first scenario starts.
   */
  public function getTags(): array;

}
