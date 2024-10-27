@api
Feature: Check that PathTrait works

  Scenario: User is at the path without prefixed slash.
    Given I am an anonymous user
    When I go to "user/login"
    Then the path should be "user/login"

  Scenario: User is at the path with prefixed slash
    Given I am an anonymous user
    When I go to "/user/login"
    Then the path should be "/user/login"

  Scenario: User is at the '<front>' path.
    Given I am an anonymous user
    When I go to "/"
    Then the path should be "/"

  Scenario: Current page is not specified path.
    Given I am an anonymous user
    When I go to "/user/login"
    Then the path should be "/user/login"

    When I go to "/"
    Then the path should not be "/user/login"

  @trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should be :path" fails
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I go to "/user/login"
      Then the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path is "/user/login", but expected is "/nonexisting"
      """

  @trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should not be :path" fails
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I go to "/user/login"
      Then the path should not be "/user/login"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path should not be "/user/login"
      """

  Scenario: Assert "Given the basic authentication with the username :username and the password :password"
    Given users:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    And I am an anonymous user

    When I go to "/mysite_core/test-basic-auth"
    Then I should get a "401" HTTP response

    When I am logged in as a user with the "administrator" role
    And I go to "/mysite_core/test-basic-auth"
    Then I should get a "403" HTTP response

    When the basic authentication with the username "admin-test" and the password "admin-test"
    And I go to "/mysite_core/test-basic-auth"
    Then I should get a "200" HTTP response
