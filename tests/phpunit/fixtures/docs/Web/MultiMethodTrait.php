<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * A trait with multiple methods to test sorting.
 */
trait MultiMethodTrait {

  /**
   * Then step.
   *
   * @code
   * Then the result should be visible
   * @endcode
   *
   * @actor:system
   */
  #[Then('the result should be visible')]
  public function multiMethodAssertResultVisible(): void {}

  /**
   * Given step.
   *
   * @code
   * Given the following items:
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following items:')]
  public function multiMethodGivenItems(): void {}

  /**
   * When step.
   *
   * @code
   * When I click on "Submit"
   * @endcode
   *
   * @actor:person
   */
  #[When('I click on :button')]
  public function multiMethodClickButton(): void {}

  /**
   * Assert that the content should contain the specified text.
   *
   * @code
   * Then the content should contain:
   * """
   * Expected content
   * """
   * @endcode
   *
   * @actor:system
   */
  #[Then('the content should contain:')]
  public function multiMethodAssertContentContains(): void {}

  /**
   * Assert that the items should have the following properties.
   *
   * @code
   * Given the following items exist:
   *   | name  | value |
   *   | one   | 1     |
   *   | two   | 2     |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following items exist:')]
  public function multiMethodGivenItemsExist(): void {}

}
