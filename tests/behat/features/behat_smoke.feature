@smoke
Feature: Behat feature context smoke tests

  Assertions for feature context steps (in FeatureContext.php) that are used
  to test the Behat-steps step definitions defined in `src`.

  Basically, tests for tests, which are testing our steps provided by this package.

  @api
  Scenario: Assert that a module can be installed and uninstalled
    Given I am logged in as a user with the "administer site configuration, administer modules" permissions
    When I go to "/admin/modules"
    And the response status code should be 200
    Then the "modules[ban][enable]" checkbox should be unchecked

    When I install a "ban" module
    And I go to "/admin/modules"
    And the response status code should be 200
    Then the "modules[ban][enable]" checkbox should be checked

    When I uninstall a "ban" module
    And I go to "/admin/modules"
    And the response status code should be 200
    Then the "modules[ban][enable]" checkbox should be unchecked

  @api
  Scenario: Assert that a cookie presence and absence assertions work
    Given I am logged in as a user with the "administer site configuration" permissions
    Then cookie "testcookiename" exists
    And cookie "testcookiename_nonexisting" does not exist
