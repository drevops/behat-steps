@d7
Feature: Check that UserTrait for D7 works

  Background:
    Given I am logged in as a user with the "administrator" role
    And users:
      | name               | mail                           | roles              | status |
      | administrator      | admin@example.com              | administrator      | active |
      | authenticated_user | authenticated_user@example.com | authenticated user | active |

  @api
  Scenario: Assert visiting specific user page
    When I am logged in as a user with the "administrator" role
    And I visit user "authenticated_user" profile
    Then I should get a "200" HTTP response

  @api
  Scenario Outline: Assert check specific user by role
    Given user "<name>" has "<role>" roles assigned

    Examples:
      | name               | role               |
      | administrator      | administrator      |
      | authenticated_user | authenticated user |

  @api
  Scenario: Assert check specific user by role
    Given user "authenticated_user" does not have "administrator" role assigned
