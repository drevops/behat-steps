@behatcli
Feature: Behat CLI context

  Tests for BehatCliContext functionality that is used to test Behat Steps traits
  by running Behat through CLI.

  - Assert that BehatCliContext context itself can be bootstrapped by Behat,
  including failed runs assertions.
  - Assert that RawContext can be autoloaded by Behat and that it can bootstrap
  a Drupal site.
  - Assert that DrupalSteps trait can be autoloaded by Behat

  Background:
    Given a file named "features/bootstrap/FeatureContext.php" with:
      """
      <?php
      use Behat\Step\Given;
      use DrevOps\BehatSteps\Behat\Context\RawContext;
      use DrevOps\BehatSteps\Steps\Generic\PathTrait;
      class FeatureContext extends RawContext {
        use PathTrait;

        #[Given('I throw test exception with message :message')]
        public function throwTestException($message) {
          throw new \RuntimeException($message);
        }
      }
      """
    And a file named "behat.php" with:
      """
      <?php
      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\Context\MinkContext;
      use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension;
      use DrevOps\BehatSteps\Behat\ServiceContainer\BehatExtension;

      $profile = (new Profile('default'))
        ->withSuite((new Suite('default'))->addContext('FeatureContext')->addContext(MinkContext::class))
        ->withExtension(new Extension(MinkExtension::class, ['base_url' => 'http://nginx:8080', 'sessions' => ['browserkit_http' => ['browserkit_http' => NULL], 'selenium2' => ['selenium2' => NULL]]]))
        ->withExtension(new Extension(BehatExtension::class, ['api_driver' => 'drupal', 'drupal' => ['drupal_root' => '/app/build/web']]));

      return (new Config())->withProfile($profile);
      """

  Scenario: Test passes
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        @api
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
          And the path should be "/"
      """

    When I run "behat --no-colors"
    Then it should pass with:
      """
      Feature: Homepage

        @api
        Scenario: Anonymous user visits homepage # features/drupal_bootstrap.feature:3
          Given I go to the homepage             # Behat\MinkExtension\Context\MinkContext::iAmOnHomepage()
          And the path should be "/"             # FeatureContext::pathAssertCurrent()

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
          And the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      Feature: Homepage

        @api
        Scenario: Anonymous user visits homepage # features/drupal_bootstrap.feature:3
          Given I go to the homepage             # Behat\MinkExtension\Context\MinkContext::iAmOnHomepage()
          And the path should be "/nonexisting"  # FeatureContext::pathAssertCurrent()
            Current path is "/", but expected is "/nonexisting". (Behat\Mink\Exception\ExpectationException)

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
          And the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      Feature: Homepage

        @api
        Scenario: Anonymous user visits homepage                       # features/drupal_bootstrap.feature:3
          Given I go to the homepage                                   # Behat\MinkExtension\Context\MinkContext::iAmOnHomepage()
          Then I throw test exception with message "Intentional error" # FeatureContext::throwTestException()
            Intentional error (RuntimeException)
          And the path should be "/nonexisting"                        # FeatureContext::pathAssertCurrent()

      --- Failed scenarios:

          features/drupal_bootstrap.feature:3

      1 scenario (1 failed)
      3 steps (1 passed, 1 failed, 1 skipped)
      """

  Scenario: Test nested PyStrings using triple single quotes
    Given some behat configuration
    And scenario steps:
      """
      Given a file named "test.txt" with:
        '''
        Line one of content
        Line two of content
        Line three of content
        '''
      """
    When I run "behat --no-colors"
    Then it should pass

  Scenario: A Drupal step outside an "@api" scenario names the tag it needs
    Given a file named "features/bootstrap/FeatureContext.php" with:
      """
      <?php
      use DrevOps\BehatSteps\Behat\Context\RawContext;
      use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
      class FeatureContext extends RawContext {
        use ContentTrait;
      }
      """
    And a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Content
        Scenario: An untagged scenario reaches for Drupal
          Given the content type "article" does not exist
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      The step requires Drupal's API. Tag the scenario "@api" so it runs on the in-process Drupal driver.
      """

  Scenario: Both extensions are registered by their namespace
    Given a file named "behat.php" with:
      """
      <?php
      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\Context\MinkContext;

      $profile = (new Profile('default'))
        ->withSuite((new Suite('default'))->addContext('FeatureContext')->addContext(MinkContext::class))
        ->withExtension(new Extension('DrevOps\BehatSteps\Behat\Mink', ['base_url' => 'http://nginx:8080', 'sessions' => ['browserkit_http' => ['browserkit_http' => NULL], 'selenium2' => ['selenium2' => NULL]]]))
        ->withExtension(new Extension('DrevOps\BehatSteps\Behat', ['api_driver' => 'drupal', 'drupal' => ['drupal_root' => '/app/build/web']]));

      return (new Config())->withProfile($profile);
      """
    And a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        @api
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
          And the path should be "/"
      """
    When I run "behat --no-colors"
    Then it should pass
