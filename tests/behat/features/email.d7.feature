@d7
Feature: Check that EmailTrait for D7 works

  Background:
    Given I am logged in as a user with the "administrator" role
    And users:
      | name          | mail                | roles              |
      | administrator | admin@myexample.com | administrator      |
      | simple_user   | MW@myexample.com    | authenticated user |

  @api
  Scenario: Assert that mail system can be enable
    Given I enable the test email system

  @api
  Scenario: Assert that mail can be send and get
    When I send test email to "MW@myexample.com" with:
    """
    some
    text
    """
    And an email is sent to "MW@myexample.com"

  @api
  Scenario: Assert that "to" field contain a mail
    Given an email to "simple_user" user is "sent" with "to" content:
    """
    MW@myexample.com
    """
    And an email "to" contains exact:
    """
    MW@myexample.com
    """

  @api
  Scenario: Assert that mail system can be clear and disable
    Given I clear the test email system queue
    Then I disable the test email system
