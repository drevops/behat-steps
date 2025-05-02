Feature: Ensure SelectTrait works.
  As Behat Steps library developer
  I want to provide tools to test select field options and their states
  So that users can verify form functionality with select elements

  @api
  Scenario: Assert that a select has/has not an option
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/regional/settings"
    Then the option "AU" should exist within the select element "site_default_country"
    And the option "DUMMY-COUNTRY" should not exist within the select element "site_default_country"

  @api
  Scenario: Assert that a select option is selected
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/regional/settings"
    Then the option "UTC" should exist within the select element "date_default_timezone"
    And the option "UTC" should be selected within the select element "date_default_timezone"

  @api
  Scenario: Assert that a select option is not selected
    Given I am logged in as a user with the "administrator" role
    When I visit "/admin/config/regional/settings"
    Then the option "Australia/Sydney" should exist within the select element "date_default_timezone"
    And the option "Australia/Sydney" should not be selected within the select element "date_default_timezone"

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should exist within the select element :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should exist within the select element "non_existent_select"
      """
    When I run "behat --no-colors"
    Then it should fail with a "InvalidArgumentException" exception:
      """
      Element "non_existent_select" is not found.
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should exist within the select element :selector" for non-existent option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "INVALID_OPTION" should exist within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "InvalidArgumentException" exception:
      """
      Option "INVALID_OPTION" is not found in select "date_default_timezone".
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should not exist within the select element :selector" for existing option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should not exist within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "InvalidArgumentException" exception:
      """
      Option "UTC" is found in select "date_default_timezone", but should not.
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should be selected within the select element :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should be selected within the select element "non_existent_select"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The select "non_existent_select" was not found on the page /admin/config/regional/settings
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should not be selected within the select element :selector" for non-existent option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "INVALID_OPTION" should not be selected within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The option "INVALID_OPTION" was not found in the select "date_default_timezone" on the page /admin/config/regional/settings
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should not be selected within the select element :selector" for selected option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should not be selected within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The option "UTC" was selected in the select "date_default_timezone" on the page /admin/config/regional/settings, but should not be
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should not exist within the select element :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should not exist within the select element "non_existent_select"
      """
    When I run "behat --no-colors"
    Then it should fail with a "InvalidArgumentException" exception:
      """
      Element "non_existent_select" is not found.
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should be selected within the select element :selector" for non-existent option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "INVALID_OPTION" should be selected within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      No option is selected in the date_default_timezone select on the page /admin/config/regional/settings
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should be selected within the select element :selector" for non-selected option
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "Australia/Sydney" should be selected within the select element "date_default_timezone"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The option "Australia/Sydney" was not selected on the page /admin/config/regional/settings
      """

  @api @trait:SelectTrait
  Scenario: Assert negative "the option :option should not be selected within the select element :selector" for non-existent select
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "/admin/config/regional/settings"
      Then the option "UTC" should not be selected within the select element "non_existent_select"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Exception" exception:
      """
      The select "non_existent_select" was not found on the page /admin/config/regional/settings
      """
