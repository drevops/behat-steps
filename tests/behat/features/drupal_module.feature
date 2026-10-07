Feature: Check that ModuleTrait works
  As Behat Steps library developer
  I want to provide tools to manage Drupal modules programmatically
  So that users can enable/disable modules during tests and restore state automatically

  Scenario: Assert "Given the module :module is enabled" enables a module
    When I log in as a user with the role "administrator"
    And the module "help" is disabled
    When the module "help" is enabled
    Then the module "help" should be enabled

  Scenario: Assert "Given the module :module is disabled" disables a module
    When I log in as a user with the role "administrator"
    And the module "help" is enabled
    When the module "help" is disabled
    Then the module "help" should be disabled

  Scenario: Assert "Then the module :module should be enabled" assertion works
    When I log in as a user with the role "administrator"
    And the module "help" is enabled
    Then the module "help" should be enabled

  Scenario: Assert "Then the module :module should be disabled" assertion works
    When I log in as a user with the role "administrator"
    And the module "help" is disabled
    Then the module "help" should be disabled

  Scenario: Assert "Given the following modules are enabled:" enables multiple modules
    When I log in as a user with the role "administrator"
    And the following modules are disabled:
      | help   |
      | syslog |
    When the following modules are enabled:
      | help   |
      | syslog |
    Then the following modules should be enabled:
      | help   |
      | syslog |

  Scenario: Assert "Given the following modules are disabled:" disables multiple modules
    When I log in as a user with the role "administrator"
    And the following modules are enabled:
      | help   |
      | syslog |
    When the following modules are disabled:
      | help   |
      | syslog |
    Then the following modules should be disabled:
      | help   |
      | syslog |

  # The assertions below resolve the Module capability instead of bootstrapping
  # Drupal, so the '@backend:drush' tag reads the module list off a site this
  # process never boots. Enabling and disabling over Drush is left to the
  # in-process scenarios above, which do not pay a subprocess per module.
  @backend:drush
  Scenario: Assert an enabled core module over Drush
    Then the module "node" should be enabled
    And the module "field" should be enabled

  @backend:drush
  Scenario: Assert a module whose code is absent is not enabled
    Then the module "no_such_module_xyz" should be disabled

  @test-trait:Drupal\ModuleTrait
  Scenario: Assert negative assertion for "Then the module :module should be enabled" works with disabled module
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the module "help" is disabled
      Then the module "help" should be enabled
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The module "help" is not enabled, but it should be.
      """

  @test-trait:Drupal\ModuleTrait
  Scenario: Assert negative assertion for "Then the module :module should be disabled" works with enabled module
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the module "help" is enabled
      Then the module "help" should be disabled
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The module "help" is enabled, but it should not be.
      """

  @module:help
  Scenario: Assert @module:module_name tag enables module automatically
    When I log in as a user with the role "administrator"
    Then the module "help" should be enabled

  @module:!help
  Scenario: Assert @module:!module_name tag disables module automatically
    When I log in as a user with the role "administrator"
    Then the module "help" should be disabled

  @module:help @module:syslog
  Scenario: Assert multiple @module tags enable multiple modules
    When I log in as a user with the role "administrator"
    Then the module "help" should be enabled
    And the module "syslog" should be enabled

  @module:help @module:syslog @module:!contextual
  Scenario: Assert mixed @module tags with enable and disable work together
    When I log in as a user with the role "administrator"
    Then the module "help" should be enabled
    And the module "syslog" should be enabled
    And the module "contextual" should be disabled

  # Skip automatic state restoration because this scenario intentionally sets up
  # initial state for the next scenarios to test tag-based restoration.
  # Without the skip tag, the Given step would store the original state and
  # restore it at the end, interfering with the cross-scenario test flow.
  @behat-steps-skip:ModuleTrait
  Scenario: Assert module state is restored after scenario changes
    When I log in as a user with the role "administrator"
    And the module "help" is disabled
    Then the module "help" should be disabled

  @module:help
  Scenario: Assert module state restoration works after tag-based enable
    When I log in as a user with the role "administrator"
    Then the module "help" should be enabled

  Scenario: Verify module state was restored after previous scenario with tag
    When I log in as a user with the role "administrator"
    Then the module "help" should be disabled

  Scenario: Setup initial state for Given step restoration test
    When I log in as a user with the role "administrator"
    And the module "syslog" is disabled
    Then the module "syslog" should be disabled

  Scenario: Assert module state changes via Given step
    When I log in as a user with the role "administrator"
    And the module "syslog" is enabled
    Then the module "syslog" should be enabled

  Scenario: Verify module state was restored after Given step modification
    When I log in as a user with the role "administrator"
    Then the module "syslog" should be disabled

  Scenario: Assert enabling already-enabled module is idempotent
    When I log in as a user with the role "administrator"
    And the module "help" is enabled
    When the module "help" is enabled
    Then the module "help" should be enabled

  Scenario: Assert disabling already-disabled module is idempotent
    When I log in as a user with the role "administrator"
    And the module "help" is disabled
    When the module "help" is disabled
    Then the module "help" should be disabled

  @test-trait:Drupal\ModuleTrait
  Scenario: Assert enabling non-existent module throws error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the module "nonexistent_module_xyz" is enabled
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Cannot enable module "nonexistent_module_xyz": module is not installed.
      """

  @test-trait:Drupal\ModuleTrait
  Scenario: Assert "Then the following modules should be enabled:" fails when module is disabled
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the following modules are disabled:
        | help |
      Then the following modules should be enabled:
        | help |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The module "help" is not enabled, but it should be.
      """

  @test-trait:Drupal\ModuleTrait
  Scenario: Assert "Then the following modules should be disabled:" fails when module is enabled
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the following modules are enabled:
        | help |
      Then the following modules should be disabled:
        | help |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The module "help" is enabled, but it should not be.
      """

  @test-trait:Drupal\ModuleTrait
  Scenario: Assert that the skip tag switches the ModuleTrait hooks off
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:ModuleTrait":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\ModuleTrait
  Scenario: Assert that a @module tag on the feature applies to every scenario and a scenario tag overrides it
    Given some behat configuration
    And a file named "features/stub.feature" with:
      """
      @module:help
      Feature: Stub feature

        Scenario: The feature tag enables the module
          Then the module "help" should be enabled

        @module:!help
        Scenario: The scenario tag overrides the feature tag
          Then the module "help" should be disabled
      """
    When I run "behat --no-colors"
    Then it should pass with:
      """
      2 scenarios (2 passed)
      """
