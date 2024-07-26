Feature: Ensure SelectTrait works.

  @api
  Scenario: Assert that a select has/has not an option
    Given I am logged in as a user with the "administrator" role
    Then I visit "/admin/config/regional/settings"
    Then select "site_default_country" should have an option "AU"
    Then select "site_default_country" should not have an option "DUMMY-COUNTRY"

  @api
  Scenario: Assert that a select option is selected.
    Given I am logged in as a user with the "administrator" role
    Then I visit "/admin/config/regional/settings"
    Then select "date_default_timezone" should have an option "UTC"
    Then the option "UTC" from select "date_default_timezone" is selected
