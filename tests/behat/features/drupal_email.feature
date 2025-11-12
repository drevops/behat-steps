Feature: Check that EmailTrait works
  As Behat Steps library developer
  I want to provide tools to test email functionality
  So that users can verify email sending and content in their applications

  @api @email
  Scenario: As a developer, I want to know that test email system is automatically
  activated when @email tag is added to the scenario.
    When I send test email to "test@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three of the test email content
      """
    Then an email should be sent to the "test@example.com"
    And the email field "body" should contain:
      """
      Line two of the test email content
      """

  @api @email
  Scenario: As a developer, I want to know that email step definitions work as
  expected.
    When I send test email to "test@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three   with   tabs and    spaces
      """
    Then an email should be sent to the "test@example.com"
    And the email field "body" should contain:
      """
      Line two of the test email content
      """
    And the email field "body" should not contain:
      """
      Line four of the test email content
      """
    And the email field "body" should be:
      """
      Line three   with   tabs and    spaces
      """
    And the email field "body" should not be:
      """
      Line three with tabs and spaces
      """
    But the email field "body" should contain:
      """
      Line three with tabs and spaces
      """
    And the email field "body" should contain:
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
    Then an email should be sent to the "test@example.com"
    And an email should be sent to the "test@example.com"
    And no emails should have been sent to the "test3@example.com"
    And the email header "Content-Type" should contain:
      """
      text/plain
      """
    And the email header "X-Mailer" should contain:
      """
      Drupal
      """

  @api @email
  Scenario: As a developer, I want to verify that the email header matches exactly
    Given I send test email to "test@example.com" with
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """
    Then an email should be sent to the "test@example.com"
    And the email header "Content-Type" should exactly be:
      """
      text/plain; charset=UTF-8
      """
    And the email header "X-Mailer" should exactly be:
      """
      Drupal
      """

  @api @email
  Scenario: As a developer, I want to verify that an email is sent to an address with specific content
    Given I send test email to "test@example.com" with
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """
    Then an email should be sent to the address "test@example.com" with the content:
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """

  @api @email
  Scenario: As a developer, I want to verify that an email is sent to an address with content containing a substring
    Given I send test email to "test@example.com" with
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """
    Then an email should be sent to the address "test@example.com" with the content containing:
      """
      content line tw
      """

  @api @email
  Scenario: As a developer, I want to verify that an email is sent to an address with content not containing a substring
    Given I send test email to "test@example.com" with
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """
    Then an email should be sent to the address "test@example.com" with the content not containing:
      """
      content line four
      """

  @api @email
  Scenario: As a developer, I want to verify that an email is not sent to an address with specific content
    Given I send test email to "different@example.com" with
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """
    Then an email should not be sent to the address "test@example.com" with the content:
      """
      Test email content line one-one
      Test email content line two-two
      Test email content line three-three
      """

  @api @email
  Scenario: As a developer, I want to verify that an email is not sent to an address with content containing a substring
    Given I send test email to "different@example.com" with
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """
    Then an email should not be sent to the address "test@example.com" with the content containing:
      """
      content line four
      """

  @api @email
  Scenario: As a developer, I want to know that test email system is activated as before and after scenario steps
    Given I send test email to "test@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three of the test email content
      """
    Then an email should be sent to the "test@example.com"
    And the email field "body" should contain:
      """
      Line two of the test email content
      """
    And the email field "body" should not contain:
      """
      Line four of the test email content
      """

  @api @email
  Scenario: As a developer, I want to know that test email system queue clearing step is working
    Given I enable the test email system
    And I send test email to "test@example.com" with
      """
      Line one of the test email content
      Line two of the test email content
      Line three of the test email content
      """
    Then an email should be sent to the "test@example.com"
    And the email field "body" should contain:
      """
      Line two of the test email content
      """
    And the email field "body" should not contain:
      """
      Line four of the test email content
      """
    When I clear the test email system queue
    Then the email field "body" should not contain:
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
    Then an email should be sent to the "test@example.com"

    And I follow link number "<number>" in the email with the subject "Test Email"
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
  Scenario: As a developer, I want to follow a link in an email by matching a subject substring
    When I send test email to "test@example.com" with:
      """
      Here is your link: https://example.com/reset-password
      """
    Then an email should be sent to the "test@example.com"
    When I follow link number 1 in the email with the subject containing "Test Email"
    Then I should be on "https://example.com/reset-password"

  @api @email
  Scenario: As a developer, I want to know that no emails assertions works as expected
    Given no emails should have been sent
    When I send test email to "test@example.com" with
      """
      Line one of the test email content
      "<content>"
      Line two of the test email content
      """
    Then an email should be sent to the "test@example.com"

    When I clear the test email system queue
    Then no emails should have been sent

  @api @email
  Scenario: As a developer, I want to manually enable the test email system and verify it works
    Given no emails should have been sent
    And I enable the test email system
    And I send test email to "test@example.com" with
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """
    Then an email should be sent to the "test@example.com"
    And the email field "body" should contain:
      """
      Test email content line two
      """
    And the email field "body" should not contain:
      """
      Non-existent content
      """

  @api @email
  Scenario: As a developer, I want to manually disable the test email system and verify it works
    Given I enable the test email system
    And I send test email to "test@example.com" with
      """
      Test email content line one
      Test email content line two
      Test email content line three
      """
    When I disable the test email system
    Then no emails should have been sent

  @api @email
  Scenario: As a developer, I want to verify that an email contains an attachment
    When I send test email to "test@example.com" with subject "Email with Attachment" and attachment "example.pdf" and body:
      """
      This email contains an attachment.
      """
    Then an email should be sent to the "test@example.com"
    And the email field "subject" should be:
      """
      Email with Attachment
      """
    And the file "example.pdf" should be attached to the email with the subject "Email with Attachment"

  @api @email
  Scenario: As a developer, I want to verify that an email with a subject containing a substring has an attachment
    When I send test email to "test@example.com" with subject "Email with Attachment" and attachment "example.pdf" and body:
      """
      This email contains an attachment.
      """
    Then an email should be sent to the "test@example.com"
    And the file "example.pdf" should be attached to the email with the subject containing "with Attachment"

  @api @email
  Scenario: As a developer, I want error when no emails sent but some expected
    Given no emails should have been sent
    When I send test email to "test@example.com" with:
      """
      Test content
      """
    Then an email should be sent to the "test@example.com"

  @api @email
  Scenario: As a developer, I want to verify no emails sent to address assertion passes when address not used
    When I send test email to "test@example.com" with:
      """
      Test content
      """
    Then no emails should have been sent to the "wrong@example.com"

  @api @email
  Scenario: As a developer, I want to verify no emails sent to CC address assertion passes when address not used
    When I send test email to "to@example.com" with cc "cc@example.com" with:
      """
      Test content with CC
      """
    Then no emails should have been sent to the "wrong@example.com"

  @api @email
  Scenario: As a developer, I want to verify no emails sent to BCC address assertion passes when address not used
    When I send test email to "to@example.com" with bcc "bcc@example.com" with:
      """
      Test content with BCC
      """
    Then no emails should have been sent to the "wrong@example.com"

  @api @email @debug
  Scenario: As a developer, I want to verify email debug output is triggered with @debug tag
    When I send test email to "test@example.com" with:
      """
      Debug test content
      """
    Then an email should be sent to the "test@example.com"
    And the email field "body" should contain:
      """
      Debug test content
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that an email was sent to an address
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      Given I am logged in as a user with the "administrator" role
      Then an email should be sent to the "test@example.com"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find email that should be sent to "test@example.com" retrieved from test message collector.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that clearing email queue without @email tag fails
    Given some behat configuration
    And scenario steps:
      """
      When I clear the test email system queue
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Clearing testing email system queue can be done only when email testing system is activated.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that no emails sent to CC address fails when email WAS sent to that CC address
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "to@example.com" with cc "cc@example.com" with:
        '''
        Test content with CC
        '''
      Then no emails should have been sent to the "cc@example.com"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      An email was cc'ed to "cc@example.com" retrieved from test email collector, but it should not have been.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that no emails sent to BCC address fails when email WAS sent to that BCC address
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "to@example.com" with bcc "bcc@example.com" with:
        '''
        Test content with BCC
        '''
      Then no emails should have been sent to the "bcc@example.com"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      An email was bcc'ed to "bcc@example.com" retrieved from test email collector, but it should not have been.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that following link in email fails when email subject not found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Email with a link: http://example.com
        '''
      Then I follow link number "1" in the email with the subject "Wrong Subject"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find email with subject "Wrong Subject" retrieved from test email collector.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that following link in email fails when no links found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Email with no links
        '''
      Then I follow link number "1" in the email with the subject "Test Email"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No links were found in the email with subject "Test Email"
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that following link in email fails when link number too high
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Email with one link: http://example.com
        '''
      Then I follow link number "5" in the email with the subject "Test Email"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with number 5 was not found among 1 links
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that following link by subject substring fails when subject not found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Email with a link: http://example.com
        '''
      Then I follow link number 1 in the email with the subject containing "Nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find email with subject containing "Nonexistent" retrieved from test email collector.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that following link by subject substring fails when no links found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Email with no links
        '''
      Then I follow link number 1 in the email with the subject containing "Test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No links were found in the email with subject containing "Test"
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that following link by subject substring fails when link number too high
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Email with one link: http://example.com
        '''
      Then I follow link number 3 in the email with the subject containing "Email"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with number 3 was not found among 1 links
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that no emails sent fails when emails WERE sent
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content
        '''
      Then no emails should have been sent
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No emails should have been sent, but some were found:
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that no emails sent to address fails when email WAS sent to that address
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content
        '''
      Then no emails should have been sent to the "test@example.com"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      An email was sent to "test@example.com" retrieved from test email collector, but it should not have been.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that email header contains fails when header not found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content
        '''
      Then the email header "X-Nonexistent-Header" should contain:
        '''
        Some value
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find an email where the header "X-Nonexistent-Header" should contain text "Some value" retrieved from test email collector.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that email field contains fails when field not found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content
        '''
      Then the email field "body" should contain:
        '''
        Nonexistent content
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find an email where the field "body" should contain text "Nonexistent content" retrieved from test email collector.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that email field should not contain fails when field DOES contain value
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content with specific text
        '''
      Then the email field "body" should not contain:
        '''
        specific text
        '''
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Found an email where the field "body" contains text "specific text" retrieved from test email collector, but it should not.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that attachment assertion fails when email subject not found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content
        '''
      Then the file "test.pdf" should be attached to the email with the subject "Wrong Subject"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find email with subject "Wrong Subject" retrieved from test email collector.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that attachment assertion fails when no attachments found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content without attachments
        '''
      Then the file "test.pdf" should be attached to the email with the subject "Test Email"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No attachments were found in the email with subject Test Email
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that attachment with subject substring fails when email not found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content
        '''
      Then the file "test.pdf" should be attached to the email with the subject containing "Nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find email with subject containing "Nonexistent" retrieved from test email collector.
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that attachment with subject substring fails when no attachments found
    Given some behat configuration
    And scenario steps tagged with "@api @email":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content without attachments
        '''
      Then the file "test.pdf" should be attached to the email with the subject containing "Test"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No attachments were found in the email with subject containing "Test"
      """

  @trait:Drupal\EmailTrait
  Scenario: Assert that behat-steps-skip tag for AfterScenario hook works
    Given some behat configuration
    And scenario steps tagged with "@api @email @behat-steps-skip:emailAfterScenario":
      """
      When I send test email to "test@example.com" with:
        '''
        Test content
        '''
      Then an email should be sent to the "test@example.com"
      When I disable the test email system
      """
    When I run "behat --no-colors"
    Then it should pass

  @api @email @email:default
  Scenario: As a developer, I want to verify custom email handler type tag is processed
    When I send test email to "test@example.com" with:
      """
      Test content with custom handler type
      """
    Then an email should be sent to the "test@example.com"
    And the email field "body" should contain:
      """
      Test content with custom handler type
      """
