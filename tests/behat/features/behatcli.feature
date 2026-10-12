@behatcli
Feature: Behat CLI context

  Tests for BehatCliContext functionality that is used to test Behat Steps traits
  by running Behat through CLI.

  - Assert that BehatCliContext context itself can be bootstrapped by Behat,
  including failed runs assertions.
  - Assert that WebRawContext can be autoloaded by Behat and that it can bootstrap
  a Drupal site.
  - Assert that DrupalSteps trait can be autoloaded by Behat

  Background:
    Given a file named "features/bootstrap/FeatureContext.php" with:
      """
      <?php
      use Behat\Step\Given;
      use DrevOps\BehatSteps\Behat\Context\WebRawContext;
      use DrevOps\BehatSteps\Steps\Web\PathTrait;
      class FeatureContext extends WebRawContext {
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
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

      $profile = (new Profile('default'))
        ->withSuite((new Suite('default'))->addContext('FeatureContext')->addContext(MinkContext::class))
        ->withExtension(new Extension(MinkExtension::class, ['base_url' => 'http://nginx:8080', 'sessions' => ['browserkit_http' => ['browserkit_http' => NULL], 'selenium2' => ['selenium2' => NULL]]]))
        ->withExtension(new Extension(BehatStepsExtension::class, ['backends' => ['drupal', 'blackbox'], 'drupal' => ['drupal_root' => '/app/build/web']]));

      return (new Config())->withProfile($profile);
      """

  Scenario: Test passes
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
          And the path should be "/"
      """

    When I run "behat --no-colors"
    Then it should pass with:
      """
      Feature: Homepage

        Scenario: Anonymous user visits homepage # features/drupal_bootstrap.feature:2
          Given I go to the homepage             # Behat\MinkExtension\Context\MinkContext::iAmOnHomepage()
          And the path should be "/"             # FeatureContext::pathAssertCurrent()

      1 scenario (1 passed)
      2 steps (2 passed)
      """

  Scenario: Test fails
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
          And the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      Feature: Homepage

        Scenario: Anonymous user visits homepage # features/drupal_bootstrap.feature:2
          Given I go to the homepage             # Behat\MinkExtension\Context\MinkContext::iAmOnHomepage()
          And the path should be "/nonexisting"  # FeatureContext::pathAssertCurrent()
            The current path is "/", but it should be "/nonexisting". (Behat\Mink\Exception\ExpectationException)

      --- Failed scenarios:

          features/drupal_bootstrap.feature:2

      1 scenario (1 failed)
      2 steps (1 passed, 1 failed)
      """

  Scenario: Test fails with exception
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
          Then I throw test exception with message "Intentional error"
          And the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      Feature: Homepage

        Scenario: Anonymous user visits homepage                       # features/drupal_bootstrap.feature:2
          Given I go to the homepage                                   # Behat\MinkExtension\Context\MinkContext::iAmOnHomepage()
          Then I throw test exception with message "Intentional error" # FeatureContext::throwTestException()
            Intentional error (RuntimeException)
          And the path should be "/nonexisting"                        # FeatureContext::pathAssertCurrent()

      --- Failed scenarios:

          features/drupal_bootstrap.feature:2

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

  Scenario: A Drupal step in a configuration listing no Drupal backend names the capability
    Given a file named "features/bootstrap/FeatureContext.php" with:
      """
      <?php
      use DrevOps\BehatSteps\Behat\Context\WebRawContext;
      use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
      class FeatureContext extends WebRawContext {
        use ContentTrait;
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
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

      $profile = (new Profile('default'))
        ->withSuite((new Suite('default'))->addContext('FeatureContext')->addContext(MinkContext::class))
        ->withExtension(new Extension(MinkExtension::class, ['base_url' => 'http://nginx:8080', 'sessions' => ['browserkit_http' => ['browserkit_http' => NULL], 'selenium2' => ['selenium2' => NULL]]]))
        ->withExtension(new Extension(BehatStepsExtension::class, ['backends' => ['blackbox'], 'drupal' => ['drupal_root' => '/app/build/web']]));

      return (new Config())->withProfile($profile);
      """
    And a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Content
        Scenario: A scenario in a blackbox-only configuration reaches for Drupal
          Given the content type "article" does not exist
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      No backend provides "DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface". Backends available to this scenario, in order: blackbox.
      """

  Scenario: A "@backend" tag naming a backend the configuration does not hold fails at scenario start
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Content
        @backend:typo
        Scenario: A scenario promotes a backend that does not exist
          Given I go to the homepage
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      The "@backend:typo" tag names a backend that the configured backend list does not hold. Configured backends: drupal, blackbox. The tag reorders that list; it never adds to it.
      """

  Scenario: A "@driver" tag fails at scenario start naming the "@backend" tag
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Content
        @driver:drupal
        Scenario: A scenario promotes a backend with a driver tag
          Given I go to the homepage
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      The "@driver:drupal" tag moved to "@backend:drupal". Rename the tag; the name it carries is unchanged.
      """

  Scenario: A "drivers" key in the extension configuration fails naming the "backends" key
    Given a file named "behat.php" with:
      """
      <?php
      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\Context\MinkContext;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

      $profile = (new Profile('default'))
        ->withSuite((new Suite('default'))->addContext('FeatureContext')->addContext(MinkContext::class))
        ->withExtension(new Extension(MinkExtension::class, ['base_url' => 'http://nginx:8080', 'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]]]))
        ->withExtension(new Extension(BehatStepsExtension::class, ['drivers' => ['drupal', 'blackbox'], 'drupal' => ['drupal_root' => '/app/build/web']]));

      return (new Config())->withProfile($profile);
      """
    And a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Content
        Scenario: A scenario under a configuration carrying a drivers key
          Given I go to the homepage
      """
    When I run "behat --no-colors"
    Then it should fail
    And the output should contain:
      """
      The "drivers" setting under "behat_steps" moved to "backends".
      """

  Scenario: An entity creation hook declared with an argument fails the run
    Given a file named "features/bootstrap/FeatureContext.php" with:
      """
      <?php
      use DrevOps\BehatSteps\Behat\Context\WebRawContext;
      use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeNodeCreate;
      class FeatureContext extends WebRawContext {
        #[BeforeNodeCreate('article')]
        public static function alterArticle(): void {}
      }
      """
    And a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Homepage
        Scenario: Anonymous user visits homepage
          Given I go to the homepage
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      The "#[BeforeNodeCreate]" attribute on "FeatureContext::alterArticle()" takes no argument. The hook runs for every entity created in its scope, so read the entity from "$scope->getStub()" and return early for one it does not handle.
      """

  Scenario: A skip tag naming a hook rather than a trait fails at scenario start
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Content
        @behat-steps-skip:emailAfterScenario
        Scenario: A scenario skips a hook by its method name
          Given I go to the homepage
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      The "@behat-steps-skip:emailAfterScenario" tag does not name a trait. A skip tag takes the name of the trait whose hooks it switches off, as in "@behat-steps-skip:JavascriptTrait".
      """

  Scenario: A skip tag naming a trait no context composes fails at scenario start
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Content
        @behat-steps-skip:PahtTrait
        Scenario: A scenario skips a misspelled trait
          Given I go to the homepage
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      The "@behat-steps-skip:PahtTrait" tag names no trait a context of the "default" suite composes, so it would switch nothing off. Check the trait name for a typo, or remove the tag.
      """

  Scenario: A skip tag naming a trait a context composes passes
    Given a file named "features/drupal_bootstrap.feature" with:
      """
      Feature: Content
        @behat-steps-skip:PathTrait
        Scenario: A scenario skips a composed trait
          Given I go to the homepage
      """
    When I run "behat --no-colors"
    Then it should pass
