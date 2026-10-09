<?php

/**
 * @file
 * Feature context for testing Behat-steps.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 */

declare(strict_types=1);

use DrevOps\BehatSteps\Behat\Context\DrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  use FeatureContextTrait;

  /**
   * Override dateGetNow() method to return a preset value for testing.
   *
   * The override is declared on the class, not in FeatureContextTrait. The
   * generated trait-tag context composes that trait beside the trait under
   * test, and 2 traits declaring the same method collide.
   */
  public static function dateGetNow(): int {
    return strtotime('2024-07-15 12:00:00');
  }

  /**
   * Override elementGetScrollIntoViewCenter() to allow runtime toggling.
   *
   * The override is declared on the class, not in FeatureContextTrait. The
   * generated trait-tag context composes that trait beside the trait under
   * test, and 2 traits declaring the same method collide.
   */
  public function elementGetScrollIntoViewCenter(): bool {
    return $this->testElementScrollCenter;
  }

  /**
   * Override accessibilityGetReportDir() to place reports under the base path.
   *
   * Behat is launched from the build directory but configured with the
   * project-root behat.php, so the captured working directory is not the
   * base path. Deriving the base from the Mink files_path keeps accessibility
   * reports in the same .logs tree as the other Behat artifacts.
   *
   * The override is declared on the class, not in FeatureContextTrait. The
   * generated trait-tag context composes that trait beside the trait under
   * test, and 2 traits declaring the same method collide.
   */
  public function accessibilityGetReportDir(): string {
    return dirname((string) $this->getMinkParameter('files_path'), 3) . '/.logs/test_results/accessibility';
  }

}
