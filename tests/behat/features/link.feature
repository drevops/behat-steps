Feature: Check that LinkTrait works

  @d7 @d8
  Scenario: Assert link with href without locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal.org"
  @d7 @d8
  Scenario: Assert link with href with locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal.org" in "#block-system-powered-by,#block-bartik-powered"
  @d7 @d8
  Scenario: Assert link with wildcard in href without locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal*"

  @api @d8
  Scenario: Assert link with title
    Given I am logged in as a user with the "administrator" role
    When I go to "/"
    Then the link with title "Return to site content" exists
    And the link with title "Some non-existing title" does not exist
    And I click the link with title "Return to site content"

  @trait:LinkTrait @d8
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I go to "/"
      Then I click the link with title "Some non-existing title"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with title "Some non-existing title" does not exist.
      """

  @trait:LinkTrait @d8
  Scenario: Assert that negative assertions fail with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I go to "/"
      Then the link with title "Return to site content" does not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The link with title "Return to site content" exists, but should not.
      """
