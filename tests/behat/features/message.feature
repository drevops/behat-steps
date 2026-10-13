Feature: Check that MessageTrait works
  As Behat Steps library developer
  I want to provide tools to assert Drupal status messages
  So that users can verify the feedback a page renders

  Scenario: Assert "Then I should see the message :message" works as expected
    Given I am an anonymous user
    When I visit "/user/login"
    And I fill in "Username" with "nonexistent-user"
    And I fill in "Password" with "wrong-password"
    And I press "Log in"
    Then I should see the error message "Unrecognized username or password."
    And I should see the message "Unrecognized username or password."
    And I should not see the success message "Unrecognized username or password."
    And I should not see the warning message "Unrecognized username or password."

  Scenario: Assert "Then I should see the following error messages:" works as expected
    Given I am an anonymous user
    When I visit "/user/login"
    And I press "Log in"
    Then I should see the following error messages:
      | Username field is required. |
      | Password field is required. |
    And I should not see the following error messages:
      | This message was never rendered. |

  Scenario: Assert "Then I should not see the message :message" works as expected
    Given I am an anonymous user
    When I visit "/user/login"
    Then I should not see the message "Unrecognized username or password."

  @phpserver
  Scenario: Assert "Then I should see the success message :message" works as expected
    When I visit "http://cli:8888/messages.html"
    Then I should see the success message "Article has been created."
    And I should see the success message "has been created"
    And I should see the message "Changes saved."

  @phpserver
  Scenario: Assert "Then I should see the warning message :message" works as expected
    When I visit "http://cli:8888/messages.html"
    Then I should see the warning message "This action cannot be undone."
    And I should not see the warning message "Article has been created."

  @phpserver
  Scenario: Assert "Then I should not see the error message :message" works as expected
    When I visit "http://cli:8888/messages.html"
    Then I should see the error message "The file could not be uploaded."
    And I should not see the error message "Article has been created."

  @phpserver
  Scenario: Assert "Then I should see the following success messages:" works as expected
    When I visit "http://cli:8888/messages.html"
    Then I should see the following success messages:
      | Article has been created. |
      | Changes saved.            |
    And I should not see the following success messages:
      | This action cannot be undone.   |
      | The file could not be uploaded. |

  @phpserver
  Scenario: Assert "Then I should see the following warning messages:" works as expected
    When I visit "http://cli:8888/messages.html"
    Then I should see the following warning messages:
      | This action cannot be undone.    |
      | The configuration is overridden. |
    And I should not see the following warning messages:
      | Article has been created. |
      | Changes saved.            |

  @test-trait:MessageTrait
  Scenario: Assert "Then I should see the success message :message" fails when no success message has the text
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then I should see the success message "Nothing was saved."
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" does not contain the "success" message "Nothing was saved.".
      """

  @test-trait:MessageTrait
  Scenario: Assert "Then I should see the warning message :message" fails when the page has no warning messages
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then I should see the warning message "This action cannot be undone."
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Warning message matching css ".messages.messages--warning" not found.
      """

  @test-trait:MessageTrait
  Scenario: Assert "Then I should not see the error message :message" fails when an error message has the text
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then I should not see the error message "could not be uploaded"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" contains the "error" message "could not be uploaded".
      """

  @test-trait:MessageTrait
  Scenario: Assert "Then I should see the following success messages:" fails when one message is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then I should see the following success messages:
        | Article has been created. |
        | Nothing was saved.        |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" does not contain the "success" message "Nothing was saved.".
      """

  @test-trait:MessageTrait
  Scenario: Assert "Then I should not see the following success messages:" fails when one message is present
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then I should not see the following success messages:
        | Nothing was saved. |
        | Changes saved.     |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" contains the "success" message "Changes saved.".
      """

  @test-trait:MessageTrait
  Scenario: Assert "Then I should see the following warning messages:" fails when one message is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then I should see the following warning messages:
        | This action cannot be undone. |
        | The disk is almost full.      |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" does not contain the "warning" message "The disk is almost full.".
      """

  @test-trait:MessageTrait
  Scenario: Assert "Then I should not see the following warning messages:" fails when one message is present
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then I should not see the following warning messages:
        | The disk is almost full.         |
        | The configuration is overridden. |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" contains the "warning" message "The configuration is overridden.".
      """
