Feature: Check that MessageTrait works
  As Behat Steps library developer
  I want to provide tools to assert Drupal status messages
  So that users can verify the feedback a page renders

  Scenario: Assert "Then the message :message should exist" works as expected
    Given the user is anonymous
    When I visit "/user/login"
    And I fill in "Username" with "nonexistent-user"
    And I fill in "Password" with "wrong-password"
    And I press "Log in"
    Then the error message "Unrecognized username or password." should exist
    And the message "Unrecognized username or password." should exist
    And the success message "Unrecognized username or password." should not exist
    And the warning message "Unrecognized username or password." should not exist

  Scenario: Assert "Then the following error messages should exist:" works as expected
    Given the user is anonymous
    When I visit "/user/login"
    And I press "Log in"
    Then the following error messages should exist:
      | Username field is required. |
      | Password field is required. |
    And the following error messages should not exist:
      | This message was never rendered. |

  Scenario: Assert "Then the message :message should not exist" works as expected
    Given the user is anonymous
    When I visit "/user/login"
    Then the message "Unrecognized username or password." should not exist

  @phpserver
  Scenario: Assert "Then the success message :message should exist" works as expected
    When I visit "http://cli:8888/messages.html"
    Then the success message "Article has been created." should exist
    And the success message "has been created" should exist
    And the message "Changes saved." should exist

  @phpserver
  Scenario: Assert "Then the warning message :message should exist" works as expected
    When I visit "http://cli:8888/messages.html"
    Then the warning message "This action cannot be undone." should exist
    And the warning message "Article has been created." should not exist

  @phpserver
  Scenario: Assert "Then the error message :message should not exist" works as expected
    When I visit "http://cli:8888/messages.html"
    Then the error message "The file could not be uploaded." should exist
    And the error message "Article has been created." should not exist

  @phpserver
  Scenario: Assert "Then the following success messages should exist:" works as expected
    When I visit "http://cli:8888/messages.html"
    Then the following success messages should exist:
      | Article has been created. |
      | Changes saved.            |
    And the following success messages should not exist:
      | This action cannot be undone.   |
      | The file could not be uploaded. |

  @phpserver
  Scenario: Assert "Then the following warning messages should exist:" works as expected
    When I visit "http://cli:8888/messages.html"
    Then the following warning messages should exist:
      | This action cannot be undone.    |
      | The configuration is overridden. |
    And the following warning messages should not exist:
      | Article has been created. |
      | Changes saved.            |

  @trait:MessageTrait
  Scenario: Assert "Then the success message :message should exist" fails when no success message has the text
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then the success message "Nothing was saved." should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" does not contain the "success" message "Nothing was saved.".
      """

  @trait:MessageTrait
  Scenario: Assert "Then the warning message :message should exist" fails when the page has no warning messages
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/regions.html"
      Then the warning message "This action cannot be undone." should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/regions.html" does not contain any "warning" messages.
      """

  @trait:MessageTrait
  Scenario: Assert "Then the error message :message should not exist" fails when an error message has the text
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then the error message "could not be uploaded" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" contains the "error" message "could not be uploaded".
      """

  @trait:MessageTrait
  Scenario: Assert "Then the following success messages should exist:" fails when one message is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then the following success messages should exist:
        | Article has been created. |
        | Nothing was saved.        |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" does not contain the "success" message "Nothing was saved.".
      """

  @trait:MessageTrait
  Scenario: Assert "Then the following success messages should not exist:" fails when one message is present
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then the following success messages should not exist:
        | Nothing was saved. |
        | Changes saved.     |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" contains the "success" message "Changes saved.".
      """

  @trait:MessageTrait
  Scenario: Assert "Then the following warning messages should exist:" fails when one message is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then the following warning messages should exist:
        | This action cannot be undone. |
        | The disk is almost full.      |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" does not contain the "warning" message "The disk is almost full.".
      """

  @trait:MessageTrait
  Scenario: Assert "Then the following warning messages should not exist:" fails when one message is present
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/messages.html"
      Then the following warning messages should not exist:
        | The disk is almost full.         |
        | The configuration is overridden. |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The page "http://cli:8888/messages.html" contains the "warning" message "The configuration is overridden.".
      """
