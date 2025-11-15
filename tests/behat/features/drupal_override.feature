Feature: Check that OverrideTrait works
  As Behat Steps library developer
  I want to provide tools to override authentication by role and entity creation
  So that users can test functionality at different permission levels and avoid duplicate entities

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

  @api
  Scenario: Assert override of createNodes deletes existing nodes before creation
    Given I am logged in as a user with the "administrator" role
    And "page" content:
      | title                                |
      | [TEST] Override Node To Be Recreated |
    When I go to "/admin/content"
    Then I should see the link "[TEST] Override Node To Be Recreated"
    # Create the same node again - override should delete first then recreate
    Given "page" content:
      | title                                |
      | [TEST] Override Node To Be Recreated |
    When I go to "/admin/content"
    Then I should see the link "[TEST] Override Node To Be Recreated"

  @api
  Scenario: Assert override of createUsers deletes existing users before creation
    Given users:
      | name                    | mail                          | status |
      | [TEST] override_user_01 | override_user_01@example.com  | 1      |
    When I am logged in as a user with the "administrator" role
    And I go to "/admin/people"
    Then I should see the text "[TEST] override_user_01"
    # Create the same user again - override should delete first then recreate
    Given users:
      | name                    | mail                          | status |
      | [TEST] override_user_01 | override_user_01@example.com  | 1      |
    When I go to "/admin/people"
    Then I should see the text "[TEST] override_user_01"

