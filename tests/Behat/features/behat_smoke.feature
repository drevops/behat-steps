@smoke
Feature: Behat feature context smoke tests
  As Behat Steps library developer
  I want to provide tools to verify Behat test infrastructure
  So that users can be confident in the Behat step definitions

  @api
  Scenario: Assert that a module can be installed and uninstalled
    When I log in as a user with the "administer site configuration, administer modules" permissions
    When I go to "/admin/modules"
    Then the response status code should be 200
    And the "modules[ban][enable]" checkbox should be unchecked

    When I install a "ban" module
    And I go to "/admin/modules"
    Then the response status code should be 200
    And the "modules[ban][enable]" checkbox should be checked

    When I uninstall a "ban" module
    And I go to "/admin/modules"
    Then the response status code should be 200
    And the "modules[ban][enable]" checkbox should be unchecked

  @api
  Scenario: Assert that a cookie presence and absence assertions work
    When I log in as a user with the "administer site configuration" permissions
    Then cookie "testcookiename" exists
    And cookie "testcookiename_nonexisting" does not exist

  @phpserver
  Scenario: Assert that page text excludes what the reader cannot see
    When I go to "http://cli:8888/hidden_text.html"
    Then I should see "Visible heading"
    And I should see "Visible paragraph."
    And I should not see "BodyScriptValue"
    And I should not see "TypedScriptValue"
    And I should not see "DrupalSettingsValue"
    And I should not see "BodyStyleValue"
    And I should not see "HeadStyleValue"
