@d9
Feature: Check that OverrideTrait works for or D9

  @api
  Scenario Outline: Assert override of authentication by role works
    Given I am logged in as a user with the "<role>" role
    When I go to "admin"
    Then I should get a "<code>" HTTP response
    Examples:
      | role               | code |
      | administrator      | 200  |
      | authenticated user | 403  |
      | anonymous user     | 403  |
