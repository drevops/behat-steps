Feature: Check that RoleTrait works
  As Behat Steps library developer
  I want to create custom roles with specific permissions
  So that I can test different user permission levels

  @api
  Scenario: Assert "Given the role :role_name with the permissions :permissions" works
    Given the role "customrole" with the permissions "access administration pages, administer filters"
    And I am logged in as a user with the "customrole" role

    When I go to "/admin"
    Then I should get a 200 HTTP response

    When I go to "/admin/config/content/formats"
    Then I should get a 200 HTTP response

  @api
  Scenario: Assert "Given the following roles:" works
    Given the following roles:
      | name       | permissions                                     |
      | test-role1 | access administration pages                     |
      | test-role2 | access administration pages, administer filters |

    When I am logged in as a user with the "test-role2" role
    And I go to "/admin"
    Then I should get a 200 HTTP response

    When I go to "/admin/config/content/formats"
    Then I should get a 200 HTTP response

    When I go to "/admin/structure/types"
    Then I should get a 403 HTTP response
