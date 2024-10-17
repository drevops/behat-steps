Feature: Check that LinkTrait works

  Scenario: Assert link with href without locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal.org"

  Scenario: Assert link with href with locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal.org" in "#block-system-powered-by,#block-bartik-powered,.block-system-powered-by-block"

  Scenario: Assert link with wildcard in href without locator
    Given I go to "/"
    Then I should see the link "Drupal" with "https://www.drupal*"

  Scenario: Assert link with href without locator does not exist
    Given I go to "/"
    Then I should not see the link "RandomLinkText" with "https://www.drupal.org"
    Then I should not see the link "Drupal" with "https://www.randomhref.org"

  Scenario: Assert link with href with locator does not exist
    Given I go to "/"
    Then I should not see the link "RandomLinkText" with "https://www.randomhref.org" in "#block-system-powered-by,#block-bartik-powered"
    Then I should not see the link "Drupal" with "https://www.drupal.org" in "#random-locator"

  Scenario: Assert link with wildcard in href without locator does not exist
    Given I go to "/"
    Then I should not see the link "Drupal" with "https://www.randomhref*"

  @api
  Scenario: Assert link with title
    Given I am logged in as a user with the "administrator" role
    When I go to "/"
    Then the link with title "Return to site content" exists
    And the link with title "Some non-existing title" does not exist
    And I click the link with title "Return to site content"

  @api
  Scenario: Assert link is absolute or not
    Given I am logged in as a user with the "administrator" role
    When I go to "/"
    Then the link with title "Drupal" is an absolute link
    And the link with title "Return to site content" is not an absolute link

  @trait:LinkTrait
  Scenario: Assert that negative assertion for "I click the link with title :title" fails with an error
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

  @trait:LinkTrait
  Scenario: Assert that negative assertion for "the link with title :title exists" fails with an error
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
