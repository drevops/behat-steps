@d7
Feature: Check that EmailTrait for D7 works

  @api
  Scenario: Assert mail system
    Given I am logged in as a user with the "administrator" role
    Given I enable the test email system
    Then an email is sent to "admin@example.com"
    Then no emails were sent
