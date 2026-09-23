<?php

/**
 * @file
 * Feature context for testing the web half of Behat-steps.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 */

declare(strict_types=1);

use DrevOps\BehatSteps\Behat\Context\WebContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends WebContext {

  use FeatureContextTrait;

  /**
   * Override dateNow() method to return a preset value for testing.
   *
   * The override sits on the class, not in FeatureContextTrait: the generated
   * trait-tag context composes that trait beside the trait under test, and two
   * traits declaring the same method collide.
   */
  public static function dateNow(): int {
    return strtotime('2024-07-15 12:00:00');
  }

  /**
   * Override elementGetScrollIntoViewCenter() to allow runtime toggling.
   *
   * The override sits on the class, not in FeatureContextTrait: the generated
   * trait-tag context composes that trait beside the trait under test, and two
   * traits declaring the same method collide.
   */
  protected function elementGetScrollIntoViewCenter(): bool {
    return $this->testElementScrollCenter;
  }

  /**
   * Override accessibilityGetReportDir() to anchor reports to the base path.
   *
   * Behat is launched from the build directory but configured with the
   * project-root behat.php, so the captured working directory is not the
   * base path. Deriving the base from the Mink files_path keeps accessibility
   * reports in the same .logs tree as the other Behat artifacts.
   *
   * The override sits on the class, not in FeatureContextTrait: the generated
   * trait-tag context composes that trait beside the trait under test, and two
   * traits declaring the same method collide.
   */
  public function accessibilityGetReportDir(): string {
    return dirname((string) $this->getMinkParameter('files_path'), 3) . '/.logs/test_results/accessibility';
  }

}
