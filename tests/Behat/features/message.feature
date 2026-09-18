Feature: Check that MessageTrait works
  As Behat Steps library developer
  I want to provide tools to assert Drupal status messages
  So that users can verify the feedback a page renders

  @api
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

  @api
  Scenario: Assert "Then the following error messages should exist:" works as expected
    Given the user is anonymous
    When I visit "/user/login"
    And I press "Log in"
    Then the following error messages should exist:
      | Username field is required. |
      | Password field is required. |
    And the following error messages should not exist:
      | This message was never rendered. |

  @api
  Scenario: Assert "Then the message :message should not exist" works as expected
    Given the user is anonymous
    When I visit "/user/login"
    Then the message "Unrecognized username or password." should not exist
