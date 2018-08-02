@d7
Feature: Check that UserTrait for D7 works

  Background:
    Given I am logged in as a user with the "administrator" role
    And users:
      | name                | mail                | roles              | status |
      | admin@myexample.com | admin@myexample.com | administrator      | active |
      | simple_user         | MW@myexample.com    | authenticated user | active |

  @api
  Scenario: Assert visiting specific user page
    When I am logged in as a user with the "administrator" role
    And I visit user "simple_user" profile
    Then I should get a "200" HTTP response

  @api
  Scenario Outline: Assert check specific user by role
    Given user "<name>" has "<role>" roles assigned
    Given user "<name>" has "<role>" status

    Examples:
      | name                | role               |
      | admin@myexample.com | administrator      |
      | simple_user         | authenticated user |

  @api
  Scenario: Assert check specific user by role
    Given user "simple_user" does not have "administrator" role assigned
