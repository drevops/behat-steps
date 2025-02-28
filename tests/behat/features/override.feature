Feature: Check that OverrideTrait works

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
