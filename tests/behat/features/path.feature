Feature: Check that PathTrait works

  Scenario: User is at the path without prefixed slash.
    Given I go to "user/login"
    Then I should be in the "user/login" path

  Scenario: User is at the path with prefixed slash
    Given I go to "/user/login"
    Then I should be in the "/user/login" path

  Scenario: User is at the '<front>' path.
    Given I go to "/"
    Then I should be in the "<front>" path

  Scenario: Current page is not specified path.
    Given I go to "/user/login"
    Then I should be in the "/user/login" path
    Then I go to "/"
    Then I should not be in the "/user/login" path

  Scenario: Visit a path and assert the final destination.
    Given I am an anonymous user
    When I visit "/user" then the final URL should be "/user/login"

  @api
  Scenario: Assert that a path can be visited or not with HTTP credentials.
    Given users:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    When I am an anonymous user
    Then I go to "/mysite_core/test-basic-auth"
    Then I should get a "401" HTTP response
    When I am logged in as a user with the "administrator" role
    Then I go to "/mysite_core/test-basic-auth"
    Then I should get a "403" HTTP response
    Then I can visit "/mysite_core/test-basic-auth" with HTTP credentials "admin-test" "admin-test"
    Then I cannot visit "/mysite_core/test-basic-auth" with HTTP credentials "admin-test-wrong" "admin-test-wrong"
