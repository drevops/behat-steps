@smoke @login
Feature: Login
  As Behat Steps library developer
  I want to provide tools to test authentication functionality
  So that users can verify access to secured resources

  @api
  Scenario: Administrator user logs in
    Given I am logged in as a user with the "administer site configuration, access administration pages" permissions
    When I go to "admin"
    Then I should be on "/admin"
    And I save screenshot

  @api @javascript
  Scenario: Administrator user logs in using a real browser
    Given I am logged in as a user with the "administer site configuration, access administration pages" permissions
    When I go to "admin"
    Then I should be on "/admin"
    And I save screenshot
