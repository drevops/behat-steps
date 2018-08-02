@d7
Feature: Check that EmailTrait for D7 works

  Background:
    Given I am logged in as a user with the "administrator" role
    And users:
      | name               | mail                           | roles              |
      | administrator      | admin@example.com              | administrator      |
      | authenticated_user | authenticated_user@example.com | authenticated user |

  @api
  Scenario: Assert that mail system can be enable
    And I enable the test email system

  @api
  Scenario: Assert that mail can be send and get
    When I send test email to "authenticated_user@example.com" with:
      """
      simpleSubject
      simpleBody
      """
    And an email is sent to "authenticated_user@example.com"

  @api
  Scenario: Assert that mail is sending with right fields
    Given an email to "authenticated_user" user is "sent" with "to" content:
      """
      authenticated_user@example.com
      """
    And an email to "authenticated_user" user is "sent" with "subject" content:
      """
      simpleSubject
      """
    And an email to "authenticated_user" user is "sent" with "body" content:
      """
      simpleBody
      """

  @api
  Scenario: Assert that mail system can be clear and disable
    Given I clear the test email system queue
    Then I disable the test email system
