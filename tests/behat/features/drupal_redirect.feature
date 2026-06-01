Feature: Check that RedirectTrait works
  As Behat Steps library developer
  I want to provide tools to manage redirect entities programmatically
  So that users can test legacy-URL preservation, vanity URLs, and path rewrites

  @api
  Scenario: Assert "Given the following redirects exist:" creates redirects with default status code
    Given the following redirects exist:
      | from         | to          |
      | /old-path-1  | /user/login |
      | /old-path-2  | /user/login |
    When I am an anonymous user
    And I go to "/old-path-1"
    Then the path should be "/user/login"

    When I go to "/old-path-2"
    Then the path should be "/user/login"

  @api
  Scenario: Assert "Given the following redirects exist:" accepts explicit, omitted, and blank status codes
    Given the following redirects exist:
      | from              | to          | status_code |
      | /default-status   | /user/login |             |
      | /explicit-301     | /user/login | 301         |
      | /explicit-302     | /user/login | 302         |
      | /explicit-307     | /user/login | 307         |
    When I am an anonymous user
    And I go to "/default-status"
    Then the path should be "/user/login"

    When I go to "/explicit-301"
    Then the path should be "/user/login"

    When I go to "/explicit-302"
    Then the path should be "/user/login"

    When I go to "/explicit-307"
    Then the path should be "/user/login"

  @api
  Scenario: Assert "Given the following redirects exist:" accepts source paths without a leading slash
    Given the following redirects exist:
      | from           | to          |
      | no-leading     | /user/login |
    When I am an anonymous user
    And I go to "/no-leading"
    Then the path should be "/user/login"

  @api
  Scenario: Assert "Given the following redirects exist:" accepts external destinations
    Given the following redirects exist:
      | from   | to                                |
      | /promo | https://example.com/promo-landing |
    When I am logged in as a user with the "administrator" role
    And I go to "/admin/config/search/redirect"
    Then I should see the text "promo"

  @api
  Scenario: Assert "Given the following redirects do not exist:" removes targeted redirects only
    Given the following redirects exist:
      | from        | to          |
      | /keep-me    | /user/login |
      | /delete-me  | /user/login |
    When the following redirects do not exist:
      | /delete-me |
    And I am an anonymous user
    And I go to "/keep-me"
    Then the path should be "/user/login"

    When I go to "/delete-me"
    Then the path should not be "/user/login"

  @api
  Scenario: Assert "Given the following redirects do not exist:" silently skips paths that have no redirect
    Given the following redirects exist:
      | from     | to          |
      | /present | /user/login |
    When the following redirects do not exist:
      | /never-created |
      | /also-missing  |
    And I am an anonymous user
    And I go to "/present"
    Then the path should be "/user/login"

  @api
  Scenario: Assert "Given the following redirects do not exist:" accepts source paths without a leading slash
    Given the following redirects exist:
      | from         | to          |
      | /strip-slash | /user/login |
    When the following redirects do not exist:
      | strip-slash |
    And I am an anonymous user
    And I go to "/strip-slash"
    Then the path should not be "/user/login"

  @api @trait:Drupal\RedirectTrait
  Scenario: Assert "Given the following redirects exist:" fails on an unsupported status code
    Given some behat configuration
    And scenario steps:
      """
      Given the following redirects exist:
        | from     | to          | status_code |
        | /unused  | /user/login | 404         |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Invalid redirect status code "404". Allowed values are: 301, 302, 303, 307, 308.
      """

  @api @trait:Drupal\RedirectTrait
  Scenario: Assert "Given the following redirects exist:" fails on a non-numeric status code
    Given some behat configuration
    And scenario steps:
      """
      Given the following redirects exist:
        | from     | to          | status_code |
        | /unused  | /user/login | abc         |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Invalid redirect status code "abc". Allowed values are: 301, 302, 303, 307, 308.
      """

  @api @trait:Drupal\RedirectTrait
  Scenario: Assert "Given the following redirects exist:" fails when "from" is empty
    Given some behat configuration
    And scenario steps:
      """
      Given the following redirects exist:
        | from | to          |
        |      | /user/login |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Each redirect row must define a non-empty "from" path.
      """

  @api @trait:Drupal\RedirectTrait
  Scenario: Assert "Given the following redirects exist:" fails when "to" is empty
    Given some behat configuration
    And scenario steps:
      """
      Given the following redirects exist:
        | from     | to |
        | /unused  |    |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Redirect from "/unused" is missing a non-empty "to" value.
      """
