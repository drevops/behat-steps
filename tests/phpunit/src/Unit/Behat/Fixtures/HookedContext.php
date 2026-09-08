<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterNodeCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;

/**
 * Context carrying one method per case the attribute reader distinguishes.
 */
class HookedContext implements Context {

  /**
   * Static hook, which the reader can pass as a plain callable pair.
   */
  #[BeforeNodeCreate]
  public static function beforeNode(BeforeNodeCreateScope $scope): void {
  }

  /**
   * Instance hook, which is not callable in its pair form.
   */
  #[AfterNodeCreate]
  public function afterNode(AfterNodeCreateScope $scope): void {
  }

  /**
   * Hook declared with a filter string.
   */
  #[AfterEntityCreate('@api')]
  public static function filtered(): void {
  }

  /**
   * Method carrying two entity hooks at once.
   */
  #[BeforeNodeCreate]
  #[AfterNodeCreate]
  public static function both(): void {
  }

  /**
   * Method carrying a Behat hook the reader does not handle.
   */
  #[BeforeScenario]
  public static function unrelated(): void {
  }

  /**
   * Method carrying an entity hook the reader has no call class for.
   */
  #[UnmappedHook]
  public static function unmapped(): void {
  }

  /**
   * Method carrying no attribute at all.
   */
  public static function plain(): void {
  }

}
