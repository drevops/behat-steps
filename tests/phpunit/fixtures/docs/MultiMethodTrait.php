<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * A trait with multiple methods to test sorting.
 */
trait MultiMethodTrait {

  /**
   * Then step.
   *
   * @Then the result should be visible
   *
   * @code
   * Then the result should be visible
   * @endcode
   */
  public function multimethodAssertResultVisible(): void {}

  /**
   * Given step.
   *
   * @Given the following items:
   *
   * @code
   * Given the following items:
   * @endcode
   */
  public function multimethodGivenItems(): void {}

  /**
   * When step.
   *
   * @When I click on :button
   *
   * @code
   * When I click on "Submit"
   * @endcode
   */
  public function multimethodClickButton(): void {}

}
