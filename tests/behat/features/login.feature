@smoke @login
Feature: Login
  As Behat Steps library developer
  I want to provide tools to test authentication functionality
  So that users can verify access to secured resources

  Scenario: Administrator user logs in
    When I log in as a user with the role "administrator"
    When I go to "admin"
    Then I should be on "/admin"
    And I save screenshot

  @javascript
  Scenario: Administrator user logs in using a real browser
    When I log in as a user with the role "administrator"
    When I go to "admin"
    Then I should be on "/admin"
    And I save screenshot

  # The selector matches only the homepage and no link carries the logout
  # text, so only the selector check on the homepage can confirm the login.
  @test-trait:Drupal\UserTrait
  Scenario: Assert that the logged-in selector on a homepage without a logout link confirms a login
    Given a configuration with the extension options:
      """
      'selectors' => ['logged_in_selector' => 'body.user-logged-in.path-frontpage'],
      'text' => ['logout' => 'Sign out of the site'],
      """
    And some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\UserTrait
  Scenario: Assert that the logged-in selector on a homepage without a logout link confirms a login in a real browser
    Given a configuration with the extension options:
      """
      'selectors' => ['logged_in_selector' => 'body.user-logged-in.path-frontpage'],
      'text' => ['logout' => 'Sign out of the site'],
      """
    And some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I log in as a user with the role "administrator"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\UserTrait
  Scenario: Assert that a login fails when no page shows the logged-in selector or a logout link
    Given a configuration with the extension options:
      """
      'selectors' => ['logged_in_selector' => 'body.user-logged-in.path-nonexistent'],
      'text' => ['logout' => 'Sign out of the site'],
      """
    And some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to determine if logged in because "Sign out of the site" ('logout') link cannot be found for user "
      """
