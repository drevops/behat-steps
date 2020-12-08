@smoke
Feature: Behat feature context smoke tests

  Assertions for D7 and D8 feature context steps (in FeatureContextD7.php and
  FeatureContextD8.php) that are used to test the Behat-steps step definitions
  defined in `src`.

  Basically, tests for tests, which are testing our steps provided by this package.

  @api @d8
  Scenario: Assert that a module can be installed and uninstalled
    Given I am logged in as a user with the "administer site configuration, administer modules" permissions
    When I go to "/admin/modules"
    And the response status code should be 200
    Then the "modules[book][enable]" checkbox should be unchecked

    When I install a "book" module
    And I go to "/admin/modules"
    And the response status code should be 200
    Then the "modules[book][enable]" checkbox should be checked

    When I uninstall a "book" module
    And I go to "/admin/modules"
    And the response status code should be 200
    Then the "modules[book][enable]" checkbox should be unchecked

  @api @d8
  Scenario: Assert that a cookie presence and absence assertions work
    Given I am logged in as a user with the "administer site configuration" permissions
    Then cookie "testcookiename" exists
    And cookie "testcookiename_nonexisting" does not exist
