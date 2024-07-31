@api
Feature: Check that UserTrait works for or D9

  Background:
    Given users:
      | name                        | mail                                      | roles         | status |
      | administrator_user          | administrator_user@myexample.com          | administrator | 1      |
      | authenticated_user          | authenticated_user@myexample.com          |               | 1      |
      | authenticated_user_disabled | authenticated_user_disabled@myexample.com |               | 0      |

  Scenario: Assert "When I visit user :name profile"
    Given I am logged in as a user with the "administrator" role
    When I visit user "authenticated_user" profile
    Then I should get a 200 HTTP response

  Scenario: Assert "When I edit user :name profile"
    Given I am logged in as a user with the "administrator" role
    When I edit user "authenticated_user" profile
    Then I should get a 200 HTTP response

  Scenario: Assert "When I go to my profile edit page"
    Given I am logged in as a user with the "administrator" role
    When I go to my profile edit page
    Then I should get a 200 HTTP response

  Scenario: Assert "Given no users:" by name
    Given I am logged in as a user with the "administrator" role
    When I visit user "authenticated_user" profile
    Then I should get a 200 HTTP response

    When no users:
      | name               |
      | authenticated_user |

    Then user "authenticated_user" does not exist

  Scenario: Assert "Given no users:" by email
    Given I am logged in as a user with the "administrator" role
    When I visit user "authenticated_user" profile
    Then I should get a 200 HTTP response

    When no users:
      | mail                             |
      | authenticated_user@myexample.com |

    Then user "authenticated_user" does not exist

  Scenario: Assert "Then user :name has :roles role(s) assigned"
    Given user "authenticated_user" has "authenticated" role assigned
    And user "authenticated_user" has "authenticated" roles assigned

    Given user "administrator_user" has "authenticated, administrator" roles assigned

  Scenario: Assert "Then user :name does not have :roles role(s) assigned"
    Given user "authenticated_user" does not have "administrator" role assigned
    And user "authenticated_user" does not have "administrator" roles assigned

  Scenario: Assert "Then user :name has :status status"
    Given user "authenticated_user" has "active" status

    Given user "authenticated_user_disabled" has "not active" status
    And user "authenticated_user_disabled" has "disabled" status

  Scenario: Assert "Then user :name has :status status"
    Given I set user "administrator_user" password to "password123"
    Given I set user "administrator_user@myexample.com" password to "password123"

  @trait:UserTrait
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I set user "non_existing_user" password to "password123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find a user with name or email "non_existing_user".
      """

  Scenario: Assert "Then the last access time of user :name is :time"
    Given users:
      | name                                | mail                                              | roles         | status |
      | administrator_user_test_last_access | administrator_user_test_last_access@myexample.com | administrator | 1      |
    Then I am logged in as a user with the "administrator" role
    Then I visit "/admin/people?user=administrator_user_test_last_access"
    Then I should see the text "never"
    Then the last access time of user "administrator_user_test_last_access" is "[relative:-10 years]"
    # We should not need clear cache at here. Re-check later.
    Then I visit "/admin/config/development/performance"
    Then I press the "Clear all cache" button
    Then I visit "/admin/people?user=administrator_user_test_last_access"
    Then I should not see the text "never"
    Then I should see the text "10 years ago"

  Scenario: Assert "Then the last access time of user :name is :time"
    Given users:
      | name                                | mail                                              | roles         | status |
      | administrator_user_test_last_access | administrator_user_test_last_access@myexample.com | administrator | 1      |
    Then I am logged in as a user with the "administrator" role
    Then I visit "/admin/people?user=administrator_user_test_last_access"
    Then I should see the text "never"
    Then the last access time of user "administrator_user_test_last_access" is "1406774864"
    # We should not need clear cache at here. Re-check later.
    Then I visit "/admin/config/development/performance"
    Then I press the "Clear all cache" button
    Then I visit "/admin/people?user=administrator_user_test_last_access"
    Then I should not see the text "never"
    Then I should see the text "10 years ago"
