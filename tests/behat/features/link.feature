@d7 @d8
Feature: Check that LinkTrait works

  Scenario: Assert link with href without locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal.org"

  Scenario: Assert link with href with locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal.org" in "#block-system-powered-by,#block-bartik-powered"

  Scenario: Assert link with wildcard in href without locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal*"
