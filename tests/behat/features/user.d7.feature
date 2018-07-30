@d7 @test1
Feature: Check that UserTrait for D7 works

  @api
  Scenario: Assert visiting specific user page
    When I am logged in as a user with the "administrator" role
    And I visit user "lDmi1wX0" profile
    Then I should get a "200" HTTP response

  @api
  Scenario Outline: Assert check specific user by role
    Given user "<name>" has "<role>" roles assigned

    Examples:
      | name     | role               |
      | admin    | administrator      |
      | lDmi1wX0 | authenticated user |

  @api
  Scenario: Assert check user status
    Given user "admin" has "active" status
