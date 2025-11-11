Feature: Check that ModuleTrait works
  As Behat Steps library developer
  I want to provide tools to manage Drupal modules programmatically
  So that users can enable/disable modules during tests and restore state automatically

  @api
  Scenario: Assert "Given the :module module is enabled" enables a module
    Given I am logged in as a user with the "administrator" role
    And the "help" module is disabled
    When the "help" module is enabled
    Then the "help" module should be enabled

  @api
  Scenario: Assert "Given the :module module is disabled" disables a module
    Given I am logged in as a user with the "administrator" role
    And the "help" module is enabled
    When the "help" module is disabled
    Then the "help" module should be disabled

  @api
  Scenario: Assert "Then the :module module should be enabled" assertion works
    Given I am logged in as a user with the "administrator" role
    And the "help" module is enabled
    Then the "help" module should be enabled

  @api
  Scenario: Assert "Then the :module module should be disabled" assertion works
    Given I am logged in as a user with the "administrator" role
    And the "help" module is disabled
    Then the "help" module should be disabled

  @api
  Scenario: Assert "Given the following modules are enabled:" enables multiple modules
    Given I am logged in as a user with the "administrator" role
    And the following modules are disabled:
      | help |
      | ban  |
    When the following modules are enabled:
      | help |
      | ban  |
    Then the following modules should be enabled:
      | help |
      | ban  |

  @api
  Scenario: Assert "Given the following modules are disabled:" disables multiple modules
    Given I am logged in as a user with the "administrator" role
    And the following modules are enabled:
      | help |
      | ban  |
    When the following modules are disabled:
      | help |
      | ban  |
    Then the following modules should be disabled:
      | help |
      | ban  |

  @api @trait:Drupal\ModuleTrait
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

  @api @trait:Drupal\ModuleTrait
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

  @api @module:help
  Scenario: Assert @module:module_name tag enables module automatically
    Given I am logged in as a user with the "administrator" role
    Then the "help" module should be enabled

  @api @module:!help
  Scenario: Assert @module:!module_name tag disables module automatically
    Given I am logged in as a user with the "administrator" role
    Then the "help" module should be disabled

  @api @module:help @module:ban
  Scenario: Assert multiple @module tags enable multiple modules
    Given I am logged in as a user with the "administrator" role
    Then the "help" module should be enabled
    And the "ban" module should be enabled

  @api @module:help @module:ban @module:!history
  Scenario: Assert mixed @module tags with enable and disable work together
    Given I am logged in as a user with the "administrator" role
    Then the "help" module should be enabled
    And the "ban" module should be enabled
    And the "history" module should be disabled

  # Skip automatic state restoration because this scenario intentionally sets up
  # initial state for the next scenarios to test tag-based restoration.
  # Without the skip tag, the Given step would store the original state and
  # restore it at the end, interfering with the cross-scenario test flow.
  @api @behat-steps-skip:moduleAfterScenario
  Scenario: Assert module state is restored after scenario changes
    Given I am logged in as a user with the "administrator" role
    # First, ensure help is disabled
    And the "help" module is disabled
    Then the "help" module should be disabled

  @api @module:help
  Scenario: Assert module state restoration works after tag-based enable
    Given I am logged in as a user with the "administrator" role
    # This scenario should enable help via tag, but after this scenario
    # the previous state should be restored in the next scenario
    Then the "help" module should be enabled

  @api
  Scenario: Verify module state was restored after previous scenario with tag
    Given I am logged in as a user with the "administrator" role
    # This verifies that help module was restored to disabled state
    # after the previous scenario that used @module:help tag
    Then the "help" module should be disabled

  @api
  Scenario: Setup initial state for Given step restoration test
    Given I am logged in as a user with the "administrator" role
    # Ensure ban is disabled as the initial state
    And the "ban" module is disabled
    Then the "ban" module should be disabled

  @api
  Scenario: Assert module state changes via Given step
    Given I am logged in as a user with the "administrator" role
    # Enable ban module using Given step (not tag)
    And the "ban" module is enabled
    Then the "ban" module should be enabled

  @api
  Scenario: Verify module state was restored after Given step modification
    Given I am logged in as a user with the "administrator" role
    # This verifies that ban module was restored to disabled state
    # after the previous scenario modified it using Given step
    Then the "ban" module should be disabled

  @api
  Scenario: Assert enabling already-enabled module is idempotent
    Given I am logged in as a user with the "administrator" role
    And the "help" module is enabled
    When the "help" module is enabled
    Then the "help" module should be enabled

  @api
  Scenario: Assert disabling already-disabled module is idempotent
    Given I am logged in as a user with the "administrator" role
    And the "help" module is disabled
    When the "help" module is disabled
    Then the "help" module should be disabled

  @api @trait:Drupal\ModuleTrait
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
