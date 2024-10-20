Feature: Check that RoleTrait works

  @api
  Scenario: Assert "Given role :name with permissions :permissions"
    Given role "customrole" with permissions "access administration pages, administer filters"
    And I am logged in as a user with the "customrole" role

    When I go to "/admin"
    Then I should get a 200 HTTP response

    When I go to "/admin/config/content/formats"
    Then I should get a 200 HTTP response

    When I go to "/admin/structure/types"
    Then I should get a 403 HTTP response

  @api
  Scenario: Assert "Given role :name with permissions :permissions" with existing role
    Given role "customrole" with permissions "administer site configuration"
    Then I am logged in as a user with the "customrole" role
    Then I go to "/admin/config/system/site-information"
    Then I should get a 200 HTTP response
    Then I go to "/admin/config/content/formats"
    Then I should get a 403 HTTP response
    Then I go to "/admin/structure/types"
    Then I should get a 403 HTTP response

    Then role "customrole" with permissions "administer site configuration, access administration pages, administer filters"
    Then I log out
    # We should not need clear cache at here. Re-check later.
    Then I am logged in as a user with the "administrator" role
    Then I visit "/admin/config/development/performance"
    Then I press the "Clear all cache" button
    Then I log out

    Then I am logged in as a user with the "customrole" role

    Then I go to "/admin/config/system/site-information"
    Then I should get a 200 HTTP response
    Then I go to "/admin/config/content/formats"
    Then I should get a 200 HTTP response
    Then I go to "/admin/structure/types"
    Then I should get a 403 HTTP response

  @api
  Scenario: Assert create multiple roles from the specified table.
    Given roles:
      | name       | permissions                                     |
      | test-role1 | access administration pages                     |
      | test-role2 | access administration pages, administer filters |

    And I am logged in as a user with the "test-role2" role
    When I go to "/admin"
    Then I should get a 200 HTTP response
    When I go to "/admin/config/content/formats"
    Then I should get a 200 HTTP response
    When I go to "/admin/structure/types"
    Then I should get a 403 HTTP response
