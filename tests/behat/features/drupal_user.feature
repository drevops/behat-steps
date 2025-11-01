@user
Feature: Check that UserTrait works
  As Behat Steps library developer
  I want to provide tools to manage users and their properties
  So that users can test user functionality and permissions

  Background:
    Given users:
      | name                       | mail                                     | roles         | status |
      | administrator_user         | administrator_user@myexample.com         | administrator | 1      |
      | authenticated_user         | authenticated_user@myexample.com         |               | 1      |
      | authenticated_user_blocked | authenticated_user_blocked@myexample.com |               | 0      |

  @api
  Scenario: Assert "Given the following users do not exist:" by name
    Given I am logged in as a user with the "administrator" role
    And user "authenticated_user" should exist
    And user "non_existing" should not exist
    When the following users do not exist:
      | name               |
      | authenticated_user |
      | non_existing       |
    Then user "authenticated_user" should not exist
    And user "non_existing" should not exist

  @api
  Scenario: Assert "Given the following users do not exist:" by email
    Given I am logged in as a user with the "administrator" role
    And user "authenticated_user" should exist
    And user "non_existing" should not exist
    When the following users do not exist:
      | mail                             |
      | authenticated_user@myexample.com |
      | non_existing@example.com         |
    Then user "authenticated_user" should not exist
    And user "non_existing" should not exist

  @api
  Scenario: Assert "When the password for the user :name is :password" works
    Given the password for the user "authenticated_user" is "password123"

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When the password for the user :name is :password" fails for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      When the password for the user "non_existing" is "password123"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When the password for the user :name is :password" fails for an existing user with an empty password
    Given some behat configuration
    And scenario steps:
      """
      When the password for the user "authenticated_user" is ""
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Password must not be empty.
      """

  @api
  Scenario: Assert "When the last access time for the user :name is :datetime" works
    Given the last access time for the user "authenticated_user" is "Friday, 22 November 2024 13:46:14"
    And the last access time for the user "authenticated_user" is "1732319174"
    And the last access time for the user "authenticated_user" is "-10 years"
    And the last access time for the user "authenticated_user" is "[relative:-10 years]"

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When the last access time for the user :name is :datetime" fails for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      When the last access time for the user "non_existing" is "Friday, 22 November 2024 13:46:14"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When the last access time for the user :name is :datetime" fails for invalid datetime
    Given some behat configuration
    And scenario steps:
      """
      When the last access time for the user "authenticated_user" is "ten years"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Invalid date format.
      """

  @api
  Scenario: Assert "Given the last login time for the user :name is :datetime" works
    Given the last login time for the user "authenticated_user" is "Friday, 22 November 2024 13:46:14"
    Given the last login time for the user "authenticated_user" is "1732319174"
    Given the last login time for the user "authenticated_user" is "-10 years"
    Given the last login time for the user "authenticated_user" is "[relative:-10 years]"

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Given the last login time for the user :name is :datetime" fails for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      Given the last login time for the user "non_existing" is "Friday, 22 November 2024 13:46:14"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Given the last login time for the user :name is :datetime" fails for invalid datetime
    Given some behat configuration
    And scenario steps:
      """
      Given the last login time for the user "authenticated_user" is "ten years"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Invalid date format.
      """

  @api
  Scenario: Assert "When I visit :name user profile page" for existing user
    Given I am logged in as a user with the "administrator" role
    When I visit "authenticated_user" user profile page
    Then I should get a 200 HTTP response

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When I visit :name user profile page" fails for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "non_existing" user profile page
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api
  Scenario: Assert "When I visit my own user profile page" for existing user
    Given I am logged in as a user with the "administrator" role
    When I visit my own user profile page
    Then I should get a 200 HTTP response

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When I visit my own user profile page" fails for non-logged in user
    Given some behat configuration
    And scenario steps:
      """
      When I visit my own user profile page
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Current user is not logged in.
      """

  @api
  Scenario: Assert "When I visit :name user profile edit page" for existing user
    Given I am logged in as a user with the "administrator" role
    When I visit "authenticated_user" user profile edit page
    Then I should get a 200 HTTP response

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When I visit :name user profile edit page" fails for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "non_existing" user profile edit page
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api
  Scenario: Assert "When I visit my own user profile edit page" for existing user
    Given I am logged in as a user with the "administrator" role
    When I visit my own user profile edit page
    Then I should get a 200 HTTP response

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When I visit my own user profile edit page" fails for non-logged in user
    Given some behat configuration
    And scenario steps:
      """
      When I visit my own user profile edit page
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Current user is not logged in.
      """

  @api
  Scenario: Assert "When I visit :name user profile delete page" for existing user
    Given I am logged in as a user with the "administrator" role
    When I visit "authenticated_user" user profile delete page
    Then I should get a 200 HTTP response

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When I visit :name user profile delete page" fails for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "non_existing" user profile delete page
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api
  Scenario: Assert "When I visit my own user profile delete page" for existing user
    Given I am logged in as a user with the "administrator" role
    When I visit my own user profile delete page
    Then I should get a 200 HTTP response

  @api @trait:Drupal\UserTrait
  Scenario: Assert "When I visit my own user profile delete page" fails for non-logged in user
    Given some behat configuration
    And scenario steps:
      """
      When I visit my own user profile delete page
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Current user is not logged in.
      """

  @api
  Scenario: Assert "Then the user :name should have the role(s) :roles assigned" works
    Given users:
      | name           | roles                         |
      | single_role    | administrator                 |
      | multiple_roles | administrator, content_editor |
    Then the user "single_role" should have the role "administrator" assigned
    And the user "multiple_roles" should have the role "administrator, content_editor" assigned
    And the user "multiple_roles" should have the role "administrator,content_editor" assigned

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should have the role(s) :roles assigned" fails for missing single role
    Given some behat configuration
    And scenario steps:
      """
      Given users:
        | name           | roles                         |
        | single_role    | administrator                 |
        | multiple_roles | administrator, content_editor |
      Then the user "single_role" should have the role "content_editor" assigned
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      User "single_role" does not have role(s) "content_editor", but has roles "authenticated", "administrator".
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should have the role(s) :roles assigned" fails for missing multiple roles
    Given some behat configuration
    And scenario steps:
      """
      Given users:
        | name           | roles                         |
        | single_role    | administrator                 |
        | multiple_roles | administrator, content_editor |
      Then the user "single_role" should have the roles "administrator, content_editor" assigned
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      User "single_role" does not have role(s) "administrator", "content_editor", but has roles "authenticated", "administrator".
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should have the role(s) :roles assigned" fails for for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      Then the user "non_existing" should have the role "administrator" assigned
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api
  Scenario: Assert "Then the user :name should not have the role(s) :roles assigned" works
    Given users:
      | name        | roles         |
      | single_role | administrator |
    Then the user "single_role" should not have the role "content_editor" assigned
    And the user "single_role" should not have the roles "content_editor, content_approver" assigned
    And the user "single_role" should not have the role "content_editor,content_approver" assigned

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should not have the role(s) :roles assigned" fails for having a single role
    Given some behat configuration
    And scenario steps:
      """
      Given users:
        | name           | roles                         |
        | single_role    | administrator                 |
      Then the user "single_role" should not have the role "administrator" assigned
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      User "single_role" should not have role(s) "administrator", but has "authenticated", "administrator".
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should not have the role(s) :roles assigned" fails for missing multiple roles
    Given some behat configuration
    And scenario steps:
      """
      Given users:
        | name           | roles                                           |
        | single_role    | administrator, content_editor, content_approver |
      Then the user "single_role" should not have the roles "administrator, content_editor" assigned
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      User "single_role" should not have role(s) "administrator", "content_editor", but has "authenticated", "administrator", "content_editor", "content_approver".
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should not have the role(s) :roles assigned" fails for for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      Then the user "non_existing" should not have the role "administrator" assigned
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api
  Scenario: Assert "Then the user :name should be blocked"
    Then the user "authenticated_user_blocked" should be blocked

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should be blocked" fails for non-blocked user
    Given some behat configuration
    And scenario steps:
      """
      Then the user "authenticated_user" should be blocked
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      User "authenticated_user" is expected to be blocked, but they are not.
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should be blocked" fails for for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      Then the user "non_existing" should be blocked
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """

  @api
  Scenario: Assert "Then the user :name should not be blocked"
    Then the user "authenticated_user" should not be blocked

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should not be blocked" fails for non-blocked user
    Given some behat configuration
    And scenario steps:
      """
      Then the user "authenticated_user_blocked" should not be blocked
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      User "authenticated_user_blocked" is expected to not be blocked, but they are.
      """

  @api @trait:Drupal\UserTrait
  Scenario: Assert "Then the user :name should not be blocked" fails for for non-existing user
    Given some behat configuration
    And scenario steps:
      """
      Then the user "non_existing" should not be blocked
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      User with name "non_existing" does not exist.
      """
