Feature: Check that PathTrait works
  As Behat Steps library developer
  I want to test path-related functionality
  So that I can verify proper URL paths in my application

  @api
  Scenario Outline: Assert that the path is the same as the given path
    Given the user is anonymous
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

  @api
  Scenario Outline: Assert that the path is not the same as the given path
    Given the user is anonymous
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

  @trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should be :path" fails
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I go to "/user/login"
      Then the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path is "/user/login", but expected is "/nonexisting"
      """

  @trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should be :path" fails for "<front>"
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I go to "/user/login"
      Then the path should be "<front>"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path is "/user/login", but expected is "<front>"
      """

  @trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should be :path" fails for "/"
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I go to "/user/login"
      Then the path should be "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path is "/user/login", but expected is "/"
      """

  @trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should not be :path" fails
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I go to "/user/login"
      Then the path should not be "/user/login"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path should not be "/user/login"
      """

  @trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should not be :path" fails for "/"
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I go to "/"
      Then the path should not be "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path should not be "/"
      """

  @trait:PathTrait
  Scenario: Assert that negative assertion for "Then the path should not be :path" fails for "<front>"
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I go to "/"
      Then the path should not be "<front>"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path should not be "<front>"
      """

  @api
  Scenario: Assert that URL has query parameter with a specific value
    Given I log in as a user with the "administrator" role
    When I visit "/admin/content?status=1&type=article"
    Then the current URL should have the "status" parameter
    And the current URL should have the "status" parameter with the value "1"
    And the current URL should have the "type" parameter
    And the current URL should have the "type" parameter with the value "article"

  @api
  Scenario: Assert that URL does not have query parameter with specific value
    Given I log in as a user with the "administrator" role
    When I visit "/admin/content?status=1&type=article"
    Then the current URL should not have the "status" parameter with the value "0"
    And the current URL should not have the "other" parameter
    And the current URL should not have the "type" parameter with the value "page"

  @trait:PathTrait
  Scenario: Assert failure when URL should have parameter but doesn't
    Given some behat configuration
    And scenario steps:
      """
      Given I log in as a user with the "administrator" role
      When I visit "/admin/content?status=1&type=article"
      Then the current URL should have the "filter" parameter with the value "recent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "filter" is not in the URL
      """

  @trait:PathTrait
  Scenario: Assert failure when URL parameter has wrong value
    Given some behat configuration
    And scenario steps:
      """
      Given I log in as a user with the "administrator" role
      When I visit "/admin/content?status=1&type=article"
      Then the current URL should have the "status" parameter with the value "2"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "status" is in the URL but with the wrong value "1"
      """

  @trait:PathTrait
  Scenario: Assert failure when URL shouldn't have parameter but does
    Given some behat configuration
    And scenario steps:
      """
      Given I log in as a user with the "administrator" role
      When I visit "/admin/content?status=1&type=article"
      Then the current URL should not have the "status" parameter with the value "1"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "status" with value "1" is in the URL but should not be
      """

  @trait:PathTrait
  Scenario: Assert failure when URL contains parameter that should not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I log in as a user with the "administrator" role
      When I visit "/admin/content?status=1&type=article"
      Then the current URL should not have the "status" parameter
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "status" is in the URL but should not be
      """

  @api
  Scenario: Assert URL parameter with value doesn't exist when parameter is absent
    Given I log in as a user with the "administrator" role
    When I visit "/admin/content?status=1"
    Then the current URL should not have the "nonexistent" parameter with the value "value"

  @api
  Scenario: Assert that a URL parameter with a zero or empty value counts as present
    Given the user is anonymous
    When I visit "/user/login?filter=0&empty=&keyword=recent"
    Then the current URL should have the "filter" parameter
    And the current URL should have the "filter" parameter with the value "0"
    And the current URL should have the "empty" parameter
    And the current URL should have the "empty" parameter with the value ""
    And the current URL should have the "keyword" parameter with the value "recent"
    And the current URL should not have the "missing" parameter
    And the current URL should not have the "missing" parameter with the value "0"
    And the current URL should not have the "filter" parameter with the value "1"

  @trait:PathTrait
  Scenario: Assert failure when URL parameter with a zero value should not exist
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I visit "/user/login?filter=0"
      Then the current URL should not have the "filter" parameter
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "filter" is in the URL but should not be
      """

  @trait:PathTrait
  Scenario: Assert failure when URL parameter with an empty value should not exist
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I visit "/user/login?empty="
      Then the current URL should not have the "empty" parameter
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "empty" is in the URL but should not be
      """

  @trait:PathTrait
  Scenario: Assert failure when URL parameter should not have the zero value it carries
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I visit "/user/login?filter=0"
      Then the current URL should not have the "filter" parameter with the value "0"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "filter" with value "0" is in the URL but should not be
      """

  @trait:PathTrait
  Scenario: Assert failure when URL parameter should not have the empty value it carries
    Given some behat configuration
    And scenario steps:
      """
      Given the user is anonymous
      When I visit "/user/login?empty="
      Then the current URL should not have the "empty" parameter with the value ""
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The parameter "empty" with value "" is in the URL but should not be
      """

  @api
  Scenario: Assert "When I go back" navigates to the previous page
    Given the user is anonymous
    When I go to "/user/login"
    And I go to "/user/password"
    And I go back
    Then the path should be "/user/login"

  @api
  Scenario: Assert "Given the basic authentication has the username :username and the password :password"
    Given the following users exist:
      | name       | mail               | pass       |
      | admin-test | admin-test@bar.com | admin-test |
    And the user is anonymous

    When I go to "/mysite_core/test-basic-auth"
    Then the response status code should be 401

    When I log in as a user with the "administrator" role
    And I go to "/mysite_core/test-basic-auth"
    Then the response status code should be 403

    When the basic authentication has the username "admin-test" and the password "admin-test"
    And I go to "/mysite_core/test-basic-auth"
    Then the response status code should be 200
