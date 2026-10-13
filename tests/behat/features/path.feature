Feature: Check that PathTrait works
  As Behat Steps library developer
  I want to test path-related functionality
  So that I can verify proper URL paths in my application

  Scenario Outline: Assert that the path is the same as the given path
    Given I am an anonymous user
    When I go to "<src>"
    Then the path should be "<dst>"
    Examples:
      | src         | dst         |
      | /           | /           |
      | /           | <front>     |
      | <front>     | /           |
      | <front>     | <front>     |
      | /user/login | /user/login |
      | user/login  | user/login  |

  Scenario Outline: Assert that the path is not the same as the given path
    Given I am an anonymous user
    When I go to "<src>"
    Then the path should not be "<dst>"
    Examples:
      | src     | dst     |
      | /       | /user   |
      | /user   | /       |
      | user    | /       |
      | <front> | /user   |
      | /user   | <front> |
      | user    | <front> |

  @test-trait:PathTrait
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
      The current path is "/user/login", but it should be "/nonexisting"
      """

  @test-trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should be :path" fails for "<front>"
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I go to "/user/login"
      Then the path should be "<front>"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The current path is "/user/login", but it should be "<front>"
      """

  @test-trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should be :path" fails for "/"
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I go to "/user/login"
      Then the path should be "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The current path is "/user/login", but it should be "/"
      """

  @test-trait:PathTrait
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
      The current path should not be "/user/login", but it is.
      """

  @test-trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should not be :path" fails for "/"
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I go to "/"
      Then the path should not be "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The current path should not be "/", but it is.
      """

  @test-trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should not be :path" fails for "<front>"
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I go to "/"
      Then the path should not be "<front>"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The current path should not be "<front>", but it is.
      """

  Scenario: Assert that URL has query parameter with a specific value
    Given I am logged in as a user with the role "administrator"
    When I visit "/admin/content?status=1&type=article"
    Then the URL should have the query parameter "status"
    And the URL should have the query parameter "status" with the value "1"
    And the URL should have the query parameter "type"
    And the URL should have the query parameter "type" with the value "article"

  Scenario: Assert that URL does not have query parameter with specific value
    Given I am logged in as a user with the role "administrator"
    When I visit "/admin/content?status=1&type=article"
    Then the URL should not have the query parameter "status" with the value "0"
    And the URL should not have the query parameter "other"
    And the URL should not have the query parameter "type" with the value "page"

  @test-trait:PathTrait
  Scenario: Assert failure when URL should have parameter but doesn't
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit "/admin/content?status=1&type=article"
      Then the URL should have the query parameter "filter" with the value "recent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "filter" is not in the URL
      """

  @test-trait:PathTrait
  Scenario: Assert failure when URL parameter has wrong value
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit "/admin/content?status=1&type=article"
      Then the URL should have the query parameter "status" with the value "2"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "status" is in the URL but with the wrong value "1"
      """

  @test-trait:PathTrait
  Scenario: Assert failure when URL shouldn't have parameter but does
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit "/admin/content?status=1&type=article"
      Then the URL should not have the query parameter "status" with the value "1"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "status" with value "1" is in the URL, but it should not be
      """

  @test-trait:PathTrait
  Scenario: Assert failure when URL contains parameter that should not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit "/admin/content?status=1&type=article"
      Then the URL should not have the query parameter "status"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "status" is in the URL, but it should not be
      """

  Scenario: Assert URL parameter with value doesn't exist when parameter is absent
    Given I am logged in as a user with the role "administrator"
    When I visit "/admin/content?status=1"
    Then the URL should not have the query parameter "nonexistent" with the value "value"

  Scenario: Assert that a URL parameter with a zero or empty value counts as present
    Given I am an anonymous user
    When I visit "/user/login?filter=0&empty=&keyword=recent"
    Then the URL should have the query parameter "filter"
    And the URL should have the query parameter "filter" with the value "0"
    And the URL should have the query parameter "empty"
    And the URL should have the query parameter "empty" with the value ""
    And the URL should have the query parameter "keyword" with the value "recent"
    And the URL should not have the query parameter "missing"
    And the URL should not have the query parameter "missing" with the value "0"
    And the URL should not have the query parameter "filter" with the value "1"

  @test-trait:PathTrait
  Scenario: Assert failure when URL parameter with a zero value should not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/user/login?filter=0"
      Then the URL should not have the query parameter "filter"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "filter" is in the URL, but it should not be
      """

  @test-trait:PathTrait
  Scenario: Assert failure when URL parameter with an empty value should not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/user/login?empty="
      Then the URL should not have the query parameter "empty"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "empty" is in the URL, but it should not be
      """

  @test-trait:PathTrait
  Scenario: Assert failure when URL parameter should not have the zero value it carries
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/user/login?filter=0"
      Then the URL should not have the query parameter "filter" with the value "0"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "filter" with value "0" is in the URL, but it should not be
      """

  @test-trait:PathTrait
  Scenario: Assert failure when URL parameter should not have the empty value it carries
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/user/login?empty="
      Then the URL should not have the query parameter "empty" with the value ""
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "empty" with value "" is in the URL, but it should not be
      """

  Scenario: Assert "When I go back" navigates to the previous page
    Given I am an anonymous user
    When I go to "/user/login"
    And I go to "/user/password"
    And I go back
    Then the path should be "/user/login"

  Scenario: Assert "Given I am using basic authentication with the username :username and the password :password"
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    And I am an anonymous user

    When I go to "/mysite_core/test-basic-auth"
    Then the response status code should be 401

    When I am logged in as a user with the role "administrator"
    And I go to "/mysite_core/test-basic-auth"
    Then the response status code should be 403

    When I am using basic authentication with the username "admin-test" and the password "admin-test"
    And I go to "/mysite_core/test-basic-auth"
    Then the response status code should be 200
