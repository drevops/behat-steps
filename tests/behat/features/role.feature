@d8 @d9
Feature: Check that RoleTrait works for D8

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
