@d7
Feature: Check that email assertions work for D7

  @api
  Scenario: As a developer, I want to know that email step definitions work as
  expected.
    Given I enable the test email system
    When I send test email to "test@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three   with   tabs and    spaces
      """
    Then an email is sent to "test@example.com"
    And an email body contains:
      """
      Line two of the test email content
      """
    And an email body does not contain:
      """
      Line four of the test email content
      """
    And an email body contains exact:
      """
      Line three   with   tabs and    spaces
      """
    And an email body does not contain exact:
      """
      Line three with tabs and spaces
      """
    But an email body contains:
      """
      Line three with tabs and spaces
      """
    And an email body contains:
      """
      Line   three   with  tabs and spaces
      """
    And I disable the test email system

  Scenario: As a developer, I want to know that test email system is activated
  as before and after scenario steps.
    Given I enable the test email system
    When I send test email to "test@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three of the test email content
      """
    Then an email is sent to "test@example.com"
    And an email "body" contains:
      """
      Line two of the test email content
      """
    And an email body does not contain:
      """
      Line four of the test email content
      """
    And I disable the test email system

  Scenario: As a developer, I want to know that test email system queue clearing
  step is working.
    Given I enable the test email system
    When I send test email to "test@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three of the test email content
      """
    Then an email is sent to "test@example.com"
    And an email body contains:
      """
      Line two of the test email content
      """
    And an email body does not contain:
      """
      Line four of the test email content
      """
    When I clear the test email system queue
    And an email body does not contain:
      """
      Line two of the test email content
      """
    And I disable the test email system

  @email
  Scenario: As a developer, I want to know that test email system is automatically
  activated when @email tag is added to the scenario.
    When I send test email to "test@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three of the test email content
      """
    Then an email is sent to "test@example.com"
    And an email body contains:
      """
      Line two of the test email content
      """

  @email
  Scenario Outline: As a developer, I want to know that following a link from
  the email is working.
    Given I send test email to "test@example.com" with
      """
      Line one of the test email content
      "<content>"
      Line two of the test email content
      """
    Then an email is sent to "test@example.com"

    And I follow the link number "<number>" in the email with the subject:
      """
      Test Email
      """
    Then the response status code should be 200
    And I should see "Example Domain"
    Examples:
      | content                                                       | number |
      | http://example.com                                            | 1      |
      | http://www.example.com                                        | 1      |
      | www.example.com                                               | 1      |
      | Link is a part of content http://example.com                  | 1      |
      | http://1.example.com http://example.com  http://3.example.com | 2      |
      | http://1.example.com http://2.example.com  http://example.com | 3      |

  @email
  Scenario: As a developer, I want to know that no emails assertions works as expected.
    Given no emails were sent
    Given I send test email to "test@example.com" with
      """
      Line one of the test email content
      "<content>"
      Line two of the test email content
      """
    Then an email is sent to "test@example.com"

    When I clear the test email system queue
    Then no emails were sent
