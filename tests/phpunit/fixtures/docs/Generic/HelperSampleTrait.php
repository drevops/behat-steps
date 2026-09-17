<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Generic;

use Behat\Hook\BeforeScenario;
use Behat\Step\Then;
use Behat\Transformation\Transform;

/**
 * Sample trait carrying helpers for testing.
 */
trait HelperSampleTrait {

  /**
   * Prepare the sample.
   */
  #[BeforeScenario]
  public function helperSampleBeforeScenario(): void {}

  /**
   * Transform the sample value.
   */
  #[Transform(':value')]
  public function helperSampleTransformValue(string $value): string {
    return $value;
  }

  /**
   * Test method.
   *
   * @code
   * Then the sample should pass
   * @endcode
   */
  #[Then('the sample should pass')]
  public function helperSampleAssertTest(): void {}

  /**
   * Build a sample value.
   *
   * @code
   * $this->helperSampleBuild('one');
   * @endcode
   */
  public function helperSampleBuild(string $name, ?int $count = NULL, bool $strict = TRUE): string {
    return $name . (string) $count . (string) $strict;
  }

  /**
   * Read the sample defaults.
   *
   * @param array<int, string> $values
   *   The values to read.
   *
   * @return array<int, string>
   *   The values.
   */
  protected static function helperSampleDefaults(array $values = []): array {
    return $values;
  }

  /**
   * Reach the machinery.
   *
   * @internal
   *   Called by the initializer.
   */
  protected function helperSampleInternal(): void {}

  /**
   * Belong to another trait by name.
   */
  protected function otherPrefixedMethod(): void {}

}
