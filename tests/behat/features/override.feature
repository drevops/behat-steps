Feature: Check that OverrideTrait works
  As Behat Steps library developer
  I want to provide tools to override authentication by role
  So that users can test functionality at different permission levels

  @api
  Scenario Outline: Assert override of authentication by role works
    Given I am logged in as a user with the "<role>" role
    When I go to "admin"
    Then I should get a "<code>" HTTP response
    Examples:
      | role               | code |
      | anonymous user     | 403  |
      | authenticated user | 403  |
      | administrator      | 200  |
