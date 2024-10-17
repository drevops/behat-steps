Feature: Check that email assertions work for or D9

  @api @email
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

  @api @email
  Scenario: As a developer, I want to know that email step definitions work as
  expected.
    Given I send test email to "test@example.com" with
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

  @api @email
  Scenario: As a developer, I want to know that an email is sent to step definition can correctly assert
  emails sent to multiple recipients.
    Given I send test email to "test@example.com,test2@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three of the test email content
      """
    Then an email is sent to "test@example.com"
    And an email is sent to "test2@example.com"
    And no emails were sent to "test3@example.com"
    And an email header "Content-Type" contains:
      """
      text/plain
      """
    And an email header "X-Mailer" contains:
      """
      Drupal
      """

  @api @email
  Scenario: As a developer, I want to know that test email system is activated
  as before and after scenario steps.
    Given I send test email to "test@example.com" with
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

  @api @email
  Scenario: As a developer, I want to know that test email system queue clearing
  step is working.
    Given I send test email to "test@example.com" with
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

  @api @email
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

  @api @email
  Scenario: As a developer, I want to know that no emails assertions works as expected.
    Given no emails were sent
    When I send test email to "test@example.com" with
      """
      Line one of the test email content
      "<content>"
      Line two of the test email content
      """
    Then an email is sent to "test@example.com"

    When I clear the test email system queue
    Then no emails were sent

  @trait:EmailTrait
  Scenario: Assert that an email was sent to an address.
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      Given I am logged in as a user with the "administrator" role
      Then an email is sent to "test@example.com"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find email sent to "test@example.com" retrieved from test record collector.
      """
