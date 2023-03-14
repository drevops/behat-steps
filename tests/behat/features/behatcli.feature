@d7 @d9 @d10 @behatcli
Feature: Behat CLI context

  Tests for BehatCliContext functionality that is used to test Behat Steps traits
  by running Behat through CLI.

  - Assert that BehatCliContext context itself can be bootstrapped by Behat,
  including failed runs assertions.
  - Assert that DrupalContext can be autoloaded by Behat and that DrupalContext
  can bootstrap Drupal site.
  - Assert that DrupalSteps trait can be autoloaded by Behat

  Background:
    Given a file named "features/bootstrap/FeatureContext.php" with:
      """
      <?php
      use Drupal\DrupalExtension\Context\DrupalContext;
      use DrevOps\BehatSteps\PathTrait;
      class FeatureContext extends DrupalContext {
        use PathTrait;

        /**
         * @Given I throw test exception with message :message
         */
        public function throwTestException($message) {
          throw new \RuntimeException($message);
        }
      }
      """
    And a file named "behat.yml" with:
      """
      default:
        suites:
          default:
            contexts:
              - FeatureContext
              - Drupal\DrupalExtension\Context\MinkContext
        extensions:
          Drupal\MinkExtension:
            goutte: ~
            selenium2: ~
            base_url: http://nginx:8080
          Drupal\DrupalExtension:
            api_driver: drupal
            drupal:
              drupal_root: /app/build/web
      """

  Scenario: Test passes
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        @api
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
          And I should be in the "<front>" path
      """

    When I run "behat --no-colors"
    Then it should pass with:
      """
      Feature: Homepage

        @api
        Scenario: Anonymous user visits homepage # features/drupal_bootstrap.feature:3
          Given I go to the homepage             # Drupal\DrupalExtension\Context\MinkContext::iAmOnHomepage()
          And I should be in the "<front>" path  # FeatureContext::pathAssertCurrent()

      1 scenario (1 passed)
      2 steps (2 passed)
      """

  Scenario: Test fails
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        @api
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
          And I should be in the "nonexisting" path
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      Feature: Homepage

        @api
        Scenario: Anonymous user visits homepage    # features/drupal_bootstrap.feature:3
          Given I go to the homepage                # Drupal\DrupalExtension\Context\MinkContext::iAmOnHomepage()
          And I should be in the "nonexisting" path # FeatureContext::pathAssertCurrent()
            Current path is "<front>", but expected is "nonexisting" (Exception)

      --- Failed scenarios:

          features/drupal_bootstrap.feature:3

      1 scenario (1 failed)
      2 steps (1 passed, 1 failed)
      """

  Scenario: Test fails with exception
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        @api
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
          Then I throw test exception with message "Intentional error"
          And I should be in the "nonexisting" path
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      Feature: Homepage

        @api
        Scenario: Anonymous user visits homepage                       # features/drupal_bootstrap.feature:3
          Given I go to the homepage                                   # Drupal\DrupalExtension\Context\MinkContext::iAmOnHomepage()
          Then I throw test exception with message "Intentional error" # FeatureContext::throwTestException()
            Intentional error (RuntimeException)
          And I should be in the "nonexisting" path                    # FeatureContext::pathAssertCurrent()

      --- Failed scenarios:

          features/drupal_bootstrap.feature:3

      1 scenario (1 failed)
      3 steps (1 passed, 1 failed, 1 skipped)
      """
