Feature: Check that ModuleTrait works
  As Behat Steps library developer
  I want to provide tools to manage Drupal modules programmatically
  So that users can enable/disable modules during tests and restore state automatically

  Scenario: Assert "Given the :module module is enabled" enables a module
    When I log in as a user with the "administrator" role
    And the "help" module is disabled
    When the "help" module is enabled
    Then the "help" module should be enabled

  Scenario: Assert "Given the :module module is disabled" disables a module
    When I log in as a user with the "administrator" role
    And the "help" module is enabled
    When the "help" module is disabled
    Then the "help" module should be disabled

  Scenario: Assert "Then the :module module should be enabled" assertion works
    When I log in as a user with the "administrator" role
    And the "help" module is enabled
    Then the "help" module should be enabled

  Scenario: Assert "Then the :module module should be disabled" assertion works
    When I log in as a user with the "administrator" role
    And the "help" module is disabled
    Then the "help" module should be disabled

  Scenario: Assert "Given the following modules are enabled:" enables multiple modules
    When I log in as a user with the "administrator" role
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
    When I log in as a user with the "administrator" role
    And the following modules are enabled:
      | help   |
      | syslog |
    When the following modules are disabled:
      | help   |
      | syslog |
    Then the following modules should be disabled:
      | help   |
      | syslog |

  @trait:Drupal\ModuleTrait
  Scenario: Assert negative assertion for "Then the :module module should be enabled" works with disabled module
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the "help" module is disabled
      Then the "help" module should be enabled
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The module "help" is not enabled, but it should be.
      """

  @trait:Drupal\ModuleTrait
  Scenario: Assert negative assertion for "Then the :module module should be disabled" works with enabled module
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the "help" module is enabled
      Then the "help" module should be disabled
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The module "help" is enabled, but it should not be.
      """

  @module:help
  Scenario: Assert @module:module_name tag enables module automatically
    When I log in as a user with the "administrator" role
    Then the "help" module should be enabled

  @module:!help
  Scenario: Assert @module:!module_name tag disables module automatically
    When I log in as a user with the "administrator" role
    Then the "help" module should be disabled

  @module:help @module:syslog
  Scenario: Assert multiple @module tags enable multiple modules
    When I log in as a user with the "administrator" role
    Then the "help" module should be enabled
    And the "syslog" module should be enabled

  @module:help @module:syslog @module:!contextual
  Scenario: Assert mixed @module tags with enable and disable work together
    When I log in as a user with the "administrator" role
    Then the "help" module should be enabled
    And the "syslog" module should be enabled
    And the "contextual" module should be disabled

  # Skip automatic state restoration because this scenario intentionally sets up
  # initial state for the next scenarios to test tag-based restoration.
  # Without the skip tag, the Given step would store the original state and
  # restore it at the end, interfering with the cross-scenario test flow.
  @behat-steps-skip:moduleAfterScenario
  Scenario: Assert module state is restored after scenario changes
    When I log in as a user with the "administrator" role
    # First, ensure help is disabled
    And the "help" module is disabled
    Then the "help" module should be disabled

  @module:help
  Scenario: Assert module state restoration works after tag-based enable
    When I log in as a user with the "administrator" role
    # This scenario should enable help via tag, but after this scenario
    # the previous state should be restored in the next scenario
    Then the "help" module should be enabled

  Scenario: Verify module state was restored after previous scenario with tag
    When I log in as a user with the "administrator" role
    # This verifies that help module was restored to disabled state
    # after the previous scenario that used @module:help tag
    Then the "help" module should be disabled

  Scenario: Setup initial state for Given step restoration test
    When I log in as a user with the "administrator" role
    # Ensure syslog is disabled as the initial state
    And the "syslog" module is disabled
    Then the "syslog" module should be disabled

  Scenario: Assert module state changes via Given step
    When I log in as a user with the "administrator" role
    # Enable syslog module using Given step (not tag)
    And the "syslog" module is enabled
    Then the "syslog" module should be enabled

  Scenario: Verify module state was restored after Given step modification
    When I log in as a user with the "administrator" role
    # This verifies that syslog module was restored to disabled state
    # after the previous scenario modified it using Given step
    Then the "syslog" module should be disabled

  Scenario: Assert enabling already-enabled module is idempotent
    When I log in as a user with the "administrator" role
    And the "help" module is enabled
    When the "help" module is enabled
    Then the "help" module should be enabled

  Scenario: Assert disabling already-disabled module is idempotent
    When I log in as a user with the "administrator" role
    And the "help" module is disabled
    When the "help" module is disabled
    Then the "help" module should be disabled

  @trait:Drupal\ModuleTrait
  Scenario: Assert enabling non-existent module throws error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the "nonexistent_module_xyz" module is enabled
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Cannot enable module "nonexistent_module_xyz": module is not installed.
      """

  @trait:Drupal\ModuleTrait
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

  @trait:Drupal\ModuleTrait
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

  @trait:Drupal\ModuleTrait
  Scenario: Assert that skip tag for beforeScenario hook works
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:moduleBeforeScenario":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass
